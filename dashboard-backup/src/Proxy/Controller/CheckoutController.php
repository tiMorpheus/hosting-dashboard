<?php

namespace Proxy\Controller;

use Axelarge\ArrayTools\Arr;
use Blazing\Common\RestApiRequestHandler\Exception\BadRequestException;
use Blazing\Reseller\Api\Api\Entity\PackageEntity;
use Buzz\Exception\InvalidArgumentException;
use ErrorException;
use Gears\Arrays;
use Proxy\Util\TFA;
use Proxy\Util\Util;
use RuntimeException;
use Silex\Application;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernel;

class CheckoutController extends AbstractController
{
    public function checkout($type, $country = false, $category = false)
    {
        // Get bought products, sort by by whmcs asc, id asc
        $bought = $this->getUser()->getPackages();
        if(empty($bought) && $type === 'upgrade') {
            return $this->redirectToRoute('dashboard');
        }
        usort($bought, function($row1) { return $row1['source'] == 'whmcs' ? -1 : 1; });
        usort($bought, function($row1, $row2) { return $row1['id'] < $row2['id'] ? -1 : 1; });
        $bought = array_map(function($row) {
            return [
                'country'  => $row[ 'country' ],
                'category' => $row[ 'category' ],
                'amount'   => $row[ 'ports' ],
                'source'   => $row[ 'source' ],
                'id'       => $row[ 'id' ],
                'status'   => $row[ 'status' ],
            ];
        }, $bought);

        // Fix preselected category
        if ('static' == $category) {
            $category = 'dedicated';
        }
        elseif ('rotate' == $category) {
            $category = 'rotating';
        }

        // Get available products (in whmcs)
        $available = array_filter(array_map(function(array $product) use ($bought) {
            // Ignore IPv6 stuff
            if (!$product[ 'meta' ][ 'country' ] or !$product[ 'meta' ][ 'category' ]) {
                return false;
            }
            $enabled = true;

            foreach ($bought as $exist) {
                if ($product[ 'meta' ][ 'country' ] == $exist[ 'country' ] and
                    $product[ 'meta' ][ 'category' ] == $exist[ 'category' ]
                ) {
                    $enabled = false;
                }
            }

            return [
                'country'        => $product[ 'meta' ][ 'country' ],
                'category'       => $product[ 'meta' ][ 'category' ],
                'countBounds'    => $this->getCountBounds($product[ 'meta' ][ 'country' ], $product[ 'meta' ][ 'category' ]),
                //'amount'       => $this->getCountBounds($product[ 'meta' ][ 'country' ], $product[ 'meta' ][ 'category' ]),
                'trial'          => $product[ 'trial' ],
                'trialQuantity'  => $product['trialQuantity'],
                'disabled'       => !$enabled
            ];
        }, $this->getPricingTiers()));

        if($type === 'buy') {
            return $this->app['twig']->render('checkout/purchase.html.twig', [
                'upgradableProducts'   => array_values($bought),
                'available'            => array_values($available),
                'data'                 => [
                    'country'  => $country,
                    'category' => $category,
                    'quantity' => $this->request->get('quantity'),
                    'billingCycle' => $this->request->get('billingCycle'),
                    'showTrials' => $this->request->get('trial')
                ]
            ]);
        }

        foreach ($available as $product) {
            foreach ($bought as $i => $existentProduct) {
                if (!isset($bought[ $i ][ 'product' ])) {
                    $bought[ $i ][ 'product' ] = [];
                }

                if ($product[ 'country' ] == $existentProduct[ 'country' ] and
                    $product[ 'category' ] == $existentProduct[ 'category' ]
                ) {
                    $bought[ $i ][ 'product' ] = $product;
                }
            }
        }

        // Extended status
        $userServices = $this->app['integration.whmcs.plugin']->getUserProducts($this->getUser()->getDetails('whmcsId'));
        if (!empty($userServices['products'])) {
            $userServices = $userServices['products'];
            $pricingProducts = $this->getPricingTiers();

            // Look over product to find product id, and then status
            foreach ($bought as $i => $package) {
                $foundService = false;
                foreach ($pricingProducts as $product) {
                    if ($product['meta']['country'] == $package['country'] and
                        $product['meta']['category'] == $package['category']) {
                        foreach ($userServices as $ind => $userService) {
                            // Equality is found
                            if ($product['productId'] == $userService['productId']) {
                                $foundService = $userService;
                                unset($userServices[$ind]);
                                break 2;
                            }
                        }
                    }
                }

                // Push information
                if ($foundService) {
                    $bought[ $i ][ 'product' ][ 'amount' ] = $bought[ $i ][ 'product' ][ 'countBounds' ][$foundService[ 'billingCycle' ]];
                    $bought[ $i ][ 'extended' ][ 'billingCycle' ] = $foundService[ 'billingCycle' ];
                    $bought[ $i ][ 'extended' ][ 'hasCancelRequest' ] = $foundService[ 'hasCancelRequest' ];
                    $bought[ $i ][ 'extended' ][ 'serviceId' ] = $foundService[ 'serviceId' ];
                    $bought[ $i ][ 'extended' ][ 'upgradeEnabled' ] = $foundService[ 'upgradeEnabled' ];
                    if(!$foundService[ 'upgradeEnabled' ]) {
                        $bought[$i]['extended']['upgradeDisabledReason'] = !empty($foundService['upgradeDisabledReason']) ? $foundService['upgradeDisabledReason'] : '';
                    }
                    if (!empty($foundService[ 'promo' ])) {
                        $bought[ $i ][ 'extended' ][ 'promo' ] = $foundService['promo'];
                    }
                    if (!empty($foundService[ 'statusReason' ])) {
                        $bought[ $i ][ 'extended' ][ 'statusReason' ] = $foundService[ 'statusReason' ];
                    }
                    if (!empty($foundService[ 'unpaidInvoice' ])) {
                        $bought[ $i ][ 'extended' ][ 'invoiceUrl' ] = $foundService[ 'unpaidInvoice' ];
                    }
                }
            }
        }

        $pendingTrials = [];
        foreach ($userServices as $userService) {
            if(is_array($userService) && array_key_exists('status', $userService) && $userService['status'] === 'pending_trial') {
                if ('rotate' == $userService[ 'whmcs_category' ]) {
                    $userService[ 'whmcs_category' ] = 'rotating';
                }

                $data = [
                    'country'  => $userService[ 'whmcs_country' ],
                    'category' => $userService[ 'whmcs_category' ],
                    'amount'   => $userService[ 'quantity' ],
                    'source'   => 'whmcs',
                    'id'       => 0,
                    'status'   => 'Pending Trial',
                ];

                foreach ($available as $product) {
                    if ($product[ 'country' ] == $userService[ 'whmcs_country' ] and
                        $product[ 'category' ] == $userService[ 'whmcs_category' ]
                    ) {
                        $data[ 'product' ] = $product;
                    }
                }

                $pendingTrials[] = $userService[ 'quantity' ] . ' x ' . $this->app['util.formatter']->humanizeProxyName([
                    'country'  => $userService[ 'whmcs_country' ],
                    'category' => $userService[ 'whmcs_category' ]
                ]);

                $bought[] = $data;
            }
        }

        $templateData = [
            'upgradableProducts'   => array_values($bought),
            //'available'            => array_values($available),
            'migrateFeature'       => true,
            'migrateAutomatically' => true,
            'data'                 => [
                'country'  => $country,
                'category' => $category,
                'quantity' => $this->request->get('quantity'),
                'billingCycle' => $this->request->get('billingCycle')
            ],
            'pendingTrials' => $pendingTrials
        ];

        if ('upgrade' == $type) {
            $templateData['userIpsSample'] = $this->getHelper()->getUserPorts([], [], 5);
        }

        return $this->app['twig']->render('checkout/checkout.html.twig', $templateData);
    }

    public function continueBilling(Request $request) {
        $serviceId = $request->get('serviceid');
        if(!ctype_digit($serviceId)) {
            $response = ['result' => 'error'];
        } else
            $response = $this->app[ 'integration.whmcs.plugin' ]->removeCancellationRequest(
                $this->getUser()->getDetails('whmcsId'),
                $serviceId
            );

        return new JsonResponse($response);
    }

    public function quickBuy($country = false, $category = false, Request $request)
    {


        if($this->app[ 'session' ]->get('addorder.details')){

            $additional = $this->app[ 'session' ]->get('addorder.details');

            print_r($additional);
        }



        if (!$country and $this->request->query->get('country')) {
            $country = $this->request->query->get('country');
        }
        if (!$category and $this->request->query->get('category')) {
            $category = $this->request->query->get('category');
        }

        if ($this->getUser()->isAuthorized()) {
            return $this->redirectToRoute('checkout', ['country' => $country, 'category' => $category]);
        }

        if ('dedicated' == $country) {
            $country = 'static';
        }

        try {
            $geo = Util::getIpInfo($request->getClientIp());
        }
        catch (\Exception $e) {
            $geo = [];
        }

        if ($this->app['config.maintenance.login']) {
            $this->addFlashError($this->app['config.maintenance.message']);
        }

        return $this->app['twig']->render('checkout/quick-buy.twig', [
            'data'           => array_replace_recursive([
                'plan'     => array_filter([
                    'country'  => $country,
                    'category' => $category,
                    'amount'   => $request->get('quantity'),
                    'billingCycle' => $this->request->get('billingCycle'),
                    'showTrials' => $this->request->get('trial')
                ]),
                'city'     => Arrays::get($geo, 'city'),
                'state'    => Arrays::get($geo, 'region'),
                'country'  => Arrays::get($geo, 'country.code'),
                'postcode' => Arrays::get($geo, 'zip'),
                'email'    => $request->get('email'),
            ], $request->query->all(), $request->request->all()),
            'initialRequest' => true,
            'available'      => array_values(array_filter(array_map(function(array $product) {
                // Ignore IPv6 stuff
                if (!$product[ 'meta' ][ 'country' ] or !$product[ 'meta' ][ 'category' ]) {
                    return false;
                }

                return [
                    'country'        => $product[ 'meta' ][ 'country' ],
                    'category'       => $product[ 'meta' ][ 'category' ],
                    'countBounds'    => $this->getCountBounds($product[ 'meta' ][ 'country' ], $product[ 'meta' ][ 'category' ]),
                    'trial'          => $product[ 'trial' ],
                    'trialQuantity'  => $product['trialQuantity'],
                ];
            }, $this->getPricingTiers()))),
            'countries' => Util::getCountriesList()
        ]);
    }

    public function continueQuickBuy()
    {
        if ($this->getUser()->getSession()->get('tfa.checkoutData')) {
            return $this
                ->disableCaptchaOnTheNextCheck()
                ->redirectPost($this->getUrl('do_quick_buy', []),
                unserialize($this->getUser()->getSession()->get('tfa.checkoutData')),
                'Processing, please wait...'
            );
        }

        return $this->redirectToRoute('quick_buy_empty');
    }

    public function doQuickBuy(Request $request)
    {
        if ($this->app['config.maintenance.login']) {
            return $this->redirectToRoute('quick_buy_empty');
        }

        try {
            if(!$this->validateCaptcha()){
                throw new ErrorException('Captcha not valid, please try again');
            }


            if (!$request->get('email') or !filter_var($request->get('email'), FILTER_VALIDATE_EMAIL)) {
                throw new ErrorException('You did not enter your email or it is invalid');
            }

//            if (!$request->get('plan') or
//                empty($request->get('plan')['country'][0]) or
//                empty($request->get('plan')['category'][0]) or
//                empty($request->get('plan')['amount'][0])) {
//                throw new ErrorException('No plan/amount selected');
//            }

            // Check if email is already registered
            try {
                $response = $this->app['integration.whmcs.api']->api('getclientsdetails', ['email' => $request->get('email')]);
            }
            catch (ErrorException $e) {}
            if (!empty($response['id'])) {
                throw new ErrorException('Error: A user already exists with that email address');
            }

            if ($this->getUser()->getTFA()) {
                $this->getUser()->getTFA()->setStrategy(TFA::VALIDATE_STRATEGY_IP_REQUIRED);

                if (!$this->getUser()->getTFA()->isValidated($request->get('email'))) {
                    if ($this->getUser()->isAuthorized()) {
                        $this->getUser()->deauthorize();
                    }

                    $this->getUser()->getSession()->set('tfa.requiredVerification', 1);
                    $this->getUser()->getSession()->set('tfa.userKey', $request->get('email'));
                    $this->getUser()->getSession()->set('tfa.checkoutData', serialize($request->request->all()));
                    $this->getUser()->getSession()->set('tfa.redirect', $this->getUrl('quick_buy_continue_tfa', []));

                    return $this->redirectToRoute('tfa');
                }
            }

            $userData = [
                'email' => $request->get('email'),
                'password2' => $request->get('password'),

                'firstname' => $request->get('firstname'),
                'lastname' => $request->get('lastname'),
                'phonenumber' => $request->get('phone'),

                'companyname' => $request->get('company'),
                'address1' => $request->get('address'),
                'city' => $request->get('city'),
                'state' => $request->get('state'),
                'postcode' => $request->get('postcode'),
                'country' => $request->get('country'),
                'customfields' => base64_encode(serialize([
                    'Birthday' => $request->get('birthday')
                ]))
            ];

            if (!$userData['firstname']) {
                $userData['firstname'] =
                    ucwords(str_replace(['.', '+'], ' ', Arrays::get(explode('@', $userData['email']), 0, '')));
            }
            if (!$userData['lastname'] and false !== strpos($userData['firstname'], ' ')) {
                $userData['lastname'] = substr($userData['firstname'], strpos($userData['firstname'], ' ') + 1);
                $userData['firstname'] = substr($userData['firstname'], 0, strpos($userData['firstname'], ' '));
            }
            if (empty($userData['city']) or empty($userData['state']) or empty($userData['country'])) {
                $geo = Util::getIpInfo($request->getClientIp());

                foreach ([
                    'city'     => 'city',
                    'state'    => 'region',
                    'country'  => 'country.code',
                    'postcode' => 'zip'
                ] as $map => $key) {
                    if (empty($userData[$map]) and Arrays::get($geo, $key)) {
                        $userData[$map] = Arrays::get($geo, $key);
                    }
                }
            }

            foreach ([
                'lastname' => '-',
                'phonenumber' => '(-)',
                'address1' => '(no address)',
                'postcode' => '00000'
            ] as $field => $default) {
                if (!$userData[$field]) {
                    $userData[$field] = $default;
                }
            }

            $response = $this->app['integration.whmcs.api']->api('addclient', $userData);

            if (empty($response['result']) or 'success' != $response['result']) {
                throw new ErrorException('Error: ' .
                    (!empty($response['message']) ? $response['message'] : json_encode($response)));
            }

            $userId = $response['clientid'];

            if (!$userId) {
                throw new ErrorException('Error: ' . json_encode($response));
            }

            // Add user to db
            $user = $this->getApi()->userManagement()->upsertUser('whmcs', $userId, $userData['email']);
            if (empty($user['userId'])) {
                throw new ErrorException('Unable to create user account');
            }
            $this->getUser()->authorizeById($user['userId']);
            if ($this->getUser()->getTFA()) {
                // delete not needed old otp (otp for email verification on sign up)
                $this->getApi()->mta()->deleteAllUserIps($request->get('email'));

                $this->getUser()->getTFA()->setTFAValidated($user[ 'userId' ]);
            }

            // Migrate customer if needed
            try {
                $packageExists = !!$this->getApi()->packages()->getByAttributes(
                    PackageEntity::construct()
                        ->setCountry($request->get('plan')[ 'country' ][ 0 ])
                        ->setCategory($request->get('plan')[ 'category' ][ 0 ])
                );
            }
            catch (BadRequestException $e) {
                $packageExists = false;
            }
            if (!$packageExists) {
                $returnUrl = $this->getUrl('checkout_process',
                    ['plan' => $request->get('plan'), 'new' => 1, 'details' => $request->get('details')]);
            }
            else {
                $returnUrl = $this->getUrl('checkout',
                    [
                        'country' => $request->get('plan')['country'][0],
                        'category' => $request->get('plan')['category'][0],
                        'quantity' => $request->get('plan')['amount'][0]
                    ]);
            }


            // User created at this point
            return $this->app['integration.whmcs.plugin.auth']->authorize($userData['email'], $userData['password2'],
                $this->getUrl("dashboard",[]), null, 'Processing, please wait...');
        }
        catch (ErrorException $e) {
            $this->addFlashError($e->getMessage());

            return $this->app['twig']->render('checkout/quick-buy.twig', [
                'data'         => array_merge(array_filter($request->request->all()), [
                    'plan'    => [
                        'country'  => Arrays::get($request->request->all(), 'plan.country.0'),
                        'category' => Arrays::get($request->request->all(), 'plan.category.0'),
                        'amount'   => Arrays::get($request->request->all(), 'plan.amount.0'),
                        'billingCycle' => Arrays::get($request->request->all(), 'plan.billingCycle'),
                    ],
                    'details' => [
                        'promocode' => Arrays::get($request->request->all(), 'details.promocode'),
                        'trial' => Arrays::get($request->request->all(), 'details.trial')
                    ]
                ]),
                'available'    => array_map(function(array $product) {
                    return [
                        'country'        => $product[ 'meta' ][ 'country' ],
                        'category'       => $product[ 'meta' ][ 'category' ],
                        'countBounds'    => $this->getCountBounds($product[ 'meta' ][ 'country' ], $product[ 'meta' ][ 'category' ]),
                        'trial'          => $product[ 'trial' ],
                        'trialQuantity'  => $product['trialQuantity'],
                    ];
                }, $this->getPricingTiers()),
                'countries' => Util::getCountriesList()
            ]);
        }
    }

    public function doCheckout(Application $app, Request $request) {
        $step = $request->get('step', 'start');

        if ('start' == $step) {
            // Prepare order data
            $planUnformed = $request->get('plan');
//            $details = $this->reformPlanDetails($planUnformed['country'], $planUnformed['category'], $planUnformed['amount']);
            $details['billingCycle'] = $planUnformed['billingCycle'];
            $additional = $request->get('details');
            $additional['_info'] = [];

            // Restrict customer from upgrading package from another source
            if (!empty($details[0])) {
                try {
                    $package = $this->getApi()->packages()->getByAttributes(
                        PackageEntity::construct()
                            ->setCountry($planUnformed[ 'country' ][ 0 ])
                            ->setCategory($planUnformed[ 'category' ][ 0 ])
                    );

                    // skip preserve ips for rotating proxies
                    if (in_array($package['item']['category'], ['sneaker', 'dedicated', 'semi-3'])) {
                        if ($details[0]['amount'] < $package['item']['ports']) {
                            $additional['_info']['downgrade'] = true;
                        }
                    }
                    $additional['_info']['realamount'] = $package['item']['ports'];

                    if ('whmcs' != $package['item']['source']) {
                        return $this
                            ->addFlashError("Upgrading/calcelling package for source \"{$package['item']['source']}\" is prohibited")
                            ->redirectToRoute('checkout');
                    }
                }
                catch (BadRequestException $e) {
                    // Listen only if package not found (new)
                    if ('NOT_FOUND' != $e->getErrorCode()) {
                        throw $e;
                    }
                }
            }

            // Validate pricing tiers

            if ($details[0]['amount'] > 0) {
                $countBounds = $this->getCountBounds($details[ 0 ][ 'country' ], $details[ 0 ][ 'category' ]);
                // Min quantity
                if ($details[ 0 ][ 'amount' ] < $countBounds[ $details['billingCycle'] ][ 'min' ]) {
                    return $this
                        ->addFlashError('Minimal amount is: ' . $countBounds[ $details['billingCycle'] ]['min'])
                        ->redirectToRoute('checkout');
                }
                if ($details[ 0 ][ 'amount' ] > $countBounds[ $details['billingCycle'] ][ 'max' ]) {
                    return $this
                        ->addFlashError('Max amount is: ' . $countBounds[ $details['billingCycle'] ]['max'])
                        ->redirectToRoute('checkout');
                }
                // No price found
                if (false === $this->getPrice($details[ 0 ][ 'country' ], $details[ 0 ][ 'category' ],
                        $details[ 0 ][ 'amount' ], $details['billingCycle'])
                ) {
                    return $this
                        ->addFlashError('Purchase of this package is prohibited')
                        ->redirectToRoute('checkout');
                }
            }

            // For existent order no need to get affiliate
            if (!$request->get('new')) {
                $step = 'complete';
            }
            else {
                $this->app['session']->set('checkout.details', $details);
                $this->app['session']->set('checkout.additional', $additional);

                $response = $app[ 'integration.whmcs.plugin' ]->getAffiliateIdCallback(
                    $this->getUrl('checkout_process', ['step' => 'affiliate']));

                if ($response instanceof Response) {
                    return $response;
                }
                elseif ($response instanceof Request) {
                    return $this->app->handle($response, HttpKernel::SUB_REQUEST);
                }
            }
        }

        elseif ('affiliate' == $step) {
            if ($request->get('error') and $request->get('alert')) {
                return $this->addFlashError($request->get('message'))->redirectToRoute('checkout');
            }
            elseif($request->get('error')) {
                throw new ErrorException('Error occured, information - ' . $request->get('message'));
            }

            $details = $this->app[ 'session' ]->get('checkout.details');
            $additional = $this->app[ 'session' ]->get('checkout.additional');
            $this->app[ 'session' ]->remove('checkout.details');
            $this->app[ 'session' ]->remove('checkout.additional');
            $affiliateId = (int) $request->get('id');
            $step = 'complete';
        }

        if ('complete' == $step and !empty($details)) {
            if ($details[0]['amount']) {
                try {
                    if(isset($additional['_info']['downgrade']) && !empty($additional['preservedips'])) {
                        $ips = explode(';', $additional['preservedips']);
                        foreach($ips as $ip) {
                            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                                return $this->addFlashError('Not valid ip\'s specified.')->redirectToRoute('checkout');
                            }
                        }

                        if(count($ips) > $details[0]['amount'])
                            return $this->addFlashError('You can only select ' .
                                $details[0]['amount'] . ' ip\'s to keep after downgrade')->redirectToRoute('checkout');
                        try {
                            if($this->getApi()->ports4()->setPreservedPorts($ips)['status'] !== 'ok') {
                                throw new ErrorException('Failed to preserve selected ips from downgrade');
                            }
                        }
                        catch (BadRequestException $e) {
                            throw new InvalidArgumentException($e->getMessage());
                        }
                    }
                    $customOptions = [];
                    if (!empty($additional['trial'])) {
                        $customOptions['trial'] = true;
                    }

                    if (!empty($details['billingCycle']) && empty($additional['trial'])) {
                        $customOptions['billingCycle'] = $details['billingCycle'];
                    }

                    $orderInformation = $app[ 'integration.whmcs.plugin' ]->updatePackage(
                        $this->getUser()->getDetails('whmcsId'),
                        $details[ 0 ][ 'productId' ],
                        $details[ 0 ][ 'amount' ],
                        $this->getUrl('dashboard', ['paid' => 1]),
                        $this->getUrl('dashboard', []),
                        $this->getUrl('callback_whmcs', ['userWhmcsId' => $this->getUser()->getDetails('whmcsId')]),
                        !empty($affiliateId) ? $affiliateId : null,
                        !empty($additional[ 'promocode' ]) ? $additional[ 'promocode' ] : null,
                        $customOptions
                    );
                }
                catch (InvalidArgumentException $e) {
                    return $this->addFlashError($e->getMessage())->redirectToRoute('checkout');
                }

                if (!empty($orderInformation['invoiceId'])) {
                    return new RedirectResponse($orderInformation['invoiceUrl']);
                }
                elseif (!empty($orderInformation['noInvoice'])) {
                    if (!empty($customOptions['trial'])) {
                        return $this->redirectToRemoteRoute('whmcsPPBA', ['redirectToDashboard' => 1]);
                    } else {
                        return $this->redirectToRoute('checkout');
                    }
                }
                else {
                    throw new ErrorException('Error occured, information - ' . json_encode($orderInformation));
                }
            }
            else {
                $reason = $request->request->get('cancel-reason');
                if(mb_strlen($reason) < 20)
                    $this->addFlashError('Cancel reason requires at least 20 symbols.');
                else if($request->request->get('serviceid')) {
                    $reason = mb_substr($reason, 0, 800);
                    $email = $this->getUser()->getDetails('email');
                    if(!$this->app['util.helper']->sendEmail(
                        $this->app['config.support.email'],
                        "We received your request to cancel " .
                            (isset($additional['_info']['realamount']) ? $additional['_info']['realamount'] : '?') .
                            " proxies\n\n",
                        "$reason",
                        'cancellations@blazingseollc.com',
                        'php_mail',
                        $email
                    )) {
                        $this->addFlashError('Sorry, it looks like there was an error cancelling your service. ' .
                            'Please contact our support team at ' .
                            "<a href=\"mailto:{$this->app['config.support.email']}\">{$this->app['config.support.email']}</a>");
                    } else {
                        $orderInformation = $app[ 'integration.whmcs.plugin' ]->cancelPackageAtTheEndOfTheBilling(
                            $this->getUser()->getDetails('whmcsId'),
                            $request->request->get('serviceid'),
                            $reason
                        );

                        // some issue with WHMCS, SubscriptionId is missing, but cancellation request added
                        if($orderInformation['result'] !== 'success' && $orderInformation['message'] !== 'Required value SubscriptionID Missing') {
                            $this->addFlashError($orderInformation['message']);
                        } else
                            $this->addFlashSuccess('Your cancellation request accepted. Your proxies will remain active until the end of the billing period.<br>' .
                             'If you wish to remove this cancellation request, please click "Continue billing"');
                    }
                }

                return $this->redirectToRoute('checkout');
            }
        }

        throw new ErrorException('A wrong workflow', 400);
    }

    public function callbackWhmcs(Request $request)
    {
        $userId = $request->get('userId');
        $userWhmcsId = $request->get('whmcsUserId') or $userWhmcsId = $request->get('userWhmcsId');

        if (!empty($forceSync = $request->get('forceSync'))
            && isset($forceSync['country'])
            && isset($forceSync['category'])
        ) {
            $response = $this->app['integration.proxy.api']->portsSync($userId, $forceSync['country'], $forceSync['category']);
            return new JsonResponse([
                'status' => 'success'
            ]);
        }

        if (!$userId and !$userWhmcsId) {
            return new JsonResponse([
                'status' => 'error',
                'text' => 'No user id passed'
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!$userId and $userWhmcsId) {
            try {
                $userId = $this->getApi()->userManagement()->getUserByBillingId('whmcs', $userWhmcsId)['userId'];
            }
            catch (BadRequestException $e) {}
        }

        if ($userId) {
            $this->log->addSharedIndex('userId', $userId);
            $user = $this->getApi()->user()->getDetails($userId);
        }

        if (empty($user)) {
            return new JsonResponse([
                'status' => 'error',
                'text' => 'No valid user found'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->app[ 'db_helper' ]->refreshUserPlans($user[ 'userId' ]);
            $synced = $this->app[ 'db_helper' ]->getLastRefreshDetails();
        }
        catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'text' => $e->getMessage()
            ], 500);
        }

        foreach (['updated', 'added', 'deleted'] as $key) {
            if (!empty($synced[$key]['count'])) {
                foreach ($synced[$key]['sets'] as $i => $set) {
                    $synced[$key]['sets'][$i]['syncLog'][] =
                        $this->app['integration.proxy.api']->portsSync($user['userId'], $set['country'], $set['category']);
                }
            }
        }

        if($synced['added']['count'] !== 0) {
            $res = $this->app['integration.whmcs.api']->api('GetClientsDetails', ['clientid' => $userWhmcsId]);

            if(($ip = Util::extractIpFromWhmcsLoginInfo($res['lastlogin'])) != false) {
                $this->registerClientIpIfEmptyIpList($ip, $userId);
            } // else if(Util::extractIpv6FromWhmcsLoginInfo($res['lastlogin']) != false) {
              //  $this->getApi()->user()->updateSettings(['authType' => 'PW'], $userId);
            // }
        }

        return new JsonResponse([
            'status' => 'success',
            'details' => $synced
        ]);
    }

    public function total(Request $request) {
        $planUnformed = $request->get('plan');

        $total = $this->getTotal(
            $planUnformed['country'],
            $planUnformed['category'],
            $planUnformed['amount'],
            $planUnformed['billingCycle'],
            Arr::getOrElse($request->get('details', []), 'promocode')
        );

        return new JsonResponse($total, 200);
    }

    public function getTotal($country, $category, $amount, $billingCycle, $promocode = null)
    {
        $plan = $this->reformPlan($country, $category, $amount);

        $total = 0;
        $discount = 0;

        foreach($plan as $country => $categories) {
            foreach($categories as $category => $amount) {
                if ('static' == $category) {
                    $category = 'dedicated';
                }

                try {
                    $data = $this->app[ 'integration.whmcs.plugin' ]->calculateTotal(
                        $this->getDetails($country, $category, $amount)['productId'],
                        $amount,
                        $this->getUser()->getDetails('whmcsId'),
                        $promocode,
                        $billingCycle
                    );
                    $total = $data['total'];
                    $discount = $data['discount'];

                    if (!empty($data['isPromoValid'])) {
                        $promoValid = true;
                    }
                }
                catch (ErrorException $e) {}
                catch (RuntimeException $e) {}
            }
        }

        return [
            'total'      => $total,
            'discount'   => $discount,
            'trial'      => !empty($data['trial']) ? $data['trial'] : 0,
            'trialNotAllowedReason' => !empty($data['trialNotAllowedReason']) ? $data['trialNotAllowedReason'] : '',
            'promoValid' => !empty($promoValid),
            'promoData'  => !empty($data[ 'promoData' ]) ? $data[ 'promoData' ] : []
        ];
    }



    public function validatePromocode(Request $request)
    {
        try {
            $code = Arr::getOrElse($request->get('details', []), 'promocode');

            if (!$code) {
                throw new ErrorException('No code is passed');
            }

            $planUnformed = $request->get('plan');
            $plan = $this->reformPlan($planUnformed['country'], $planUnformed['category'], $planUnformed['amount']);

            $isValid = false;
            foreach($plan as $country => $categories) {
                foreach($categories as $category => $amount) {
                    if ('static' == $category) {
                        $category = 'dedicated';
                    }

                    $data = $this->app[ 'integration.whmcs.plugin' ]->calculateTotal(
                        $this->getDetails($country, $category, $amount)['productId'],
                        $amount,
                        $this->getUser()->getDetails('whmcsId'),
                        $code
                    );

                    if (!empty($data['isPromoValid'])) {
                        $isValid = true;
                    }
                }
            }

            if ($isValid) {
                return new JsonResponse(['result' => 'success', 'promotion' => []]);
            }
            else {
                return new JsonResponse(['result' => 'fail', 'reason' => '']);
            }
        }
        catch (ErrorException $e) {
            return new JsonResponse(['result' => 'fail', 'reason' => $e->getMessage()]);
        }
    }

    public function changeBillingCycle(Request $request)
    {
        /*$whmcsServiceId = $request->get('serviceId');
        $billingCycle = $request->get('billingCycle');
        $userId = $this->getUser()->getDetails('whmcsId');

        if (!$userId || !$billingCycle) {
            return new JsonResponse(['result' => 'fail', 'reason' => 'Operation not allowed']);
        }

        try {
            $data = $this->app['integration.whmcs.plugin']->changeBillingCycle($userId, $whmcsServiceId, $billingCycle);
        } catch (\Exception $e) {
            return new JsonResponse(['result' => 'fail']);
        }

        if ($data['result'] == 'ok') {
            return new JsonResponse(['result' => 'success']);
        } else {
            return new JsonResponse(['result' => 'fail']);
        }*/
    }

    private function reformPlan($countries, $categories, $amounts) {
        $plan = [];

        if (count($countries) != count($categories) || count($countries) != count($amounts)) {
            throw new \Exception('Plan Counts Don\'t Match');
        }

        foreach ($countries as $idx => $country) {
            $country = strtolower($country);
            $category = strtolower($categories[$idx]);
            $amount = (int)$amounts[$idx];
            if ($country && $category /*&& $amount*/) {
                $plan[$country][$category] = (isset($plan[$country][$category])) ? $plan[$country][$category] + $amount : $amount;
            }
        }
        return $plan;
    }

    private function getPrice($country, $category, $amount, $billingCycle) {

        foreach ($this->getPricingTiers() as $product) {
            if ($country == $product['meta']['country'] and $category == $product['meta']['category']) {
                foreach ($product['pricingTiersForCycles'][$billingCycle]['tiers'] as $tier) {
                    if ($tier['from'] <= $amount and $amount <= $tier['to']) {
                        return $tier['price'] * $amount;
                    }
                }
            }
        }

        return false;
    }

    private function getCountBounds($country, $category)
    {
        $countBounds = [];
        foreach ($this->getPricingTiers() as $product) {
            if ($country == $product['meta']['country'] and $category == $product['meta']['category']) {
                foreach ($product['pricingTiersForCycles'] as $billingCycle => $pricingTiersForCycle) {
                    $min = 0;
                    $max = 0;
                    foreach ($pricingTiersForCycle['tiers'] as $tier) {
                        if (!$min) {
                            $min = $tier['from'];
                        }

                        if ($tier['from'] < $min) {
                            $min = $tier['from'];
                        }

                        if ($tier['to'] > $max) {
                            $max = $tier['to'];
                        }
                    }

                    $countBounds[$billingCycle] = [
                        'min' => max($min, 1),
                        'max' => $max,
                        'step' => 1
                    ];
                }
            }
        }

        return $countBounds;
    }

    private function getPricingTiers()
    {
        $response = $this->app['integration.whmcs.plugin']->getPricingTiers();

        if (empty($response['pricing'])) {
            throw new ErrorException('No pricing tiers received, response - ' . json_encode($response));
        }

        return $response['pricing'];
    }

    private function reformPlanDetails($countries, $categories, $amounts) {
        $reformed = [];
        $plan = $this->reformPlan($countries, $categories, $amounts);
        foreach($plan as $country => $categories) {
            foreach($categories as $category => $amount) {
                $reformed[] = $this->getDetails($country, $category, $amount);
            }
        }
        return $reformed;
    }

    private function getDetails($country, $category, $amount) {
        if ('static' == $category) {
            $category = 'dedicated';
        }

        $response = $this->app['integration.whmcs.plugin']->getPricingTiers();

        if (empty($response['pricing'])) {
            throw new ErrorException('No pricing tiers received, response - ' . json_encode($response));
        }

        $pricingTiers = $response['pricing'];

        foreach ($pricingTiers as $product) {
            if ($country == $product['meta']['country'] and $category == $product['meta']['category']) {
                return [
                    'productId' => $product['productId'],
                    'country' => $product['meta']['country'],
                    'category' => $product['meta']['category'],
                    'amount' => $amount
                ];
            }
        }

        throw new ErrorException('Not found category or country!');
    }

    private function registerClientIpIfEmptyIpList($ip, $userId) {
        if(count($this->getApi()->user()->getAuthIpList($userId)['list']) === 0)
            $this->getApi()->user()->addAuthIp($ip, $userId);
    }
}

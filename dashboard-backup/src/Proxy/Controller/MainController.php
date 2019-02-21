<?php

namespace Proxy\Controller;

use Axelarge\ArrayTools\Arr;
use Blazing\Common\RestApiRequestHandler\Exception\BadRequestException;
use Blazing\Reseller\Api\Api\Entity\PackageEntity;
use ErrorException;
use Proxy\Util\TFA;
use Silex\Application;
use Symfony\Component\HttpFoundation\JsonResponse;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\Routing\Generator\UrlGenerator;

class MainController extends AbstractController
{


    public function getPromocode(Request $request){

//        die();
        try {
//            $code = Arr::getOrElse($request->get('details', []), 'promocode');

            $code = $this->request->get('promocode');
            if (!$code) {
                throw new ErrorException('No code is passed');
            }


            $data = $this->app[ 'integration.whmcs.api' ]->getPromotions(
                $code
            );

            return new JsonResponse($data);

        }
        catch (ErrorException $e) {
            return new JsonResponse(['result' => 'fail', 'reason' => $e->getMessage()]);
        }
    }


    public function index() {
        if ($this->getUser()->isAuthorized()) {
            return $this->redirectToRoute('dashboard');
        }

        return $this->app['twig']->render('main/index.html.twig');
        return $this->redirectToRoute("loginType");
    }

    public function loginDetermine()
    {
        if ($this->getUser()->isAuthorized()) {
            return $this->redirectToRoute('dashboard');
        }

        $step = $this->request->get('step', 'start');

        // Get whmcs id
        while ($step) {
            switch ($step) {
                case 'start':
                    // if ('whmcs' == $this->request->cookies->get('auth_type')) {
                    //     return $this->redirectToRemoteRoute('login');
                    // }

                    $response = $this->app[ 'integration.whmcs.plugin.auth' ]->isAuthorized(
                        $this->getUrl('loginDetermine', ['step' => 'whmcsId']));

                    if ($response instanceof Response) {
                        return $response;
                    }
                    elseif ($response instanceof Request) {
                        return $this->app->handle($response, HttpKernel::SUB_REQUEST);
                    }
                    break;

                case 'whmcsId':
                    // Authorized at WHMCS, let use it
                    if ($this->request->get('id')) {
                        return $this->redirectToRoute('login');
                    }

                    if (!empty($this->app['config.proxyOldUrls']) and !empty($this->app['amember.url'])) {
                        $step = 'amemberId';
                    }
                    else {
                        $step = false;
                    }

                    break;

                case 'amemberId':
                    $urlId = $this->request->get('urlId', -1);
                    $urls = explode(', ', $this->app['config.proxyOldUrls']);

                    // Was or is authorized in amember
                    if ($urlId >= 0 and $this->request->get('id')) {
                        // Check if user has account in whmcs
                        // try {
                        //   $user = $this->getApi()->user()->getDetails($this->request->get('id'));
                        //   // Has whmcs account as well, redirect to it
                        //   if (!empty($user['whmcsId'])) {
                        //       return $this->redirectToRoute('login');
                        //   }
                        // }
                        // catch (BadRequestException $e) {}

                        return new RedirectResponse($urls[$urlId]);
                    }

                    // Try first url
                    if (-1 == $urlId) {
                        $url = $urls[0];
                    }
                    // Try next url
                    elseif (!empty($urls[$urlId + 1])) {
                        $url = $urls[$urlId + 1];
                    }
                    // No urls left to try, nothing to do
                    else {
                        break 2;
                    }

                    return new RedirectResponse("$url/api_callback.php?" . http_build_query(
                        ['method' => 'getUserId', 'callback' => $this->getUrl('loginDetermine', [
                            'step' => 'amemberId',
                            'urlId' => $urlId + 1
                        ])]
                    ));
                    break;
            }
        }

        return $this->redirectToRoute('loginType');
    }

    public function loginType()
    {
        if ($this->getUser()->isAuthorized()) {
            return $this->redirectToRoute('dashboard');
        }

        if ($this->app['config.maintenance.login']) {
            $this->addFlashError($this->app['config.maintenance.message']);

            return $this->app[ 'twig' ]->render('main/index_sign_in.html.twig', [
                'login'   => $this->request->get('login'),
                'options' => $this->request->get('opt')
            ]);
        }

        if ($this->request->isMethod('post')) {
            $login = $this->request->get('login');
            $password = $this->request->get('password');

            try {
                $this->validateCaptcha();
            }
            catch (ErrorException $e) {
                return $this
                    ->addFlashError('Captcha is not solved')
                    ->redirectToRoute('loginType', ['login' => $login, 'opt' => $this->request->get('opt')]);
            }

            // Determine source

            if (!$login) {
                return $this
                    ->addFlashError("No login/email field passed")
                    ->redirectToRoute('loginType');
            }


            // Check the pass again
            $response = $this->app['integration.whmcs.api']->api('ValidateLogin', [
                'email'     => $login,
                'password2' => $password
            ]);

            // Password is valid
            if (!empty($response['result']) and 'success' == $response['result']) {

                return $this->app['integration.whmcs.plugin.auth']
                    ->authorize($login, $password, $this->getUrl('login', []), null, 'Signing In...');
            } else {
                return $this
                    ->addFlashError("Unknown login/email \"$login\" or wrong password. Please check if input is correct")
                    ->redirectToRoute('loginType', ['login' => $login, 'opt' => 0]);
            }

            //----------
            try {
                $user = $this->getApi()->userManagement()->findUserByLoginOrEmail($login);
            }
            catch (BadRequestException $e) {
                $response = $this->app['integration.whmcs.api']->api('getclientsdetails', ['email' => $login]);
                if (!empty($response['id'])) {
                    try {
                        // Email is change is what we know
                        $user = $this->getApi()->userManagement()->getUserByBillingId('whmcs', $response[ 'id' ]);
                        $user[ 'email' ] = $response[ 'email' ];
                    }
                    catch (\Exception $e) {
                        // New user, so let's create his account right after login
                        $user = [
                            'userId' => 0,
                            'email' => $login,
                            'whmcsId' => $response['id'],
                            'login' => ''
                        ];
                    }
                }

                if (empty($user)) {
                    return $this
                        ->addFlashError("Unknown login/email \"$login\" or wrong password. Please check if input is correct")
                        ->redirectToRoute('loginType', ['login' => $login, 'opt' => 1]);
                }
            }

            if (!empty($user['userId'])) {
                $this->log->addSharedIndex('userId', $user['userId']);
            }

            $hasWhmcs = !empty($user['whmcsId']);
            if (!$hasWhmcs) {
                $response = $this->app['integration.whmcs.api']->api('getclientsdetails', ['email' => $user[ 'email' ]]);
                $hasWhmcs = !empty($response['id']);
                // Consider user has whmcs id
                if ($hasWhmcs) {
                    $user['whmcsId'] = $response['id'];
                }
            }

            // Has WHMCS account, sign him in
            if ($hasWhmcs) {
                $response = $this->app['integration.whmcs.api']->api('ValidateLogin', [
                    'email'     => $user[ 'email' ],
                    'password2' => $password
                ]);
                // Password is valid
                if (!empty($response['result']) and 'success' == $response['result']) {
                    return $this->app['integration.whmcs.plugin.auth']
                        ->authorize($user[ 'email' ], $password, $this->getUrl('login', []), null, 'Signing In...');
                }

                // Check if email was changed earlier
                elseif (!empty($user['whmcsId'])) {
                    $response = $this->app['integration.whmcs.api']->api('getclientsdetails', ['clientid' => $user[ 'whmcsId' ]]);
                    if (!empty($response['email']) and $user['email'] != $response['email']) {
                        $user['email'] = $response['email'];

                        // Check the pass again
                        $response = $this->app['integration.whmcs.api']->api('ValidateLogin', [
                            'email'     => $user[ 'email' ],
                            'password2' => $password
                        ]);
                        // Password is valid
                        if (!empty($response['result']) and 'success' == $response['result']) {
                            return $this->app['integration.whmcs.plugin.auth']
                                ->authorize($user[ 'email' ], $password, $this->getUrl('login', []), null, 'Signing In...');
                        }
                    }

                }

                // User has no whmcs packages yet, ok check if he needs password copy from AMember into WHMCS
                if (isset($this->app['config.proxyOldUrls']) and isset($this->app['config.amember.url'])) {
                    $response = $this->app[ 'integration.amember.api' ]->verifyUserPass($user[ 'login' ], $password);
                    if ($response[ 'success' ]) {
                        $responseProducts = $this->app[ 'integration.whmcs.api' ]->api('GetClientsProducts',
                            ['clientid' => $user[ 'whmcsId' ]]);

                        // Empty account, ok. Handle it
                        if (!empty($responseProducts[ 'result' ]) and 'success' == $responseProducts[ 'result' ]) {
                            $cleanAccount = empty($responseProducts[ 'products' ][ 'product' ]);

                            // User has some products, but they can be all ignored
                            if (!$cleanAccount) {
                                $ignoreProducts = [];
                                foreach ($this->app[ 'config.whmcs.ignore_on_auth_groups' ] as $groupId) {
                                    $response = $this->app[ 'integration.whmcs.api' ]->api('GetProducts',
                                        ['gid' => $groupId]);
                                    if (!empty($response[ 'products' ][ 'product' ])) {
                                        foreach ($response[ 'products' ][ 'product' ] as $product) {
                                            $ignoreProducts[] = $product[ 'pid' ];
                                        }
                                    }
                                }

                                // All products are in ignore groups
                                if (!array_filter($responseProducts[ 'products' ][ 'product' ],
                                    function (array $row) use ($ignoreProducts) {
                                        return !in_array($row[ 'pid' ], $ignoreProducts);
                                    })
                                ) {
                                    $cleanAccount = true;
                                }
                            }

                            // Update his password if he has no services (clean/generated account)
                            if ($cleanAccount) {
                                $response = $this->app[ 'integration.whmcs.api' ]->api('UpdateClient',
                                    ['clientid' => $user[ 'whmcsId' ], 'password2' => $password]);

                                if (!empty($response[ 'result' ]) and 'success' == $response[ 'result' ]) {
                                    return $this->app[ 'integration.whmcs.plugin.auth' ]
                                        ->authorize(
                                            $user[ 'email' ],
                                            $password,
                                            $this->getUrl('login', []),
                                            $this->getUrl('loginType', ['login' => $login]),
                                            'Signing In...'
                                        );
                                }
                            }
                        }
                    }
                }

                // Force and notice password is wrong if has WHMCS packages
                if (
                    empty($user[ 'userId' ]) or
                    $this->getApi()->packages()->getAll('whmcs', false, PackageEntity::IP_V_4, $user[ 'userId' ])[ 'list' ] or
                    !(isset($this->app['config.proxyOldUrls']) and isset($this->app['config.amember.url']))
                ) {
                    return $this
                        ->addFlashError("Unknown login/email \"$login\" or wrong password. Please check if input is correct")
                        ->redirectToRoute('loginType', ['login' => $login]);
                }

                // We've tried all available options, now leave job to amember handler
            }

            if ($user['amemberId']) {
                $response = $this->app['integration.amember.api']->verifyUserPass($user['login'], $password);

                // Password is valid
                if ($response['success']) {
                    return $this->redirectPost($this->app[ 'config.amember.url.authForm' ], [
                        'amember_login' => $user['login'],
                        'amember_pass'  => $password,
                        'amember_redirect_url' =>
                            trim(Arr::getOrElse(explode(',', $this->app['config.proxyOldUrls']), 0, ''))
                    ], 'Signing In...');
                }
                else {
                    return $this
                        ->addFlashError("Unknown login/email \"$login\" or wrong password. Please check if input is correct")
                        ->redirectToRoute('loginType', ['login' => $login]);
                }
            }

            return $this
                ->addFlashError("Sorry, but I'm unable to determine, please choose it manually")
                ->redirectToRoute('loginType', ['login' => $login, 'opt' => 1]);
        }

        return $this->app['twig']->render('main/index_sign_in.html.twig', [
            'login' => $this->request->get('login'),
            'options' => $this->request->get('opt')
        ]);
    }

    public function forgetPassword(Request $request) {
        if ($this->getUser()->isAuthorized()) {
            return $this->redirectToRoute('dashboard');
        }

        if($email = $request->request->get('email')) {
            try {

                if(!$this->validateCaptcha()){
                    throw new ErrorException('Captcha not valid, please try again');
                }



                try {
                    $res = $this->getApi()->userManagement()->findUserByLoginOrEmail($email);
                    $userId = $res['userId'];
                } catch (\Exception $e) {
                    $this->addFlashError("No client account was found with the email address you entered.");
                }
            }
            catch (ErrorException $e) {
                $this->addFlashError($e->getMessage());
            }

            if(isset($userId)) {
                if(empty($res['whmcsId'])) {
                    $this->addFlashError("Password reset is not supported for Amember account.");
                } else {
                    $secret = md5(random_bytes(16) . $userId);

                    $key = base64_encode($secret . ',' . $email);

                    $this->getApi()->mta()->deleteOtp('tfa_email_recoverpass', $userId); //? should delete?
                    $this->getApi()->mta()->storeOtp('tfa_email_recoverpass', $secret, 86400, 1, $userId);

                    $whmcsDetails = $this->app['integration.whmcs.api']->api(
                        'GetClientsDetails',
                        [
                            'clientid' => $res['whmcsId']
                        ]);

                    if($whmcsDetails['result'] === 'error') {
                        $this->addFlashError("Failed to get user details. Please, <a href='http://billing.blazingseollc.com/hosting/submitticket.php?step=2&amp;amp;deptid=4' target='_blank'>contact us</a>. <br> Details: " . $whmcsDetails['message']);
                    } else {
                        $fullName = $whmcsDetails['fullname'];

                        if ($this->app['util.helper']->sendEmail($whmcsDetails['email'], 'Password recovery', $this->app['twig']->render('emails/recover_password.html.twig', ['k' => $key, 'fullName' => $fullName]))) {
                            $this->addFlashSuccess('Validation Email has been sent');

                            return $this->app['twig']->render('main/password_reclink_sent.html.twig');
                        } else {
                            $this->addFlashError("Failed to sent email. Please, <a href='http://billing.blazingseollc.com/hosting/submitticket.php?step=2&amp;amp;deptid=4' target='_blank'>contact us</a>.");
                        }
                    }
                }
            }
        }

        return $this->app['twig']->render('main/forget_password.html.twig');
    }

    public function recoverPassword(Request $request) {
        if ($this->getUser()->isAuthorized()) {
            return $this->redirectToRoute('dashboard');
        }

        if(($userDetails = $this->validateUserOtp($request->get('k'))) !== false) {
            if($newPass = $request->request->get('newpw')) {
                if(mb_strlen($newPass) < 6)
                    $this->addFlashError('Your password too short! Must be at least 6 symbols.');
                else {
                    if(isset($userDetails['whmcsId'])) {
                        $resp = $this->app['integration.whmcs.api']->api(
                            'UpdateClient',
                            [
                                'clientid' => $userDetails['whmcsId'],
                                'password2' => $newPass
                            ]);

                        if($resp['result'] === 'success') {
                            $this->getApi()->mta()->deleteOtp('tfa_email_recoverpass', $userDetails['userId']);

                            $whmcsDetails = $this->app['integration.whmcs.api']->api(
                                'GetClientsDetails',
                                [
                                    'clientid' => $userDetails['whmcsId']
                                ]);

                            $fullName = $whmcsDetails['fullname'];

                            $this->app['util.helper']->sendEmail($whmcsDetails['email'], 'Password Reset Successful', $this->app['twig']->render('emails/password_reset_confirmatiom.html.twig', ['fullName' => $fullName]));

                            return new RedirectResponse($this->app['url_generator']->generate('passwordReset'));
                        }
                    }

                    $this->addFlashError('Failed to reset password! ' . $resp['error']);
                }
            }

            return $this->app['twig']->render('main/recover_password.html.twig', [
                'newPass' => $newPass
            ]);
        } else {
            return $this
                ->addFlashError("Sorry, but this link have been expired. Try again.")
                ->redirectToRoute('forgetPassword');
        }
    }

    public function passwordReset(Request $request) {
        return $this->app['twig']->render('main/password_reset.html.twig');
    }

    protected function validateUserOtp($key) {
        list($secret, $email) = explode(',', base64_decode($key));

        try {
            $userDetails = $this->getApi()->userManagement()->findUserByLoginOrEmail($email);
        } catch (\Exception $e) {
            return false;
        }

        $userId = $userDetails['userId'];
        try {
            $res = $this->getApi()->mta()->getOtp('tfa_email_recoverpass', true, $secret, true, $userId);
            return $userDetails;
        } catch (\Exception $e) {
            return false;
        }
    }

    ///public function

    public function tfa()
    {
        $redirect = $this->getUser()->getTFA()->getRedirectUrl();
        $nonce = $this->request->get('nonce');
        $userKey = $this->getUser()->getTFA()->getUserKey();
        $userId = $userEmail = null;
        if (ctype_digit((string) $userKey)) {
            $userId = $userKey;
        }
        else {
            $userEmail = $userKey;
        }

        $this->getUser()->getTFA()->setStrategy(TFA::VALIDATE_STRATEGY_IP_REQUIRED);

        $emailSubject = 'Please confirm your account';
        $emailBody = <<<EOB
You are receiving this email to verify your identity with Blazing SEO. To better protect your account from intrusion, we are requiring everyone to verify their email upon first signing in. After you have verified your email, you will no longer be prompted for this message on the same device.

Your code is: <strong>%s</strong>

Please insert this at your proxy dashboard to complete the process
Thank you for your understanding.

Sincerely,

Blazing SEO Support
EOB;

        try {
            // AMember legacy MTA support
            if (!$userKey) {
                $userIdMasked = $this->request->get('userId');
                $source = $this->request->get('source') == 'amember' ? $this->request->get('source') . '_' : '';
                $userId = $this->getConn()
                    ->executeQuery("SELECT id FROM proxy_users WHERE MD5({$source}id) = ?", [$userIdMasked])
                    ->fetchColumn();

                if (!$userId) {
                    $this->log->warn('User has not been found by masked id',
                        ['maskedId' => $userIdMasked, 'nonce' => $nonce]);

                    throw new ErrorException('User is not found');
                }

                $userKey = $userId;

                if ($this->getUser()->getTFA()->signToken($userId . $source) != $nonce) {
                    $this->log->warn('Bad nonce', ['nonce' => $nonce, 'userId' => $userIdMasked, 'source' => $source]);
                    throw new ErrorException('Bad request');
                }
            }

            if ($userId) {
                $this->log->addSharedIndex('userId', $userKey);
            }

            if ($userKey) {
                if ($this->getUser()->getTFA()->isValidated($userKey)) {
                    return new RedirectResponse($redirect .
                        (false === stripos($redirect, '?') ? '?' : '&') .
                        'token=' . $this->getUser()->getTFA()->signToken($nonce . $this->getUser()->getTFA()->getToken()) .
                        '&secret=' . $this->getUser()->getTFA()->getToken());
                }

                if ($this->request->isMethod('post')) {
                    $code = $this->request->get('code');

                    if (!$code) {
                        $this->addFlashError('No code is passed');
                        throw new ErrorException('No code is passed');
                    }

                    if (!$this->getUser()->getTFA()->validateTFAOTP($userKey, $code)) {
                        // Code is expired or has no more attempts?
                        $record = $this->getUser()->getTFA()->isOTPGenerated($userKey);
                        if ($this->getUser()->getTFA()->isCodeExist($userKey, $code) or
                            ($record and ($record['attempts'] <= 0))) {
                            $email = $userEmail ? $userEmail : $this->getApi()->user()->getDetails($userId)['email'];

                            $newOtp = false;
                            try {
                                $newOtp = $this->getApi()->mta()->getOtp('tfa_email', true, false, true, $userKey)['otp'];
                            } catch (\Exception $e) {}

                            if($newOtp) {
                                $this->log->warn('OTP code is not valid anymore.' .
                                    'New code generated.',
                                    ['code' => $code, 'newCode' => $newOtp['code'], 'record' => $record]);
                                throw new ErrorException(
                                    'The code you entered is no longer valid. You should have received a new code by e-mail. Please check your inbox to retrieve the valid code and try again.
                                    <br>
                                    If you continue to experience issues, please contact our support team by sending an email to support@blazingseollc.com');
                            }

                            $newCode = $this->getUser()->getTFA()->generateTFAOTP($userKey);
                            $this->app['util.helper']->sendEmail($email, $emailSubject, sprintf($emailBody, $newCode));
                            if ($record and $record['attempts'] <= 0) {
                                $this->log->warn('OTP code has no more attempts',
                                    ['code' => $code, 'newCode' => $newCode, 'record' => $record]);
                                throw new ErrorException("The verification code has no more attempts left, " .
                                    "so a new one has been just sent to your email \"$email\", " .
                                    "please check your inbox");
                            }
                            else {
                                $this->log->info('OTP code is expired',
                                    ['code' => $code, 'newCode' => $newCode, 'record' => $record]);
                                throw new ErrorException("The verification code has been expired already, " .
                                    "so the new one has been just sent to your email \"$email\", " .
                                    "please check your inbox");
                            }
                        }
                        else {
                            $this->log->notice('OTP code is wrong', ['code' => $code]);
                            throw new ErrorException('Code is wrong');
                        }
                    }

                    // Just validated
                    $token = $this->getUser()->getTFA()->setTFAValidated($userKey);

                    return new RedirectResponse(
                        $redirect .
                        (false === stripos($redirect, '?') ? '?' : '&') .
                        'token=' . $this->getUser()->getTFA()->signToken($nonce . $token) .
                        '&secret=' . $token
                    );
                }

                else {

                    // We should validate user
                    $email = $userEmail ? $userEmail : $this->getApi()->user()->getDetails($userId)['email'];



                    $resend = $this->request->get('resend');
                    $generated = $this->getUser()->getTFA()->isOTPGenerated($userKey);
                    if ($generated and !$resend) {
                        $this->addFlashSuccess("The verification code has already been sent to your email \"$email\", " .
                            "please check your inbox");
                        $this->log->notice('OTP has not been generated second time');
                    }
                    else {
                        if ($resend and $generated) {
                            $code = $generated['code'];
                        }
                        else {
                            $code = $this->getUser()->getTFA()->generateTFAOTP($userKey);
                        }
                        $this->app['util.helper']->sendEmail($email, $emailSubject, sprintf($emailBody, $code));
                        $this->addFlashSuccess("The verification code has been sent to your email \"$email\", " .
                            "please check your inbox");
                        $this->log->debug('OTP has been generated and sent', ['code' => $code]);
                        unset($code);
                    }
                }
            }
        }
        catch (\Exception $e) {
            $this->addFlashError($e->getMessage());
        }

        return $this->app['twig']->render('main/tfa.html.twig', [
            'code'     => !empty($code) ? $code : '',
            'parameters' => Arr::except($this->request->query->all(), ['resend'])
        ]);
    }

    public function login()
    {
        if (function_exists('mcrypt_create_iv')) {
            $this->app['session']->set('oauth_token', bin2hex(mcrypt_create_iv(32, MCRYPT_DEV_URANDOM)));
        } else {
            $this->app['session']->set('oauth_token', bin2hex(openssl_random_pseudo_bytes(32)));
        }

        if ($this->request->get('redirect')) {
            $this->app['session']->set('oauth_redirect', $this->request->get('redirect'));
        }

        $url = $this->app['config.whmcs.path'] . "oauth/authorize.php?client_id=" . $this->app['config.whmcs.client'] .
                "&response_type=code" .
                "&autoauth=1" .
                "&scope=openid%20profile%20email" .
                "&redirect_uri=" . $this->app['url_generator']->generate('oauth_code', [], UrlGenerator::ABSOLUTE_URL) .
                "&state=security_token%3D" . $this->app['session']->get('oauth_token') . "%26url%3D" . $this->app['url_generator']->generate('dashboard', [], UrlGenerator::ABSOLUTE_URL);;

        return new RedirectResponse($url);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @return RedirectResponse
     * @throws \Exception
     */
    public function code(Request $request, Application $app) {
        $code = $request->get('code');

        // Authorization cancelled
        if (!$code) {
            return $this->redirectToRoute('index');
        }

        if (hash_equals($code, $app['session']->get('oauth_token'))) {
            throw new \Exception('Bad Auth Token');
        }

        $redirectUri = $app['url_generator']->generate('oauth_code', [], UrlGenerator::ABSOLUTE_URL);

        $info = $app['integration.whmcs.api']->oAuthToken($code, $redirectUri);
        if (!empty($info['error'])) {
            throw new \ErrorException('Authentication error: ' . json_encode($info));
        }

        $access_token = $info['access_token'];

        $userInfo = $app['integration.whmcs.api']->oAuthUserInfo($access_token);
        if (!empty($userInfo['error'])) {
            throw new \ErrorException('Authentication error: ' . json_encode($userInfo));
        }

        $details = $app['integration.whmcs.api']->getClientDetailsByEmail($userInfo['email']);

        if (empty($details['userid'])) {
            if (!empty($details['result']) and 'error' == $details['result'] and !empty($details['message'])) {
                throw new \ErrorException('Authentication error: ' . $details['message']);
            }
            else {
                throw new \ErrorException('Authentication error! Please try later or contact with us');
            }
        }
        $whmcsId = $details['userid'];

        $user = $this->getApi()->userManagement()->upsertUser('whmcs', $whmcsId, $userInfo['email']);
        if (empty($user['userId'])) {
            throw new ErrorException('Unable to create user account');
        }
        $this->getUser()->authorizeById($user['userId']);
//        $this->getUser()->getTFA()->clearVerificationForced()->clearRedirectUrl();
        if ($this->app['session']->has('skip-tfa')) {
            if ($this->getUser()->getTFA()) {
                $this->getUser()->getTFA()->setTFAValidated($this->getUser()->getId());
            }
            $this->app['session']->remove('skip-tfa');
        }

        if ($app['session']->has('oauth_redirect')) {
            $redirectTo = $app['session']->get('oauth_redirect');
            $app['session']->remove('oauth_redirect');
        }
        else {
            $redirectTo = $app['url_generator']->generate('dashboard');
        }

        // Force sync plans
        try {
            $this->app['db_helper']->refreshUserPlans();
        }
        catch (ErrorException $e) {
            $this->addFlashError('Unable to sync plan(s)');
            $this->log->warn('Packages sync exception', ['message' => $e->getMessage(), 'exception' => $e]);
        }
        $response = new RedirectResponse($redirectTo);
        // Mark customer was authorized at the new dashboard
        $response->headers->setCookie(new Cookie('auth_type', 'whmcs', (new \DateTime())->add(new \DateInterval('P1Y'))));

        return $response;
    }

    public function logout() {
        $this->getUser()->deauthorize();

        return $this->app['integration.whmcs.plugin.auth']
            ->unauthorize($this->getUrl('index', []));
    }

    public function relogin()
    {
        $this->logout();

        if ($this->request->get('asAdmin')) {
            $this->app['session']->set('skip-tfa', 1);
        }

        return $this->login();
    }

    public function isEmailRegistered(Request $request)
    {
        return new Response(null, 400);
        if ($request->get('email')) {
            if (filter_var($request->get('email'), FILTER_VALIDATE_EMAIL)) {
                $response = $this->app['integration.whmcs.api']->api('getclientsdetails', ['email' => $request->get('email')]);

                if (empty($response['id'])) {
                    return new Response(null, 200);
                }
                else {
                    return (new Response())->setStatusCode(400, sprintf(
                        'Email "%s" is already registered. You can login <a href="%s">here</a>',
                        $request->get('email'), $this->getUrl('login',
                            ['redirect' => $this->getUrl('checkout',
                                ['country' => $request->get('country'), 'category' => $request->get('category')])])));
                }
            }

        }

        return new Response(null, 400);
    }

    public function packageActionDetermine($country, $category)
    {
        if (!$this->getUser()->isAuthorized()) {
            return $this->redirectToRoute('quick_buy', ['country' => $country, 'category' => $category, 'quantity' => $this->request->get('quantity')]);
        }

        try {
            $this->getApi()->packages()->get($country, $category);
            return $this->redirectToRoute('checkout', ['country' => $country, 'category' => $category, 'quantity' => $this->request->get('quantity')]);
        } catch (\Exception $e) {
            return $this->redirectToRoute('buy_new_package', ['country' => $country, 'category' => $category, 'quantity' => $this->request->get('quantity')]);
        }
    }
}

<?php

namespace Proxy\Controller;

use Axelarge\ArrayTools\Arr;
use Blazing\Reseller\Api\Api\Entity\PackageEntity;
use Blazing\Common\RestApiRequestHandler\Exception\BadRequestException;
use ErrorException;
use Proxy\Util\Util;
use Silex\Application;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class DashboardController extends AbstractController
{
    protected $config = [
        'portsPerPage' => 10
    ];


    // render contact page
    public function contact(Application $app, Request $request)
    {
        return $app['twig']->render('contact.html.twig', [
            "result" => "",
        ]);
    }

    // submit email contact page
    public function contactEmail(Request $request)
    {


        $email = $request->get('email');
        $subject = $request->get('subject');
        $paypal = $request->get('paypal');
        $text = $request->get('Description');
//        $haveServer = $request->get('haveServer');
        $tickettype = $request->get('ticket_type');


        $message = "
<html>
<head>
<title>HTML email</title>
</head>
<body>
<p>BlazingSEO Hosting Dashboard Support ticket:</p>

<p><b>From:</b></p>
<p>$email</p>

<br>

<p><b>Subject</b></p>
<p>$subject</p>

<br>

<p><b>
PayPal</b>
</p>
<p>$paypal</p>

<br>


<br>

<p>
 <b>
Type of support<b>

 </p>
<p>$tickettype</p>

<br>

<p><b>

 Message<b>

 </p>
<p>$text</p>


</body>
</html>
";

// Always set content-type when sending HTML email
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

// More headers
        $headers .= 'To:Tymur <tt.softevol@gmail.com>' . "\r\n";
        $headers .= 'From:Dashboard <hosting@blazingseollc.com>' . "\r\n";
        $headers .= 'Cc:Support <support@blazingseollc.com>' . "\r\n";

        $result = mail('support@blazingseollc.com', $subject, $message, $headers);

//        return $this->app['twig']->get('contact', [
//            'result'     => $result,
//        ]);

        return $this->addFlashSuccess('The mail has been sent successfully
')->redirectToRoute('contact');

//        $from = "support@blazingseollc.com";

//        $res = $this->getApi()->userManagement()->findUserByLoginOrEmail($email);
//
//        $whmcsDetails = $this->app['integration.whmcs.api']->api(
//            'GetClientsDetails',
//            [
//                'clientid' => $res['whmcsId']
//            ]);
//
//        $this->app['util.helper']->sendEmail($whmcsDetails['email'], $subject);
//
//        $sent = mail("support@blazingseollc.com", $subject, $text, join("\r\n", [
//            "From: Hosting dashboard contact form",
//            "Reply-To: " . ($email ? $email : $from),
//        ]));

//        return $this->redirectToRoute("contactEmail");

    }


    // load more invoices on billing page
    public function getMoreInvoices(Request $request)
    {

        $invoices = $this->app['integration.whmcs.plugin.servers']->getInvoicesByUserId($this->getUser()->getDetails('whmcsId'), 10, $request->get('page'));


        return new JsonResponse($invoices['invoices']);
    }


    // load more services on billing page
    public function getMoreServices(Request $request)
    {


        $limit = 0;
        $offset = 0;
        if (($page = $request->get('page')) && ctype_digit($page)) {
            $limit = $this->config['portsPerPage'] + 1;
            $offset = $this->config['portsPerPage'] * ($page);
        }

        $services = $this->app['integration.whmcs.api']->getClientProducts($this->getUser()->getDetails('whmcsId'), $offset);
        $serversProducts = $this->app['integration.whmcs.plugin.servers']->getClientsServersProducts($this->getUser()->getDetails('whmcsId'), $offset, 10);


        return new JsonResponse($serversProducts);
    }


    // Server management page init
    public function serverManagement(Application $app, Request $request)
    {

        $services = $this->app['integration.whmcs.api']->getClientProducts($this->getUser()->getDetails('whmcsId'));


        $totalResult = $this->app['integration.whmcs.plugin.servers']->getClientsServersProducts($this->getUser()->getDetails('whmcsId'), 0, 999);

        $serversProducts = $this->app['integration.whmcs.plugin.servers']->getClientsServersProducts($this->getUser()->getDetails('whmcsId'), 0, 10);





        return $app['twig']->render('dashboard/serverManagement.html.twig', [
            'services' => $serversProducts,
            'numreturned' => count($serversProducts['products']['product']),
            'servers' => $serversProducts,
            'totalNum' => count($totalResult['products']['product'])


        ]);

    }


    // render dedicated servers store page
    public function getDedicatedServers(Application $app)
    {

        $dedicatedServers = $this->app['integration.whmcs.plugin.servers']->getProductsByGID(16);



        return $app['twig']->render('products/dedicated_servers.html.twig', [
            'products' => $dedicatedServers['products']['product'],
//            'products' =>$dedicatedServers['products']['product'],
            'productNav' => false,
            'title' => "Dedicated Servers"


        ]);
    }

    // render los angeles vps store page
    public function getLosAngelesVps(Application $app)
    {



        if ($this->getUser()->isAuthorized()) {


            $laProductsForUser = $this->app['integration.whmcs.plugin.servers']->getProductsByUserIdAndGID(26, $this->getUser()->getDetails('whmcsId'));


            return $app['twig']->render('dashboard/products.html.twig', [
                'products' => $laProductsForUser['products']['product'],
                'productNav' => false,
                'title' => "Los Angeles VPS"


            ]);
        }

//        $products = $this->app[ 'integration.whmcs.api' ]->getProductsByGid(26);
        $losAngeles = $this->app['integration.whmcs.plugin.servers']->getProductsByGID(26);


//        echo "<pre>";
//        print_r($losAngeles);
//        echo "</pre>";

        return $app['twig']->render('dashboard/products.html.twig', [
            'products' => $losAngeles['products']['product'],
            'productNav' => false,
            'title' => "Los Angeles VPS"


        ]);
    }


    // render new york/new jersey vps store page
    public function getNyNjVps(Application $app)
    {
//        $products = $this->app[ 'integration.whmcs.api' ]->getProductsByGid(27);
        $nynj = $this->app['integration.whmcs.plugin.servers']->getProductsByGID(27);



        if ($this->getUser()->isAuthorized()) {


            $nynjProductsForUser = $this->app['integration.whmcs.plugin.servers']->getProductsByUserIdAndGID(27, $this->getUser()->getDetails('whmcsId'));


            return $app['twig']->render('dashboard/products.html.twig', [
                'products' => $nynjProductsForUser['products']['product'],
                'productNav' => false,
                'title' => "New York/New Jersey VPS"


            ]);
        }

        return $app['twig']->render('dashboard/products.html.twig', [
            'products' => $nynj['products']['product'],
            'productNav' => false,
            'title' => "New York/New Jersey VPS"


        ]);
    }

    // render dallas vps store page
    public function getDallasVps(Application $app)
    {
        $dallas = $this->app['integration.whmcs.plugin.servers']->getProductsByGID(28);


        if ($this->getUser()->isAuthorized()) {


            $dallasProductsForUser = $this->app['integration.whmcs.plugin.servers']->getProductsByUserIdAndGID(28, $this->getUser()->getDetails('whmcsId'));


            return $app['twig']->render('dashboard/products.html.twig', [
                'products' => $dallasProductsForUser['products']['product'],
                'productNav' => false,
                'title' => "Dallas VPS"


            ]);
        }

        return $app['twig']->render('dashboard/products.html.twig', [
            'products' => $dallas['products']['product'],
            'productNav' => false,
            'title' => "Dallas VPS"


        ]);
    }

    // render chicago vps store page
    public function getChicagoVps(Application $app)
    {
        $chicago = $this->app['integration.whmcs.plugin.servers']->getProductsByGID(24);



        echo $this->getUser()->getDetails('whmcsId');

        if ($this->getUser()->isAuthorized()) {


            $chicagoProductsForUser = $this->app['integration.whmcs.plugin.servers']->getProductsByUserIdAndGID(24, $this->getUser()->getDetails('whmcsId'));


            return $app['twig']->render('dashboard/products.html.twig', [
                'products' => $chicagoProductsForUser['products']['product'],
                'productNav' => false,
                'title' => "Chicago VPS"


            ]);
        }


        return $app['twig']->render('dashboard/products.html.twig', [
            'products' => $chicago['products']['product'],
            'productNav' => false,
            'title' => "Chicago VPS"


        ]);
    }




    //render vpn store page
//    public function getVpn(Application $app){
////        $products = $this->app[ 'integration.whmcs.api' ]->getProductsByGid(20);
//
//        $vpn = $this->app['integration.whmcs.plugin.servers']->getProductsByGID( 20);
//
//
////        echo '<pre>';
////        print_r($vpn);
////        echo '</pre>';
//
//        return $app['twig']->render('products/vpn.html.twig', [
//            'products' =>$vpn['products']['product'],
//            'productNav' => false,
//            'title' => "VPN"
//
//
//
//        ]);
//    }

    //render vpn checkout
//    public function getVpnCheckout(Application $app, $pid){
//
//
//       if(!$pid){
//           return $this->redirectToRoute('dashboard');
//
//       }
//
////        $product = $this->app[ 'integration.whmcs.api' ]->getProductByPid($pid);
//
//        $singleProduct = $this->app['integration.whmcs.plugin.servers']->getProductsByPID($pid);
//
////        echo '<pre>';
////        print_r($singleProduct);
////        echo '</pre>';
//
//        if($singleProduct['result'] != "success"){
//            $this->addFlashError('No product with id:'.$pid." was found");
//
//
//
//            return $this->products($app, 0);
//        }
//
//        return $app['twig']->render('products/vpn_checkout.html.twig', [
//            'product' =>$singleProduct['products']['product'],
//
//
//        ]);
//    }

    // render checkout page
    public function products(Application $app)
    {


        return $app['twig']->render('dashboard/products.html.twig', [
            'products' => '',
            'productNav' => true,
            'title' => "Get Your Blazing Products Today"


        ]);


        if ($gid == 0) {
            return $app['twig']->render('dashboard/products.html.twig', [
                'products' => '',
                'productNav' => true


            ]);
        }

        $products = $this->app['integration.whmcs.api']->getProductsByGid($gid);


//        echo '<pre>';
//        print_r($products);
//        echo '</pre>';

        return $app['twig']->render('dashboard/products.html.twig', [
            'products' => $products['products']['product'],
            'productNav' => false


        ]);

    }

    public function getSingleProduct(Application $app, $pid)
    {


        if (!$pid) {
            return $this->redirectToRoute('dashboard');

        }



        if ($this->getUser()->isAuthorized()) {


            $singleProductForUser = $this->app['integration.whmcs.plugin.servers']->getProductsByUserIdAndPID($pid, $this->getUser()->getDetails('whmcsId'));


            if ($singleProductForUser['result'] != "success") {
                $this->addFlashError('No product with id:' . $pid . " was found");


                return $this->products($app, 0);
            }


            return $app['twig']->render('dashboard/product.html.twig', [
                'product' => $singleProductForUser['products']['product'],


            ]);
        }

//        $product = $this->app[ 'integration.whmcs.api' ]->getProductByPid($pid);

        $singleProduct = $this->app['integration.whmcs.plugin.servers']->getProductsByPID($pid);

//        echo '<pre>';
//        print_r($singleProduct);
//        echo '</pre>';

        if ($singleProduct['result'] != "success") {
            $this->addFlashError('No product with id:' . $pid . " was found");


            return $this->products($app, 0);
        }

        return $app['twig']->render('dashboard/product.html.twig', [
            'product' => $singleProduct['products']['product'],


        ]);

    }

    public function getAddorder(Application $app)
    {

        return $this->redirectToRoute('products');
    }

    public function addOrder(Application $app, Request $request)
    {

        if (!$this->request->get('id')) {
            return $this->redirectToRoute('products');
        }


        $product = $this->app['integration.whmcs.api']->getProductByPid($this->request->get('id'));

        $product = $product['products']['product']['0'];

        $options = [
            'pid' => $this->request->get('id'),
            'billingcycle' => $this->request->get('billingcycle'),
            'hostname' => $this->request->get('hostname'),
            'rootpw' => $this->request->get('rootpw'),
            'promocode' => $this->request->get('promocode'),
            'trial' => $this->request->get('trial', false)
        ];


        //check for config options
        if ($this->request->get('location')) {
            $options['location'] = $this->request->get('location');
        }


        if ($this->request->get('os')) {
            $options['os'] = $this->request->get('os');
        }


        if ($this->request->get('ssd')) {
            $options['ssd'] = $this->request->get('ssd');
        }


        if ($this->request->get('ram')) {
            $options['ram'] = $this->request->get('ram');
        }

        if ($this->request->get('totalPriceThereafterTotal')) {
            $options['totalPriceThereafterTotal'] = $this->request->get('totalPriceThereafterTotal');
        }


        if ($this->request->get('lifetime')) {
            $options['lifetime'] = $this->request->get('lifetime');
        }


        //user not log in
        if (!$this->getUser()->isAuthorized()) {


            return $app['twig']->render('checkout/addorder.html.twig', [

                "order" => $options,
                'userin' => false,
                'countries' => Util::getCountriesList(),
                'totalOrderPrice' => $this->request->get("totalPrice"),
                'product' => $product,

            ]);
        }


//        $result = $this->app[ 'integration.whmcs.api' ]->addOrder($options);
//
//        echo '<pre>';
//
//        print_r($product);
//        echo '</pre>';

        // user logged in - redirect to checkout/addorder page with no additional registration
        return $app['twig']->render('checkout/addorder.html.twig', [

            "order" => $options,
            'userin' => true,
            'userid' => $this->getUser()->getDetails('whmcsId'),
            'totalOrderPrice' => $this->request->get("totalPrice"),
            'product' => $product


        ]);
    }

    public function confirmOrder(Request $request)
    {


        // non user
        if (!$this->getUser()->isAuthorized()) {

            try {

                if ($this->app['config.maintenance.login']) {
                    return $this->redirectToRoute('quick_buy_empty');
                }


                // user have an account
                if ($this->request->get('custtype') == 'existing') {
                    $login = $this->request->get('login');
                    $password = $this->request->get('loginpassword');

                    if (!$this->validateCaptcha()) {
                        throw new ErrorException("Captcha not valid, please try again");

                    }


                    // Determine source

                    if (!$login) {
                        throw new ErrorException("No login/email field passed");
                    }


                    // Check the pass again
                    $response = $this->app['integration.whmcs.api']->api('ValidateLogin', [
                        'email' => $login,
                        'password2' => $password
                    ]);

//                echo "<pre>";
//                print_r($response);
//                echo "</pre>";

//                 Password is valid
                    if (!empty($response['result']) and 'success' == $response['result']) {


                        $user = $this->getApi()->userManagement()->findUserByLoginOrEmail($login);
//
//                        echo "<pre>";
//                        print_r($user);
//                        echo "</pre>";
//                        die();


                        $this->getUser()->authorizeById($user['userId']);

                        $options = [
                            'clientid' => $user['whmcsId'],
                            'pid' => $this->request->get('pid'),
                            'billingcycle' => $this->request->get('billingcycle'),
                            'hostname' => $this->request->get('hostname'),
                            'rootpw' => $this->request->get('rootpw'),
                            'paymentmethod' => $this->request->get('paymentmethod'),

                            'promocode' => $this->request->get('promocode'),
                            'trial' => $this->request->get('trial', false),
                            "configoptions" => base64_encode(serialize(
                                array('38' => $this->request->get('location'),
                                    '39' => $this->request->get('os'),
                                    '43' => $this->request->get('ssd'),
                                    '44' => $this->request->get('ram'))))
                        ];

//                    $result = $this->app[ 'integration.whmcs.api' ]->addOrder($options);

                        $addorderResult = $this->app['integration.whmcs.plugin.servers']->addOrder($options);



                        if (!empty($addorderResult['trialNotAllowedReason'])) {

                            if ($addorderResult['trialNotAllowedReason'] == "PPBA not set") {

//                return $this->redirectToRemoteRoute('whmcsPPBA', ['redirectToDashboard'=> 1]);
//                        return $this->addFlashError("PPBA not set")->redirectToRoute('dashboard');


                                return $this->addFlashError("PPBA not set")->app['integration.whmcs.plugin.auth']
                                    ->authorize($login, $password, $this->getUrl('dashboard', []) , null, 'Signing In...');

                            }

                            try {
                                if ($addorderResult['trialNotAllowedReason'] == "Exist trial product") {


                                    throw new ErrorException("You already have a trial on this product");

                                } else {
                                    return $this->addFlashError($addorderResult['trialNotAllowedReason'])->app['integration.whmcs.plugin.auth']
                                        ->authorize($login, $password, $this->getUrl('dashboard', []), null, 'Invoice creating...');

                                }
                            } catch (ErrorException $e) {

//                        return $this->addFlashError($e->getMessage())->redirectToRoute('dashboard');
                                return $this->addFlashError($e->getMessage())->app['integration.whmcs.plugin.auth']
                                    ->authorize($login, $password, $this->getUrl('dashboard', []), null, 'Invoice creating...');

                            }
                        }




                        return $this->app['integration.whmcs.plugin.auth']
                            ->authorize($login, $password, 'https://whmcs.dev.sprious.com/viewinvoice.php?id=' . $addorderResult['invoiceid'], null, 'Invoice creating...');



//                        return $this->redirectToRemoteRoute('whmcsViewInvoice', ['id' => $addorderResult['invoiceid']]);

//                        return $this->app['integration.whmcs.plugin.auth']
//                            ->authorize($login, $password, $this->getUrl('whmcsViewInvoice', ['id' => $addorderResult['invoiceid']]), null, 'Invoice creating...');
//            return $this->redirectToRemoteRoute('whmcsViewInvoice', ['id' => $result['invoiceid']]);



                    } else {
                        throw new ErrorException("Unknown login/email \"$login\" or wrong password. Please check if input is correct ");
                    }

                }

                if (!$this->validateCaptcha()) {
                    throw new ErrorException('Captcha is not valid, please try again');
//                return $this->addOrder($app, $request);

                }


                if (!$request->get('email') or !filter_var($request->get('email'), FILTER_VALIDATE_EMAIL)) {
                    throw new ErrorException("You did not enter your email or it is invalid");

                }


                // Check if email is already registered
                $response = $this->app['integration.whmcs.api']->api('getclientsdetails', ['email' => $request->get('email')]);


                if (!empty($response['id'])) {

                    throw new ErrorException("Error: A user already exists with that email address");

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

                

//
//            echo "<pre>";
//            print_r($userData);
//            echo "</pre>";


                $response2 = $this->app['integration.whmcs.api']->api('addclient', $userData);

//            echo "<pre>";
//            print_r($response2);
//            echo "</pre>";
//
//            die();

                // Add user to db
                $user = $this->getApi()->userManagement()->upsertUser('whmcs', $response2['clientid'], $userData['email']);

                if (empty($user['userId'])) {
                    throw new ErrorException('Unable to create user account');
                }
//            $this->getUser()->authorizeById($user['userId']);
//            $this->getUser()->authorizeById($response2['clientid']);
                $this->getUser()->authorizeById($user['userId']);

                $options = [
                    'clientid' => $response2['clientid'],
                    'pid' => $this->request->get('pid'),
                    'billingcycle' => $this->request->get('billingcycle'),
                    'hostname' => $this->request->get('hostname'),
                    'rootpw' => $this->request->get('rootpw'),
                    'paymentmethod' => $this->request->get('paymentmethod'),

                    'promocode' => $this->request->get('promocode'),
                    'trial' => $this->request->get('trial', false),


                    "configoptions" => base64_encode(serialize(
                        array('38' => $this->request->get('location'),
                            '39' => $this->request->get('os'),
                            '43' => $this->request->get('ssd'),
                            '44' => $this->request->get('ram'))))
                ];


//            $result = $this->app[ 'integration.whmcs.api' ]->addOrder($options);
                $addorderResult = $this->app['integration.whmcs.plugin.servers']->addOrder($options);



                if (!empty($addorderResult['trialNotAllowedReason'])) {

                    if ($addorderResult['trialNotAllowedReason'] == "PPBA not set") {

//                return $this->redirectToRemoteRoute('whmcsPPBA', ['redirectToDashboard'=> 1]);
//                        return $this->addFlashError("PPBA not set")->redirectToRoute('dashboard');


                        return $this->addFlashError("PPBA not set")->app['integration.whmcs.plugin.auth']
                            ->authorize($userData['email'], $userData['password2'], $this->getUrl('dashboard', []) , null, 'Signing In...');

                    }

                    try {
                        if ($addorderResult['trialNotAllowedReason'] == "Exist trial product") {


                            throw new ErrorException("You already have a trial on this product");

                        } else {
                            return $this->addFlashError($addorderResult['trialNotAllowedReason'])->app['integration.whmcs.plugin.auth']
                                ->authorize($userData['email'], $userData['password2'], $this->getUrl('dashboard', []), null, 'Invoice creating...');

                        }
                    } catch (ErrorException $e) {

//                        return $this->addFlashError($e->getMessage())->redirectToRoute('dashboard');
                        return $this->addFlashError($e->getMessage())->app['integration.whmcs.plugin.auth']
                            ->authorize($userData['email'], $userData['password2'], $this->getUrl('dashboard', []), null, 'Invoice creating...');

                    }
                }



                return $this->app['integration.whmcs.plugin.auth']
                    ->authorize($userData['email'], $userData['password2'], 'https://whmcs.dev.sprious.com/viewinvoice.php?id=' . $addorderResult['invoiceid'], null, 'Invoice creating...');


//            return
//            return $this->redirectToRemoteRoute('whmcsViewInvoice', ['id' => $result['invoiceid']]);
//            return $this->app['integration.whmcs.plugin.auth']
//                ->authorize($userData['email'], $userData['password2'], $this->getUrl('whmcsViewInvoice', ['id'=> $result['invoiceid']]), null, 'Invoice creating...');
            } catch (ErrorException $e) {
                $product = $this->app['integration.whmcs.api']->getProductByPid($this->request->get('pid'));
                $product = $product['products']['product']['0'];
                $this->addFlashError($e->getMessage());


                $existingLogin = "";

                if ($request->get('login')) {
                    $existingLogin = $request->get('login');

                }


                return $this->app['twig']->render('checkout/addorder.html.twig', [

                    'order' => array_merge(array_filter($request->request->all())),
                    'data' => array_merge(array_filter($request->request->all())),
                    'countries' => Util::getCountriesList(),
                    'product' => $product,
                    'totalOrderPrice' => $this->request->get('totalOrderPrice'),
                    'userin' => false,
                    'pid' => $this->request->get('pid'),
                    'hostname' => $this->request->get('hostname'),
                    'rootpw' => $this->request->get('rootpw'),
                    'billingcycle' => $this->request->get('billingcycle'),
                    'custType' => $this->request->get('custtype'),
                    'login' => $existingLogin,


//                    'order' => [
//                        'promocode' => $this->request->get('promocode'),
//                        'trial'     => $this->request->get('trial', false),
//
//                    ]


                ]);

            }

        }


        // user in

        $options = [
            'clientid' => $this->request->get('clientid'),

            'pid' => $this->request->get('pid'),
            'billingcycle' => $this->request->get('billingcycle'),
            'hostname' => $this->request->get('hostname'),
            'rootpw' => $this->request->get('rootpw'),
            'paymentmethod' => $this->request->get('paymentmethod'),
            'promocode' => $this->request->get('promocode'),
            'trial' => $this->request->get('trial', false),


            "configoptions" => base64_encode(serialize(
                array('38' => $this->request->get('location'),
                    '39' => $this->request->get('os'),
                    '43' => $this->request->get('ssd'),
                    '44' => $this->request->get('ram'))))
        ];


        $addorderResult = $this->app['integration.whmcs.plugin.servers']->addOrder($options);

//
//        print_r($addorderResult);
//        die();

        if ($addorderResult['trial'] == 1) {

            return $this->redirectToRoute('dashboard');

        }

        if (!empty($addorderResult['trialNotAllowedReason'])) {

            if ($addorderResult['trialNotAllowedReason'] == "PPBA not set") {

//                return $this->redirectToRemoteRoute('whmcsPPBA', ['redirectToDashboard'=> 1]);
                return $this->addFlashError("PPBA not set")->redirectToRoute('dashboard');

            }

            try {
                if ($addorderResult['trialNotAllowedReason'] == "Exist trial product") {


                    throw new ErrorException("You already have a trial on this product");

                }
            } catch (ErrorException $e) {

                return $this->addFlashError($e->getMessage())->redirectToRoute('dashboard');

            }
        }


//        $result = $this->app[ 'integration.whmcs.api' ]->addOrder($options);


        return $this->redirectToRemoteRoute('whmcsViewInvoice', ['id' => $addorderResult['invoiceid']]);


    }


    //render dashboard home page
    public function dashboard(Application $app, Request $request)
    {
        $user = $this->getUser();

        if ($app['session']->get('redirect_dashboard')) {
            $app['session']->remove('redirect_dashboard');
        }


        // client servers products
        $totalResult = $this->app['integration.whmcs.plugin.servers']->getClientsServersProducts($this->getUser()->getDetails('whmcsId'), 0, 999);



//
//        echo "<pre>";
//        print_r($totalResult);
//        echo "</pre>";


        $activeTrials = [];
        $pendingTrials = [];


        if ($totalResult['result'] == "success") {
            foreach ($totalResult['products']['product'] as $userService) {
                if (isset($userService['trialStatus'])) {
                    if ($userService['trialStatus'] == "Trial period") {
                        $activeTrials[] = $userService;
                    }
                    if ($userService['trialStatus'] == "Pending trial") {
                        $pendingTrials[] = $userService;
                    }




                }
            }

        }






        


//        echo "<pre>";
//        print_r($activeTrials);
//        echo "</pre>";


        $this->app['app.announcement']->enable();

//        $services = $this->app[ 'integration.whmcs.api' ]->getClientProducts($this->getUser()->getDetails('whmcsId'));


        //invoice count
        $data = $this->app['integration.whmcs.plugin.servers']->getInvoicesByUserId($this->getUser()->getDetails('whmcsId'), 10, 1, true);


        $totalInvoices = $data['invoices'][0]['count'];


        return $app['twig']->render('dashboard/main.html.twig', [
            'email' => $user->getDetails('email'),
            'perPage' => $this->config['portsPerPage'],
            'user' => $user,
            'activeTrials' => $activeTrials,
            'pendingTrials' => $pendingTrials,
//            'pendingTrials' => $totalResult['products']['product'],
            'invoiceCount' => $totalInvoices,
            'servicesCount' => count($totalResult['products']['product'])

        ]);
    }


    public function getInvoices(Request $request)
    {


        $limit = 0;
        $offset = 0;
        if (($page = $request->get('page')) && ctype_digit($page)) {
            $limit = $this->config['portsPerPage'] + 1;
            $offset = $this->config['portsPerPage'] * ($page);
        }

        $data = $this->app['integration.whmcs.api']->getInvoices($this->getUser()->getDetails('whmcsId'), $offset);


        $total = $data['totalresults'];

        $invoices = [];
        $i = 0;


        foreach ($data['invoices']['invoice'] as $invoice) {


            $userInvoice = $this->app['integration.whmcs.api']->getInvoice($invoice['id']);
//            print_r($userInvoice);


//            if (!array_key_exists("description",$userInvoice['items']['item']))
//            {
////                continue;
//            }
            $invoices[$i] = $invoice;

            $invoices[$i]['description'] = $userInvoice['items']['item'][0]['description'];
//            $prevId = $invoice[$i]['id'];
            $i++;
        }

//        print_r($invoices);

        return new JsonResponse($invoices);
    }


    public function saveFormat(Request $request, $format)
    {
        $this->getApi()->user()->updateSettings(['authType' => $format] + ($request->get('ipAuthType') ? ['ipAuthType' => $request->get('ipAuthType')] : []));
        $this->getUser()->refreshData();

        $details = $this->getUser()->getDetails();

        return new JsonResponse([
            'success' => true,
            'authType' => $details['authType'],
            'ipAuthType' => $details['ipAuthType']
        ]);
    }

    public function saveLocations(Application $app, Request $request)
    {
        $ports = $request->get('ports');

        foreach ($ports as $country => $categories) {
            foreach ($categories as $category => $regions) {
                try {
                    $this->getApi()->ports4()->setAllocation($country, $category,
                        array_filter($regions, function ($v) {
                            return (int)$v;
                        }));
                } catch (\Exception $e) {
                    return new JsonResponse([
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $response = ['success' => true];

        if ($app['session']->get('redirect_dashboard')) {
            $response['redirectUrl'] = $app['url_generator']->generate('dashboard');
            // $app['session']->remove('redirect_dashboard');
        }

        return new JsonResponse($response);
    }

    public function sneaker(Application $app, Request $request)
    {
        if ($request->getMethod() == "POST") {
            $location = $request->get('location');

            if (!in_array($location, ['LA', 'NY'])) {
                return $this->addFlashError('Invalid Location Passed')->redirectToRoute('sneaker');
            }

            $this->getApi()->user()->updateSneakerLocation($location);
            $this->getUser()->refreshData();

            if ($app['session']->get('redirect_dashboard')) {
                $app['session']->remove('redirect_dashboard');

                return $this->redirectToRoute('dashboard');
            } else {
                $this->addFlashSuccess('Sneaker Location Saved');

                return $this->redirectToRoute('sneaker');
            }
        }
        return $app['twig']->render('dashboard/sneaker.html.twig');
    }

    public function settings()
    {
        $displayExportButtons = false;
        $details = $this->getUser()->getDetails();
        if (isset($details['whmcsId'])) {
            $country = $this->app['integration.whmcs.api']->getClientDetailsById($this->getUser()->getDetails()['whmcsId'])['client']['country'];

            if (Util::isCountryInEU($country)) {
                $displayExportButtons = true;
            }
        }

        return $this->app['twig']->render('dashboard/settings.html.twig', [
            'proxyUrl' => $this->app['config.proxyUrl'],
            'displayExportButtons' => $displayExportButtons
        ]);
    }

    public function saveSettings()
    {
        $options = [
            'rotationType' => $this->request->get('rotationType'),
            'rotate_ever' => $this->request->get('rotateEver'),
            'rotate_30' => $this->request->get('rotate30'),
        ];

        // Drop out null options
        foreach ($options as $option => $value) {
            if (is_null($value)) {
                unset($options[$option]);
            }
        }

        if ($options) {
            $this->getApi()->user()->updateSettings($options);

            // Update session
            $this->getUser()->refreshData();
        }

        return new JsonResponse(['status' => 'success']);
    }

    public function exportUserRelatedData()
    {
        $data = $this->getApi()->user()->exportUserData();

        $filename = 'account-' . $this->getUser()->getId() . '-data.json';

        $response = new Response(json_encode($data));

        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename
        );

        // Set the content disposition
        $response->headers->set('Content-Disposition', $disposition);

        // Dispatch request
        return $response;
    }

    public function replace()
    {
        $proxies = $this->getApi()->ports4()->getAll([], ['dedicated', 'semi-3', 'sneaker'],
            ['country' => 'asc', 'category' => 'asc', 'updated' => 'desc', 'rotated' => 'desc', 'ip' => 'asc'])['list'];
        $replacements = $this->getApi()->ports4()->getAvailableReplacements()['list'];

        return $this->app['twig']->render('dashboard/replace.html.twig', [
            'replacements' => $replacements,
            'proxies' => $proxies
        ]);
    }

    public function replaceIp(Application $app, $id)
    {
        $ports = Arr::pluck($this->getApi()->ports4()->getAll([], ['dedicated', 'semi-3', 'sneaker'])['list'], 'ip', 'id');

        try {
            if (empty($ports[$id])) {
                throw new ErrorException('No port is found');
            }

            $ip = $ports[$id];
            $this->getApi()->ports4()->setPendingReplace($ip);
        } catch (ErrorException $e) {
            return $this
                ->addFlashError($e->getMessage())
                ->redirectToRoute('replace');
        }

        return $this->addFlashSuccess('Replacement request received. See table below')
            ->redirectToRoute('replace');
    }

    public function replaceMultipleIp(Request $request)
    {
        $rawData = trim($request->get('replace', ''));
        $this->saveVariableToFlash('replaceMultipleIp', $rawData);
        $data = [];

        // Parse data
        if ($rawData) {
            $data = explode("\n", trim($rawData));
            $data = array_map(function ($ip) {
                list($ip) = explode(":", trim($ip));
                $ip = trim((string)$ip);

                return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
            }, $data);
            $data = array_filter($data);
        }

        if (!$data) {
            return $this->addFlashError('No valid IP-s passed')->redirectToRoute('replace');
        }

        try {
            $this->getApi()->ports4()->setPendingReplaceMultiple($data);
        } catch (ErrorException $e) {
            return $this
                ->addFlashError($e->getMessage())
                ->redirectToRoute('replace');
        }

        return $this->addFlashSuccess('Replacement request received. See table below')
            ->redirectToRoute('replace');
    }


    public function getIpList()
    {
        $ips = $this->getIpListInternal();

        return new JsonResponse([
            'status' => 'success',
            'ip' => $ips
        ]);
    }

    public function addIp($ip)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return new JsonResponse([
                'error' => true,
                'message' => "Not a valid IP address, must be valid IPv4 address."
            ]);
        }

        try {
            $ip = $this->getApi()->user()->addAuthIp($ip)['ip'];
        } catch (BadRequestException $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }

        $now = (new \DateTime(null))->getTimestamp();
        $timer = $this->getUser()->getTimer()->set([$ip['dateCreated'], $ip['id']], $now + 600);

        return new JsonResponse([
            'status' => 'success',
            'ip' => [
                'id' => $ip['id'],
                'ip' => $ip['ip'],
                'timer' => $timer
            ]
        ]);
    }

    public function removeIp($id)
    {
        $ips = $this->getApi()->user()->getAuthIpList()['list'];
        $key = array_search($id, array_column($ips, 'id'));
        if ($key !== false) {
            $this->getApi()->user()->deleteAuthIp($id);

            $this->getUser()->getTfimer()->clear([$ips[$key]['dateCreated'], $id]);

            return new JsonResponse(['status' => 'success']);
        }

        return new JsonResponse(['status' => 'error']);
    }

    public function setRotationTime(Request $request)
    {
        $time = $request->get('time');
        $portId = $request->get('portId');

        try {
            $this->getApi()->ports4()->setRotationTime($portId, $time);
        } catch (BadRequestException $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }

        return new JsonResponse(['status' => 'success']);
    }

    public function exportProxies($type)
    {
        $user = $this->getUser()->getDetails();
        if ('PW' == $user['authType']) {
            $ipPostfix = ":" . $this->app['config.port.pwd'] . ":" .
                Util::toProxyLogin($user) . ":" . $user['apiKey'];
        } else {
            if ($user['ipAuthType'] === 'SOCKS')
                $ipPostfix = ":" . $this->app['config.port.ip_socks'];
            else
                $ipPostfix = ":" . $this->app['config.port.ip'];
        }

        // Parse type
        $typeParsed = explode('-', $type);
        $country = '';
        $category = '';
        if (count($typeParsed) >= 2) {
            $country = $typeParsed[0];
            // category can be "semi-3", so push out country and concatenate category parts
            $category = join('-', array_slice($typeParsed, 1));
        }

        $proxies = $this->getApi()->ports4()->getAll($country ? [$country] : [], $category ? [$category] : [],
            ['country' => 'asc', 'category' => 'asc', 'updated' => 'desc', 'rotated' => 'desc', 'ip' => 'asc'])['list'];

        $response = [];
        foreach ($proxies as $row) {
            if (in_array($row['category'], ['rotating', 'google'])) {
                $response[] = $row['serverIp'] . ":" . $row['port'];
            } else {
                $response[] = $row['ip'] . $ipPostfix;
            }
        }

        return new Response(
            join("\n", $response),
            200,
            [
                'cache-control' => 'no-cache, must-revalidate', // HTTP/1.1
                'expires' => 'Sat, 26 Jul 1997 05:00:00 GMT', // Date in the past
                'content-type' => 'text/plain'
            ]
        );
    }
}

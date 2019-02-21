<?php

namespace Proxy\Integrations;

use Proxy\Integration;
use Silex\Application;

class WHMCS extends Integration {


    public function getPromotions($code){



        $post = [
            'action' => 'getPromotions',
            'code' => $code,

        ];





        return $this->getRequestHandler()->doRequest($post);



    }


    public function addOrder($options){


        $post = [
            'action' => 'addorder',
            'clientid' => $options['clientid'],
            'pid' => $options['pid'],
            'billingcycle' => $options['billingcycle'],
            'paymentmethod' => $options['paymentmethod'],
            'hostname' => $options['hostname'],
            'rootpw' => $options['rootpw'],

        ];

        if($options['configoptions']){

            $post['configoptions'] = $options['configoptions'];
        }



        return $this->getRequestHandler()->doRequest($post);


    }

    public function getInvoices($whmcsId, $offset = 0, $liminnum = 10){
        $post = [
            'action' => 'getinvoices',
            'userid' => $whmcsId,
            'limitstart' => $offset,
            'limitnum' => $liminnum,
        ];
        return $this->getRequestHandler()->doRequest($post);
    }

    public function getInvoice($invoiceId){
        $post = [
            'action' => 'getinvoice',
            'invoiceid' => $invoiceId,
        ];
        return $this->getRequestHandler()->doRequest($post);
    }

    public function getClientDetailsById($whmcsId) {
        $post = [
            'action' => 'getclientsdetails',
            'clientid' => $whmcsId,
            'stats' => false
        ];
        return $this->getRequestHandler()->doRequest($post);
    }

    public function getClientDetailsByEmail($email) {
        $post = [
            'action' => 'getclientsdetails',
            'email' => $email,
            'stats' => false
        ];
        return $this->getRequestHandler()->doRequest($post);
    }

    public function getClientProducts($clientId, $offset = 0) {
        $post = [
            'action' => 'getclientsproducts',
            'clientid' => $clientId,
            'limitstart' => $offset,
            'limitnum' => 10,
        ];
        return $this->getRequestHandler()->doRequest($post);
    }

    public function oAuthToken($code, $redirectUrl) {
        return $this->doCurlJson(
            $this->app['config.whmcs.path'] . "oauth/token.php",
            [
                "code" => $code,
                "client_id" => $this->app['config.whmcs.client'],
                "client_secret" => $this->app['config.whmcs.secret'],
                "redirect_uri" => $redirectUrl,
                "grant_type" => "authorization_code"
            ]
        );
    }

    public function oAuthUserInfo($access_token) {
        return $this->doCurlJson(
            $this->app['config.whmcs.path'] . "oauth/userinfo.php",
            [],
            'POST',
            ['Content-Type: application/json' , "Authorization: Bearer $access_token"]
        );
    }

    public function api($method, array $parameters)
    {
        return $this->getRequestHandler()->doRequest(array_merge($parameters, ['action' => $method]));
    }

    /**
     * @return WHMCSRequestHandler
     */
    protected function getRequestHandler()
    {
        return $this->app[ 'integration.whmcs.api.request_handler' ];
    }


    public function getProductsByGid($gid){
        $post = [
            'action' => 'getProducts',
            'gid' => $gid,
        ];
        return $this->getRequestHandler()->doRequest($post);

    }



    public function getProductByPid($pid){
        $post = [
            'action' => 'getProducts',
            'pid' => $pid,
        ];
        return $this->getRequestHandler()->doRequest($post);

    }
}
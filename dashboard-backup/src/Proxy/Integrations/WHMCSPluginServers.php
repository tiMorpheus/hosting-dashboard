<?php

namespace Proxy\Integrations;

use Buzz\Browser;
use Buzz\Client\Curl;
use Proxy\Integration;
use Silex\Application;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Zend\Json\Server\Exception\ErrorException;

class WHMCSPluginServers extends Integration
{

    protected $moduleName = 'blazing_servers';




    public function addOrder($order){


        return $this->doCurlJson(
            $this->app['config.whmcs.path.api'] . "modules/addons/{$this->moduleName}/api.php",
            array(
                'method' => 'addOrder',
                'clientid' => $order['clientid'],
                'pid' => $order['pid'],
                'billingcycle' => $order['billingcycle'],
                'paymentmethod' => $order['paymentmethod'],
                'hostname'=> $order['hostname'],
                'rootpw'=> $order['rootpw'],
                'promocode'=> $order['promocode'],
                'trial' => $order['trial'],
                'configoptions' => $order['configoptions']
            ),
            'POST',
            ["Auth-Token: ttse"]

        );


    }


    public function getInvoicesByUserId($userId, $limit = 10 , $page = 1 , $count = false){


        return $this->doCurlJson(
            $this->app['config.whmcs.path.api'] . "modules/addons/{$this->moduleName}/api.php",
            array( 'method' => 'getInvoices', 'userId' => $userId, 'limit' => $limit, 'page' => $page, 'count' => $count),
            'GET',
            ["Auth-Token: ttse"]

        );


    }



    public function getClientsServersProducts($clientId, $limitstart = 0 , $limitnum = 50){


        return $this->doCurlJson(
            $this->app['config.whmcs.path.api'] . "modules/addons/{$this->moduleName}/api.php",
            array( 'method' => 'getClientProducts', 'clientid' => $clientId, 'limitstart' => $limitstart, 'limitnum' => $limitnum),
            'GET',
            ["Auth-Token: ttse"]

        );

    }


    public function getProductsByGID($gid){


        return $this->doCurlJson(
            $this->app['config.whmcs.path.api'] . "modules/addons/{$this->moduleName}/api.php",
            array( 'method' => 'getProducts', 'gid' => $gid ),
            'GET',
            ["Auth-Token: ttse"]

        );
    }

    public function getProductsByPID($pid){


        return $this->doCurlJson(
            $this->app['config.whmcs.path.api'] . "modules/addons/{$this->moduleName}/api.php",
            array( 'method' => 'getProducts', 'pid' => $pid ),
            'GET',
            ["Auth-Token: ttse"]

        );
    }


    public function getProductsByUserIdAndGID($gid, $userId){
        return $this->doCurlJson(
            $this->app['config.whmcs.path.api'] . "modules/addons/{$this->moduleName}/api.php",
            array( 'method' => 'getProducts', 'gid' => $gid ,'clientid' => $userId),
            'GET',
            ["Auth-Token: ttse"]

        );
    }

    public function getProductsByUserIdAndPID($pid, $userId){
        return $this->doCurlJson(
            $this->app['config.whmcs.path.api'] . "modules/addons/{$this->moduleName}/api.php",
            array( 'method' => 'getProducts', 'pid' => $pid ,'clientid' => $userId),
            'GET',
            ["Auth-Token: ttse"]

        );
    }



    public function doRequest($method, array $args = [])
    {
        return $this->doCurlJson(
            $this->app['config.whmcs.path.api'] . "modules/addons/{$this->moduleName}/api.php",
            array_merge($args, ['method' => $method])
        );
    }

    public function doCallbackRequest($callbackUrl, $method, array $args = [])
    {
        return new RedirectResponse($this->app['config.whmcs.path'] .
            "modules/addons/{$this->moduleName}/api_callback.php?" .
            http_build_query(array_merge(['method' => $method, 'callbackUrl' => $callbackUrl], $args)));
    }

    protected function getRequestPath($url, array $args = [])
    {
        return !empty($args['method']) ? $args['method'] : parent::getRequestPath($url, $args);
    }

    protected function checkJsonResponse(array $data)
    {
        if (!empty($data['error'])) {
            throw new ErrorException(!empty($data['message']) ? $data['message'] : 'Bad response');
        }
    }
}

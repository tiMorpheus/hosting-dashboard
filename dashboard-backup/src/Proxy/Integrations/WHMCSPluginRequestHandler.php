<?php

namespace Proxy\Integrations;

use Buzz\Browser;
use Buzz\Client\Curl;
use Proxy\Integration;
use Silex\Application;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Zend\Json\Server\Exception\ErrorException;

class WHMCSPluginRequestHandler extends Integration
{
    protected $moduleName = 'blazing_proxy_seller';

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
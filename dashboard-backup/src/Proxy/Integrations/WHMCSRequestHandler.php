<?php

namespace Proxy\Integrations;

use Proxy\Integration;

class WHMCSRequestHandler extends Integration
{

    public function doRequest($parameters) {
        return $this->doCurlJson($this->app[ 'config.whmcs.path.api' ] . 'includes/api.php', array_merge([
                'username'     => $this->app[ 'config.whmcs.username' ],
                'password'     => md5($this->app[ 'config.whmcs.password' ]),
                'responsetype' => 'json',
            ], $parameters)
        );
    }

    protected function getRequestPath($url, array $args = [])
    {
        return !empty($args['action']) ? $args['action'] : parent::getRequestPath($url, $args);
    }
}
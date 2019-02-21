<?php

namespace Proxy\Integrations;

use ErrorException;
use Proxy\Integration;

class Amember extends Integration {

    public function verifyUserPass($username, $password) {
        $return = $this->doRequest('check-access/by-login-pass', [
            'login' => $username,
            'pass' => $password
        ]);

        return [
            'success' => (isset($return['ok'])) ? $return['ok'] : false,
            'amember_id' => (isset($return['user_id'])) ? $return['user_id'] : false,
            'email' => (isset($return['email'])) ? $return['email'] : false,
            'subscriptions' => (isset($return['subscriptions'])) ? array_keys($return['subscriptions']) : false,
        ];
    }

    public function checkAccess($email) {
        $return = $this->doRequest('check-access/by-email', [
            'email' => $email
        ]);

        return [
            'success' => (isset($return['ok'])) ? $return['ok'] : false,
            'amember_id' => (isset($return['user_id'])) ? $return['user_id'] : false,
            'email' => (isset($return['email'])) ? $return['email'] : false,
            'subscriptions' => (isset($return['subscriptions'])) ? array_keys($return['subscriptions']) : false,
        ];
    }

    private function doRequest($call, $parameters)
    {
        if (!$this->app[ 'config.amember.apiKey' ]) {
            throw new ErrorException('No Amember API Key is configured');
        }

        return $this->doCurlJson($this->app[ 'config.amember.url.api' ] . "/$call", array_merge($parameters, [
            '_key' => $this->app[ 'config.amember.apiKey' ]
        ]), 'GET');
    }
}
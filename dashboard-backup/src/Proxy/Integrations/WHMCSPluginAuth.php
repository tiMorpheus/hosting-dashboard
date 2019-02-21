<?php

namespace Proxy\Integrations;

use Proxy\Integration;
use Symfony\Component\HttpFoundation\RedirectResponse;

class WHMCSPluginAuth extends Integration
{

    protected $moduleName = 'easy_auth';

    public function authorize($email, $password, $successUrl, $failUrl = null, $pendingText = null)
    {
        $data = [
            'username' => $email,
            'password' => $password,
            'url'      => ['success' => $successUrl],
        ];

        if ($failUrl) {
            $data[ 'url' ][ 'fail' ] = $failUrl;
        }

        if ($pendingText) {
            $data[ 'text' ][ 'pending' ] = $pendingText;
        }

        return $this->generateResponse('do', $data);
    }

    public function unauthorize($url)
    {
        $data = [
            'url' => $url,
        ];

        return $this->generateResponse('logout', $data);
    }

    public function isAuthorized($callbackUrl)
    {
        return $this->generateResponse('check', ['callbackUrl' => $callbackUrl]);
    }

    protected function generateResponse($script, array $data)
    {
        $url = $this->app[ 'config.whmcs.path' ] . "modules/addons/{$this->moduleName}/$script.php?" .
            http_build_query(['data' => base64_encode(json_encode($data))]);

        return new RedirectResponse($url);
    }
}

<?php

namespace Proxy\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Proxy\ApiException;

class WhmcsApiController extends AbstractController {
    protected $headers = [
    ];

    protected function onControllerRequest(callable $controller)
    {
        parent::onControllerRequest($controller);

        $apiKey = $this->request->headers->get('Auth-Token');

        if(!hash_equals($this->app['config.api.whmcs.secret'], $apiKey)) {
            throw new ApiException('API Key not valid');
        }
    }

    public function getCleanBlocksList(Request $request) {
        $response = $this->app['integration.proxy.api']->getBlocks(!$request->get('all') ? true : false);

        if(isset($response['error'])) {
            throw new ApiException($response['message']);
        }

        return new JsonResponse([
            'status' => 'success',
            'blocks' => $response
        ]);
    }

    protected function onControllerException($e) {
        return new JsonResponse([
            'status' => 'error',
            'message' => $e->getMessage()
        ], ($e instanceof ApiException ? $e->getErrorCode() : 500), $this->headers);
    }

    protected function onControllerResponse(Response $response) {
        $response->headers->add($this->headers);
    }
}
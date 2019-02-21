<?php

namespace Proxy\Controller\Announcements;

use Proxy\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Proxy\ApiException;

class MainController extends AbstractController
{
    public function postAction(Request $request, $action)
    {
        $this->app['app.announcement']->enable();

        if (($class = $this->app['app.announcement']->getClass()) !== null) {
            return $class->postAction($request, $action, false, $this->app['session.user']->getDetails('whmcsId'));
        }

        return new JsonResponse([
            'status' => 'error',
            'message' => 'Announcement is not set'
        ]);
    }

    protected function onControllerException($e)
    {
        return new JsonResponse([
            'status' => 'error',
            'message' => $e->getMessage()
        ], ($e instanceof ApiException ? $e->getErrorCode() : 500));
    }
}

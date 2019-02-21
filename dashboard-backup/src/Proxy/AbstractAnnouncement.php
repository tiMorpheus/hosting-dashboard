<?php

namespace Proxy;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

abstract class AbstractAnnouncement
{
    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function postAction(Request $request, $action)
    {
        if ($announcement = $this->app['app.announcement']->get()) {
            $this->app['app.announcement']->remove();

            $response = $this->app['api']->user()->postAnnouncementAction($announcement['id'], $action, false, $this->app['session.user']->getDetails('whmcsId'));

            if ($response['status'] == 'ok') {
                return new JsonResponse([
                    'status' => 'ok'
                ]);
            } else {
                $this->app['logs']->warn('Post announcement\'s action failed', ['response' => $response]);
            }
        }

        return new JsonResponse([
            'status' => 'error'
        ]);
    }

    abstract public function getTemplateData();

    public function beforeRender()
    {
        return true;
    }
}
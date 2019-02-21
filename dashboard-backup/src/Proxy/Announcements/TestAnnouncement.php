<?php

namespace Proxy\Announcements;

use Proxy\AbstractAnnouncement;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class TestAnnouncement extends AbstractAnnouncement
{
    protected $submittedLinks = [];

    public function postAction(Request $request, $action)
    {
        if($action === 'post') {
            return $this->postSubmittedLink($request);
        } else
            return parent::postAction($request, $action);
    }

    protected function postSubmittedLink($request)
    {
        $data = [];
        if (($data['type'] = $request->request->get('type')) && ($data['link'] = $request->request->get('link'))) {
            if ($announcement = $this->app['app.announcement']->get()) {
                $this->app['api']->user()->postAnnouncementAction($announcement['id'], 'post', $data);

                $replyTo = $this->app['session.user']->getDetails('email');
                if (!$this->app['util.helper']->sendEmail(
                        $this->app['config.support.email'],
                        'A new marketing submission is ready for review',
                        "Please, review the following client's post - {$data['link']}\nFrom: ".$replyTo,
                        'announcement_post@sprious.com',
                        'php_mail',
                        $replyTo)
                ) {
                    return new JsonResponse([
                        'status' => 'error',
                        'message' => 'System error! Please try later or contact with us.'
                    ]);
                }

                // $this->app['app.announcement']->remove();

                return new JsonResponse([
                    'status' => 'ok'
                ]);
            }
        }
    }

    public function beforeRender()
    {
        $responses = $this->app['app.announcement']->getResponses('post');

        if (empty($packages = $this->app['session.user']->getPackages())) {
            return false;
        }

        if (!$responses) {
            return true;
        }

        foreach ($responses as $response) {
            if (isset($response['data']['type'])) {
                $this->submittedLinks[] = $response['data']['type'];
            }
        }

        $this->submittedLinks = array_unique($this->submittedLinks);

        // close and show new announcements as there are no options available
        if (count($this->submittedLinks) >= 4) {
            $this->app['api']->user()->postAnnouncementAction($this->app['app.announcement']->get()['id'], 'close');

            $this->app['app.announcement']->next();

            return false;
        }

        return true;
    }

    public function getTemplateData()
    {
        $announcement = $this->app['app.announcement']->get();

        if (isset($announcement['data']['template']) && isset($announcement['data']['items'])) {
            return [
                'template'      => $announcement['data']['template'],
                'submitedLinks' => $this->submittedLinks,
                'items'         => $announcement['data']['items']
            ];
        }

        $this->app['logs']->debug('Announcement loaded, but required parameters \'template\' or(and) \'items\' are missing', [
            'announcement' => $announcement
        ]);

        return [];
    }
}

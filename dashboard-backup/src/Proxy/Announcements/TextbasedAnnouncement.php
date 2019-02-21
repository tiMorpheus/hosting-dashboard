<?php
/**
 * Created by PhpStorm.
 * User: illia
 * Date: 08.05.18
 * Time: 17:27
 */

namespace Proxy\Announcements;


use Proxy\AbstractAnnouncement;

class TextbasedAnnouncement extends AbstractAnnouncement
{
    protected $announcement;

    public function beforeRender()
    {
        $this->announcement = $this->app['app.announcement']->get();

        if (!isset($this->announcement['data']['content'])) {
            $this->app['logs']->debug('Announcement loaded, but required parameter \'content\' is missing', [
                'announcement' => $this->announcement
            ]);

            $this->app['api']->user()->postAnnouncementAction($this->announcement['id'], 'close', 'closed due error occurred', $this->app['session.user']->getDetails('whmcsId'));

            $this->app['app.announcement']->next();

            return false;
        }

        return true;
    }

    public function getTemplateData()
    {
        return [
            'template' => $this->announcement['data']['template'] . '.html.twig',
            'size' => (isset($this->announcement['data']['size']) ? $this->announcement['data']['size'] : 'modal-sm'),
            'heading' => (isset($this->announcement['data']['heading']) ? $this->announcement['data']['heading'] : 'Announcement'),
            'buttonText' => (isset($this->announcement['data']['buttonText']) ? $this->announcement['data']['buttonText'] : 'Close'),
            'content' => $this->announcement['data']['content']
        ];
    }

}
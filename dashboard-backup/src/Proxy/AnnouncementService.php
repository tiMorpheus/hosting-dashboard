<?php

namespace Proxy;

use Silex\Application;

class AnnouncementService
{
    protected $app;

    protected $announcementClass;
    protected $reload = false;
    protected $enabled = false;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function get()
    {
        if ($this->enabled && $this->app['session.user']->isAuthorized() && ($announcement = $this->app[ 'session' ]->get("announcement"))) {
            return $announcement;
        }

        return false;
    }

    public function load()
    {
        $res = $this->app['api']->user()->getAnnouncement( $this->app['session.user']->getDetails('whmcsId'));

        if (is_array($res['announcement'])) {
            $this->app[ 'session' ]->set("announcement", $res['announcement']);
        }
    }

    public function next()
    {
        $this->remove();

        $this->reload = true;

        $this->load();
    }

    public function remove()
    {
        $this->announcementClass = null;

        $this->app['session']->remove("announcement");
    }

    public function getClassName()
    {
        if (!($announcement = $this->get())) {
            return false;
        }

        if (isset($announcement['data']['template'])) {
            if (!preg_match('/([a-zA-Z]*).*/', $announcement['data']['template'], $m)) {
                return false;
            }

            return  __NAMESPACE__ . '\\Announcements\\' . ucfirst($m[1]) . 'Announcement';
        }

        return false;
    }

    public function getClass()
    {
        if($this->announcementClass === null) {
            if (($className = $this->getClassName()) && class_exists($className)) {
                $this->announcementClass = new $className($this->app);
            } else {
                $this->announcementClass = false;
            }
        }

        return $this->announcementClass;
    }

    public function prepare()
    {
        if ($class = $this->getClass()) {
            if ($class->beforeRender()) {
                return true;
            }

            if ($this->reload) {
                $this->reload = false;

                return $this->prepare();
            }
        }

        return false;
    }

    public function enable()
    {
        $this->enabled = true;
    }

    public function disable()
    {
        $this->enabled = false;
    }

    public function getResponses($action = null)
    {
        if (!($announcement = $this->get())) {
            return false;
        }

        $res = $this->app['api']->user()->getAnnouncementResponses($announcement['id'], $action);

        if (isset($res['responses'])) {
            return $res['responses'];
        }

        return false;
    }
}

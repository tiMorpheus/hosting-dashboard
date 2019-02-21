<?php

namespace Proxy;

use Axelarge\ArrayTools\Arr;
use Blazing\Reseller\Api\Api;
use Blazing\Common\RestApiRequestHandler\Exception\BadRequestException;
use Proxy\Util\TFA;
use Silex\Application;
use Symfony\Component\HttpFoundation\Session\Session;

use Proxy\User\Timer;

class User
{

    protected $app;

    /**
     * @var Api
     */
    protected $api;

    protected $packages;

    protected $timer;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->api = $app['api'];
        $this->timer = new Timer($app);

        // Adjust API context
        if ($this->isAuthorized()) {
            $this->api->getContext()->setUserId($this->getId());
            $this->refreshPackages();
        }
    }

    public function isAuthorized()
    {
        return !!$this->app[ 'session' ]->get('userId');
    }

    /**
     * @return Session
     */
    public function getSession()
    {
        return $this->app['session'];
    }

    public function getId()
    {
        return (int) $this->app[ 'session' ]->get('userId');
    }

    public function authorizeById($userId)
    {
        $this->deauthorize();
        $this->app[ 'session' ]->set('userId', $userId);

        // Try to load data and decide if we authorized
        try {
            $this->getDetails();

            // Adjust API context
            $this->api->getContext()->setUserId($this->getId());

            $this->refreshPackages();

            // Adjust logs indexes
            if (!empty($this->app['logs'])) {
                $this->app['logs']->addSharedIndex('userId', $this->getId());
            }
        }
        catch (BadRequestException $e) {
            // User not found
            $this->deauthorize();

            // Just rethrow to exception to be seen$this->getUser()->authorizeUserId()
            throw $e;
        }

        $this->app['app.announcement']->load();

        return $this;
    }

    public function deauthorize()
    {
        $this->app[ 'session' ]->remove('userId');
        $this->refreshData();
    }

    public function refreshData()
    {
        $this->app[ 'session' ]->remove('userData');
        $this->app[ 'session' ]->remove('userWhmcsData');

    }

    public function getDetails($key = null)
    {
        if (!$this->isAuthorized()) {
            return $key ? false : [];
        }

        if (!$this->app[ 'session' ]->get('userData')) {
            $this->app['session']->set('userData', $this->api->user()->getDetails($this->getId()));
        }

        return !$key ?
            $this->app[ 'session' ]->get('userData') :
            Arr::getOrElse($this->app[ 'session' ]->get('userData'), $key);
    }

    public function getWhmcsDetails($key = null)
    {
        if (!$this->isAuthorized()) {
            return $key ? false : [];
        }

        if (!($whmcsId = $this->getDetails('whmcsId'))) {
            return [];
        }

        if (!$this->app[ 'session' ]->get('userWhmcsData')) {
            $this->app['session']->set('userWhmcsData', $this->app['integration.whmcs.api']->getClientDetailsById($whmcsId));
        }

        return !$key ?
            $this->app[ 'session' ]->get('userWhmcsData') :
            Arr::getOrElse($this->app[ 'session' ]->get('userWhmcsData'), $key);
    }

    public function getPackages()
    {
        if (!isset($this->packages)) {
            throw new ErrorException('User packages not provided');
        }

        return $this->packages;
    }

    public function refreshPackages()
    {
        $this->packages = $this->api->packages()->getAll()['list'];

        return $this->packages;
    }

    /**
     * @return TFA|false
     */
    public function getTFA()
    {
//        return !empty($this->app['session.tfa']) ? $this->app['session.tfa'] : false;
        return false;
    }

    public function getTimer()
    {
        return $this->timer;
    }
}

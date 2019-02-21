<?php

namespace Proxy\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Proxy\Integrations\Amember;
use Proxy\Integrations\Hostbill;
use Proxy\Integrations\WHMCS;

class AdminController
{
    public function __construct() {
        $this->WHMCS = new WHMCS();
    }

    public function add(Application $app) {
        $sources = $app['db']->fetchAll("SELECT id, name, ip FROM proxy_source ORDER BY name");
        return $app['twig']->render('admin/add.html.twig', ['sources' => $sources]);
    }

    public function stats(Application $app) {
        return 'stats';
    }
}
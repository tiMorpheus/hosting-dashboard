<?php

namespace Proxy\Controller;

class HomeController extends AbstractController
{
    public function index() {
        if ($this->getUser()->isAuthorized()) {
            return $this->redirectToRoute('dashboard');
        }

//        return $this->app['twig']->render('home/index.html.twig');
        return $this->redirectToRoute('loginType');
    }

    public function searchEngineScraping() {
        return $this->app['twig']->render('home/s_engine_scraping_proxies.html.twig');
    }

    public function ecommerceScraping() {
        return $this->app['twig']->render('home/ecommerce_scraping_proxies.html.twig');
    }
}

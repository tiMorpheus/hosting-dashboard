<?php

namespace Proxy\Silex;

use Silex\Controller;
use Silex\ControllerCollection as BaseControllerCollection;

class ControllerCollection extends BaseControllerCollection
{

    /**
     * Get route by id
     * @param $routeKey
     * @return bool|\Silex\Route
     */
    public function getRoute($routeKey)
    {
        foreach ($this->controllers as $controller) {
            /** @var Controller $controller */
            if ($routeKey == $controller->getRouteName()) {
                return $controller->getRoute();
            }
        }

        return false;
    }
}

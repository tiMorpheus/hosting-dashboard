<?php
/**
 * Created by PhpStorm.
 * User: Illia
 * Date: 07.02.2018
 * Time: 13:42
 */

namespace Proxy\User;

use Silex\Application;

class Timer
{
    protected $app;

    public function __construct(Application $context)
    {
        $this->app = $context;
    }

    public function get($key) {
        $key = md5(json_encode($key));

        if($time = $this->app[ 'session' ]->get("timer$key")) {
            return $time - $this->now();
        }

        return false;
    }

    public function set($key, $timestamp) {
        $key = md5(json_encode($key));

        $this->app[ 'session' ]->set("timer$key", $timestamp);

        return $timestamp - $this->now();
    }

    public function clear($key) {
        $key = md5(json_encode($key));

        $this->app[ 'session' ]->remove("timer$key");
    }

    protected function now() {
        return (new \DateTime(null))->getTimestamp();
    }
}
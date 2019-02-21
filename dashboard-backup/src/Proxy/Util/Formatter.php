<?php

namespace Proxy\Util;

use Silex\Application;

class Formatter
{
    protected $app;

    public function __construct(Application $app = null)
    {
        $this->app = $app;
    }

    public function humanizeProxyName(array $proxy) {
        if (empty($proxy['country']) or empty($proxy['category'])) {
            return 'UNKNOWN';
        }

        if ('static' == $proxy[ 'category' ]) {
            $proxy[ 'category' ] = 'dedicated';
        }

        if ('rotate' == $proxy[ 'category' ]) {
            $proxy[ 'category' ] = 'rotating';
        }

        $translator = $this->app['translator'];

        return $translator->trans("proxy.name", [
            '%type%' =>
                $translator->trans('proxy.country.short.' . $proxy[ 'country' ]) . ' ' .
                $translator->trans('proxy.category.' . $proxy[ 'category' ])
        ]);
    }
}

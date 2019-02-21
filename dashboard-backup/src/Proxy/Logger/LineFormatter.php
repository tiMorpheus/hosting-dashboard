<?php

namespace Proxy\Logger;

use Monolog\Formatter;

class LineFormatter extends Formatter\LineFormatter
{

    protected function convertToString($data)
    {
        return str_replace('\\"', '"', parent::convertToString($data));
    }
}

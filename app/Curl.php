<?php

declare(strict_types=1);

namespace app;

use Amp\ReactAdapter\ReactAdapter;
use KHR\React\Curl\Curl as ReactCurl;

class Curl
{
    protected static $curl;
    
    public static function single() : ReactCurl
    {
        if (static::$curl === null) {
            static::$curl = new ReactCurl(ReactAdapter::get());
            static::$curl->client->setMaxRequest(100);
        }
        
        return static::$curl;
    }
}
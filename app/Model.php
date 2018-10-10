<?php

namespace app;

use Amp\ReactAdapter\ReactAdapter;
use KHR\React\Curl\Curl;
use KHR\React\Curl\Exception;

class Model 
{
    protected static $curl;
    
    protected static function getCurl()
    {
        if (static::$curl === null) {
            static::$curl = new Curl(ReactAdapter::get());
        }
        
        return static::$curl;
    }
    
    protected $active = false;
    protected $model_id;
    protected $album_id;
    protected $recorder;
    
    public function __construct($model_id, $album_id) 
    {
        $this->model_id = $model_id;
        $this->album_id = $album_id;
        $this->recorder = new Recorder($this, static::getCurl());
    }
    
    public function getId()
    {
        return $this->model_id;
    }
    
    public function startPoll()
    {        
        return \Amp\call([$this, 'poll']);
    }
    
    public function poll()
    {
        while (true) {
            $result = yield static::getCurl()->get('https://chaturbate.com/' . $this->model_id . '/')
                ->then(function($result){
                    if (strpos($result->body, '.m3u8') !== false && preg_match('#https://(.*)\.m3u8#', $result->body, $matches)) {
                        return $matches[0];
                    }
                    
                    return false;
                });
                
            try {
                if ($result !== false) {
                    echo $this->model_id . PHP_EOL;
                    yield from $this->recorder->capture($result);
                } else {
                    yield new \Amp\Delayed(500);
                }
            } catch (\Throwable $t) { 
                echo $t->getMessage() . '(' . $t->getFile() . ':' . $t->getLine() . ')' . PHP_EOL; 
                echo $t->getTraceAsString() . PHP_EOL . PHP_EOL;
            }
        }
    }    
    
    public function getPlayList($streamLink)
    {
        $body = yield $this->curl->get($streamLink);
        
        $m3u8 = new M3u8();
        $m3u8->read($body);
        
        return $m3u8;
    }
}
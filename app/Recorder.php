<?php

declare(strict_types=1);

namespace app;

use Chrisyue\PhpM3u8\M3u8;

class Recorder
{
    protected $model;
    
    public function __construct(Model $model)
    {
        $this->model = $model;
    }
    
    public function getModel() : Model
    {
        return $this->model;
    }
    
    public function capture(string $streamLink) : \Generator
    {        
        if (false === $bestStream = yield from $this->getBestStream($streamLink)) {
            return false;
        }
        
        $source      = new stream\Hls($bestStream, $this);
        $destination = new stream\File($this->model);
        
        yield from $destination->pipe($source);
        
        return $destination;
    }
    
    public function getPlayList(string $streamLink) : \Generator
    {
        try  {
            $body = yield Curl::single()->get($streamLink);
            
            $m3u8 = new M3u8();
            $m3u8->read($body);
            
            return $m3u8;
        } catch (\Throwable $t) {}
        
        return false;
    }
    
    protected function getBestStream(string $streamLink) : \Generator
    {
        if (false === $master = yield from $this->getPlayList($streamLink)) {
            return false;
        }
        
        $segments = $master->getSegments();
        
        try {
            $topSegment = $segments->current();
            foreach ($segments as $segment) {
                if ($topSegment === null || $segment->getStreamInfTag()->getBandwidth() > $topSegment->getStreamInfTag()->getBandwidth()) {
                    $topSegment = $segment;
                }
            }        
        } catch (\Throwable $t) {
            $topSegment = null;
        }
        
        return is_object($topSegment) 
            ? dirname($streamLink) . '/' . $topSegment->getUri() 
            : false;
    }
}
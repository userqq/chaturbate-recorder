<?php

namespace app;

use Chrisyue\PhpM3u8\M3u8;

class Recorder
{
    protected $model;
    protected $curl;
    
    public function __construct($model, $curl)
    {
        $this->model = $model;
        $this->curl = $curl;
    }
    
    public function capture($streamLink)
    {
        if (false === $stream = yield from $this->getBestStream($streamLink)) {
            return false;
        }
        
        $stream = new Stream($stream, $this->curl, $this->model);
        yield from $stream->record();
        
        return $stream;
    }
    
    protected function getBestStream($streamLink)
    {
        $master = yield from $this->model->getPlayList($streamLink);
        $segments = $master->getSegments();
        
        $topSegment = $segments->current();
        foreach ($segments as $segment) {
            if ($topSegment === null || $segment->getStreamInfTag()->getBandwidth() > $topSegment->getStreamInfTag()->getBandwidth()) {
                $topSegment = $segment;
            }
        }
        
        return is_object($topSegment) 
            ? dirname($streamLink) . '/' . $topSegment->getUri() 
            : false;
    }
}
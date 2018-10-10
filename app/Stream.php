<?php

namespace app;

use Amp\File;

class Stream
{    
    protected $streamLink;
    protected $model;
    
    protected $dq;    
    protected $fileName;    
    protected $files;
    
    protected $lastSegmentSequence = -1;
    
    public function __construct($streamLink, $model)
    {
        $this->streamLink = $streamLink;
        $this->model = $model;
        
        $this->dq = new \Ds\Deque();        
        $this->files = new Files($model);
    }
    
    protected function handleSegments($media)
    {
        $maxSequenceExists = $this->dq->count() < 1
            ? $this->lastSegmentSequence
            : (int)$this->dq->last()->getMediaSequence();
        
        if ($maxSequenceExists > 0 && $maxSequenceExists < ($media->getMediaSequence() - 1)) {
            return false;
        }
        
        if ($this->dq->count() < 1) {
            foreach ($media->getSegments() as $segment) {
                if ($this->lastSegmentSequence < 0 || $this->lastSegmentSequence + 1 === (int)$segment->getMediaSequence()) {
                    $this->dq->push($segment);
                }
            }
        } else {            
            foreach ($media->getSegments() as $segment) {
                $lastInQ = $this->dq->last();
                if ((int)$lastInQ->getMediaSequence() + 1 === (int)$segment->getMediaSequence()) {
                    $this->dq->push($segment);
                    $lastInQ = $segment;
                }
            }
        }
        
        return true;
    }
    
    public function record()
    {        
        while (true) {
            
            if ((false === $media = (yield from $this->model->getPlayList($this->streamLink))) && $this->dq->count() < 1) {
                if ($file->tell() < 1) {
                    $file->close();
                    File\unlink($this->fileName);
                } else {
                    
                }
                
                return false;
            }        
            
            if ($media === false || $this->handleSegments($media) === false) {
                return false;
            }
            
            if ($this->dq->count() > 0) {
                $segment = $this->dq->shift();
                $segmentUri = dirname($this->streamLink) . '/' . $segment->getUri();
                
                if (false !== (yield from $this->files->write($segmentUri, $segment->getMediaSequence()))) {
                    $this->lastSegmentSequence = $segment->getMediaSequence();
                }

                var_dump($this->lastSegmentSequence);
            } elseif ($media !== false) {
                $sleepTime = ($media->getSegments()->getFirst()->getDuration() * 1000) / 2;
                yield new \Amp\Delayed($sleepTime);
            }
            
        }
    }
}
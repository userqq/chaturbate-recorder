<?php

declare(strict_types=1);

namespace app\stream\hls;

use app\Curl;
use app\Recorder;

use Amp\Deferred;
use Amp\Delayed;
use Amp\Promise;
use Amp\ByteStream\InputStream;
use Chrisyue\PhpM3u8\Segments;
use Ds\Deque;

use function Amp\call;

class Watcher
{
    protected $watchPromise;
    
    protected $streamLink;
    protected $recorder;
    protected $dq;
    protected $lastSegmentSequence = -1;
    
    protected $activeDeferred;
    
    public function __construct(string $streamLink, Recorder $recorder)
    {
        $this->streamLink = $streamLink;
        $this->recorder = $recorder;
        $this->dq = new Deque();        
        $this->activeDeferred = new Deferred();

        $this->watchPromise = call([$this, 'watcher']);
    }
    
    public function watcher() : \Generator
    {
        do {
            while (false !== yield from $this->watch());
        } while ($this->reload());
        
        return;
    }
    
    protected function reload() : \Generator
    {
        echo $this->recorder->getModel()->getId() . ' trying to reload stream ' . PHP_EOL;
        if (false !== $result = yield from $this->recorder->getModel()->checkPage()) {
            if (false !== $bestStream = yield from $this->recorder->getBestStream($result)) {
                echo $this->recorder->getModel()->getId() . ' reloading successfull ' . PHP_EOL;
                
                $dfd = $this->activeDeferred;
                $this->activeDeferred = new Deferred();
                $dfd->resolve($this->activeDeferred);
                
                $this->streamLink = $bestStream;
                return true;
            }
        }
        
        echo $this->recorder->getModel()->getId() . ' reloading failed ' . PHP_EOL;
        
        $this->activeDeferred->fail(new \Exception('Stream ended, no fallback found'));
        
        return false;
    }
    
    protected function watch() : \Generator
    {
        if (false === $media = yield from $this->recorder->getPlayList($this->streamLink)) {
            return false;
        }
            
        $segments = $media->getSegments();            
        if ($segments->count() < 1) {
            return yield new Delayed(500, true);
        }
        
        $maxSequenceExists = $this->dq->count() < 1
            ? $this->lastSegmentSequence
            : (int)$this->dq->last()->getMediaSequence();
        
        if ($maxSequenceExists > 0 && $maxSequenceExists < ($media->getMediaSequence() - 1)) {
            return yield new Delayed(
                (int)round(($media->getSegments()->getFirst()->getDuration() * 1000) / 2), true
            );
        }
        
        $sleep = $this->handleSegments($segments);
        
        if ($this->dq->count() > 0) {
            $dfd = $this->activeDeferred;
            $this->activeDeferred = new Deferred();
            $dfd->resolve(false);
        }
        
        return yield new Delayed($sleep, true);
    }
    
    protected function handleSegments(Segments $segments) : int
    {        
        if ($this->dq->count() < 1) {
            foreach ($segments as $segment) {
                if ($this->lastSegmentSequence < 0 || $this->lastSegmentSequence + 1 === (int)$segment->getMediaSequence()) {
                    $this->dq->push($segment);
                }
            }
        } else {            
            foreach ($segments as $segment) {
                $lastInQ = $this->dq->last();
                if ((int)$lastInQ->getMediaSequence() + 1 === (int)$segment->getMediaSequence()) {
                    $this->dq->push($segment);
                }
            }
        }
        
        return (int)round(($segments->getFirst()->getDuration() * 1000) / 2);
    }
    
    public function getSegment() : \Generator
    {
        if ($this->activeDeferred === null && $this->dq->count() < 1) {
            return false;
        }
        
        if ($this->dq->count() < 1) {
            try {
                do {
                    $resolvedValue = yield $this->activeDeferred->promise();
                } while ($resolvedValue !== false);
            } catch (\Throwable $t) {
                return false;
            }
        }
        
        $segment = $this->dq->shift();
        $this->lastSegmentSequence = $segment->getMediaSequence();
        
        return $segment;
    }
}
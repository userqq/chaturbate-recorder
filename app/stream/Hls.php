<?php

declare(strict_types=1);

namespace app\stream;

use app\Curl;
use app\Recorder;

use Amp\Promise;
use Amp\ByteStream\InputStream;
use Chrisyue\PhpM3u8\Segment;

use function Amp\call;

class Hls implements InputStream
{    
    const REPEAT_COUNT = 5;

    protected $streamLink;
    protected $watcher;
    
    public function __construct(string $streamLink, Recorder $recorder)
    {
        $this->streamLink = $streamLink;
        $this->watcher = new hls\Watcher($streamLink, $recorder);
    }
    
    public function read() : Promise
    {
        return call([$this, 'call']);
    }
    
    public function call() : \Generator
    {
        if (false !== $segment = yield from $this->watcher->getSegment()) {            
            return yield from $this->data($segment);                            
        }
        
        return null;
    }
    
    protected function data(Segment $segment) : \Generator
    {
        for ($i = 0; $i < static::REPEAT_COUNT; $i++) {
            try {
                $segmentUri = dirname($this->streamLink) . '/' . $segment->getUri();
                
                $response = yield Curl::single()->get($segmentUri);
                
                return $response->body;
                
            } catch (\Throwable $t) {}
        }
        
        return null;
    }
}
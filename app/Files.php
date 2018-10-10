<?php

namespace app;

use Amp\File;
use function Amp\ByteStream\pipe;

class Files
{
    const MAX_FILE_SIZE = 4.5 * 1000 * 1000 * 1000;
    
    protected static $client;
    
    protected static function getClient() 
    {
        if (static::$client === null) {
            static::$client = new \Amp\Artax\DefaultClient;
        }
        
        return static::$client;
    }
    
    protected $model;
    protected $files;
    
    public function __construct($model) 
    {
        $this->model = $model;
        $this->files = new \Ds\Deque();
    }
    
    public function write($segmentUri)
    {
        if ($this->files->count() < 1 || $this->files->last()->tell() > static::MAX_FILE_SIZE) {
            $this->files->push($this->getNewFile());
        }
        
        $file = $this->files->last();
        
        $position = $file->tell();
        
        try {
            $response = yield static::getClient()
                ->request($segmentUri);
                
            $length = (int)$response->getHeader('Content-Length');
                
            if ($length !== (yield pipe($response->getBody(), $file))) {
                throw new \Exception('Write error. Length written is not equal to Content-Length');
            }
            
        } catch (\Throwable $t) {
            yield $file->close();
            
            yield \Amp\ParallelFunctions\parallel(function () use ($position) {
                $fh = fopen($this->fileName, 'c');
                ftruncate($fh, $position);
                fclose($fh);
            })();
            
            $file = yield File\open($this->fileName, 'c');
            $file->seek(0, SEEK_END);
            
            return false;
        }
        
        return true;
    }
    
    public function end()
    {
        if ($this->files->count() > 0) {
            $file = $this->files->last();
            
            if ($file->tell() < 1) {
                $file->close();
                File\unlink($this->fileName);
            } else {
                
            }
        }
    }
    
    public function getNewFile()
    {
        $filename = '/srv/80.241.220.222/streams/' . $this->model->getId() . '_' . time();
        
        return new class ($filename) {
            protected $fileName;
            protected $currentSequence;
            protected $sequencesLength = 0;
            protected $file;
            
            public function __construct($filename, $file) {
                $this->fileName = $filename;
                $this->file = yield File\open($this->fileName, 'c');
            $file->seek(0, SEEK_END);
            }
            
            public function setCurrentSequence($sequence) {
                $this->currentSequence = $sequence;
            }
            
            public function getFileName() {
                return $this->fileName;
            }
            
            public function tell() {
                return $this->file->tell();
            }
            
            public function getFile() {
                return $this->file;
            }
        };
    }
}
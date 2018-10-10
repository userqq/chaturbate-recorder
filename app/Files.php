<?php

namespace app;

class Files
{
    protected $model;
    protected $files;
    
    public function __construct($model) 
    {
        $this->model = $model;
        $this->files = new \Ds\Deque();
    }
    
    public function write($segmentUri) 
    {
        if ($this->files->count() < 1 || $this->files->last()->tell() > 4.5 * 1000 * 1000 * 1000) {
            $this->files->push($this->getNewFile());
        }
        
        $file = $this->files->last()->getFile();
        
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
    
    public function getNewFile()
    {
        $filename = '/srv/80.241.220.222/streams/' . $this->model->getId() . '_' . time();
        
        return new class ($filename) {
            protected $fileName;
            protected $currentSequence;
            protected $sequencesLength = 0;
            protected $file;
            
            public function __construct($filename) {
                $this->fileName = $filename;
            }
            
            public function setCurrentSequence($sequence) {
                $this->currentSequence = $sequence;
            }
            
            public function getFileName() {
                return $this->fileName;
            }
            
            public function getFile() {
                
            }
        };
    }
}
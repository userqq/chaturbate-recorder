<?php

declare(strict_types=1);

namespace app\stream;

use app\Model;

use Amp\Promise;
use Amp\ByteStream\InputStream;
use Amp\ByteStream\OutputStream;

use function Amp\call;

class File implements OutputStream
{
    const MAX_FILE_SIZE = 4.5 * 1000 * 1000 * 1000;
    
    protected $model;
    protected $fileName;
    protected $length = 0;
    
    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->fileName = $this->createFilename();
    }
    
    protected function createFilename() : string
    {
        return env('TEMPORARY_PATH') . $this->model->getId() . '_' . time() . '.mp4';
    }
    
    protected function next() : void
    {        
        $this->end();
        $this->fileName = $this->createFilename();
        $this->length = 0;
    }
    
    public function pipe(InputStream $source) : \Generator
    {
        $written = 0;
        while (null !== $chunk = yield $source->read()) {            
            $chunkLength = \strlen($chunk);
            $written += $chunkLength;
            $this->length += $chunkLength;
            
            $writePromise = $this->write($chunk);
            $chunk = null;
            yield $writePromise;
            
            if ($this->length > static::MAX_FILE_SIZE) {
                $this->next();
            }
        }
        
        $this->end();
        
        return $written;
    }
    
    public function write(string $data) : Promise
    {
        return call([$this, 'callWrite'], $data);
    }
    
    public function callWrite(string $content) : \Generator
    {
        try {
            $file = yield \Amp\File\open($this->fileName, 'c');
        } catch (\Throwable $t) {
            echo 'Unable to open file: "' . $this->fileName . '"' . PHP_EOL;
            return false;
        }
        
        try {
            yield $file->seek(0, SEEK_END);
        } catch (\Throwable $t) {
            echo 'Unable to seek file: "' . $this->fileName . '"' . PHP_EOL;
            yield $file->close();
            return false;
        }        
        
        try {        
            $written = yield $file->write($content);
        } catch (\Throwable $t) {
            echo 'Unable to write into file: "' . $this->fileName . '"' . PHP_EOL;
            yield $file->close();
            return false;
        }
        
        yield $file->close();
        
        return $written;
    }
    
    public function end(string $finalData = "") : Promise
    {
        return call([$this, 'callEnd'], $finalData);
    }
    
    public function callEnd($finalData = "") : void
    {
        if ($this->length > 0) {
            $this->model->pushUpload($this->fileName);
        }
    }
}
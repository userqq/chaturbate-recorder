<?php

declare(strict_types=1);

namespace app;

use Amp\Promise;
use Amp\ReactAdapter\ReactAdapter;
use Chrisyue\PhpM3u8\M3u8;
use Clue\React\Mq\Queue;
use React\Promise\Deferred as ReactDeferred;
use React\Promise\Promise as ReactPromise;

use function Amp\call;

class Model 
{
    public static function fromJson(string $path) : array
    {
        $models = json_decode(file_get_contents($path), true);
        
        $v = [];
        foreach ($models as $key => $value) {
            $v[$key] = new Model($key, $value);
        }
        
        return $v;
    }    
    
    protected static $uploadQ;
    
    protected static function getUploadQ() : Queue
    {
        if (static::$uploadQ === null) {
            static::$uploadQ = new Queue(3, null, [static::class, 'qCallback']);
        }
        
        return static::$uploadQ;
    }
    
    public static function qCallback(string $file, Model $model) : ReactPromise
    {        
        $deferred = new ReactDeferred();
        
        static::upload($file, $model)
            ->onResolve(function ($error, $response) use ($deferred) {
                if ($error !== null) {
                    return $deferred->reject($error);
                }
            
                return $deferred->resolve($response);
            });
        
        return $deferred->promise();
    }
    
    protected $model_id;
    protected $album_id;
    protected $recorder;
    
    public function __construct(string $model_id, int $album_id) 
    {
        $this->model_id = $model_id;
        $this->album_id = $album_id;
        $this->recorder = new Recorder($this);
    }
    
    public function getId() : string
    {
        return $this->model_id;
    }
    
    public function getAlbumId() : int
    {
        return $this->album_id;
    }
    
    public function startPoll() : Promise
    {        
        return call([$this, 'poll']);
    }
    
    public function poll() : \Generator
    {        
        while (true) {                
            try {
                
                if (false !== $result = yield from $this->checkPage()) {
                    
                    echo date('Y-m-d H:i:s') . ' > ' . $this->model_id . ' online ' . PHP_EOL;
                    $r = yield from $this->recorder->capture($result);
                    echo date('Y-m-d H:i:s') . ' > ' .  $this->model_id . ' gone ' . PHP_EOL;
                    
                    continue;
                }
                
                yield new \Amp\Delayed(500);
                
            } catch (\KHR\React\Curl\Exception $e) {
                echo $e->getMessage() . '(' . $e->getFile() . ':' . $e->getLine() . ')' . PHP_EOL; 
                echo $e->getTraceAsString() . PHP_EOL . PHP_EOL;
                exit();
            } catch (\Throwable $t) {            
                echo $t->getMessage() . '(' . $t->getFile() . ':' . $t->getLine() . ')' . PHP_EOL; 
                echo $t->getTraceAsString() . PHP_EOL . PHP_EOL;
            }
        }
    }
    
    public function checkPage() : \Generator
    {
        return yield Curl::single()->get('https://chaturbate.com/' . $this->model_id . '/')
            ->then(function($result){
                if (strpos($result->body, '.m3u8') !== false && preg_match('#https://(.*)\.m3u8#', $result->body, $matches)) {
                    return $matches[0];
                }
                
                return false;
            });
    }
    
    public function pushUpload(string $file) : ReactPromise
    {
        $q = static::getUploadQ();
        
        $q($file, $this);
    }
    
    protected static function upload(string $file, Model $model) : Promise
    {
        $uploader = new Uploader($file, $model);
        
        return call([$uploader, 'run']);
    }
}
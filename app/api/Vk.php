<?php

declare(strict_types=1);

namespace app\api;

use app\Curl;

use Amp\Artax\DefaultClient as Artax;

class Vk
{
    public static function call(string $method, ?array $args = null) : \Amp\Coroutine
    {
        $request = new static($method);
        
        if (is_array($args)) {
            $request->withArgs($args);
        }
        
        return \Amp\call([static::class, 'ampCallback'], $request);
    }
    
    public static function ampCallback(Vk $request) : \Generator
    {
        do {
            try {
                $result = yield $request->createRequest();
            } catch (\Exception $e) {
                return false;
            }
                        
            if (isset($result->json->response)) {
                return $result->json->response;
            }
            
            if (isset($result->json->error) && isset($result->json->error->error_code)) {
                switch ((int)$result->json->error->error_code) {
                    case 6: 
                        continue;
                        break;
                    default:
                        $error = $result->json->error;
                        throw new \Exception($error->error_msg, $error->error_code);
                        break;
                }
            }
            
            throw new \Exception('Mailformed response, no "response" or "error" received: ' . PHP_EOL . var_export($result->json, true) . PHP_EOL);
        } while (true);
    }
    
    protected $method;
    protected $args = [];
    
    public function __construct(string $method, bool $withToken = true)
    {
        $this->method = $method;
        
        $this->args['v'] = '5.80';
        
        if (env('ACCESS_TOKEN') !== false && $withToken) {
            $this->args['access_token'] = env('ACCESS_TOKEN');
        }
    }
    
    public function withArgs(array $args) : Vk
    {
        $this->args = array_merge($this->args, $args);
        
        return $this;
    }
    
    public function createRequest() : \React\Promise\Promise
    {
        $uri = 'https://api.vk.com/method/' . $this->method;
        
        return Curl::single()->post($uri, $this->args);
    }
}
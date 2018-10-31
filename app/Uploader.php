<?php

namespace app;

use Amp\Artax\DefaultClient as Artax;

class Uploader
{    
    protected $file;
    protected $model;
    
    public function __construct($file, $model)
    {
        $this->file = $file;
        $this->model = $model;
    }
    
    public function run()
    {        
        echo 'will upload file ' . $this->file . ' for model ' . $this->model->getId() . PHP_EOL;
    
        try {
            if (false !== $uploadResponse = yield from $this->getUpload()) {
                if (false !== $uploadedResponse = yield from $this->startUpload($uploadResponse)) {
                    return yield from $this->moveToAlbum($uploadedResponse);
                }
            }
        } catch (\Throwable $t) {
            echo $t->getMessage() . PHP_EOL;
            echo $t->getFile() . ':' . $t->getLine() . PHP_EOL;
        }
        
        return false;
    }
    
    protected function moveToAlbum($uploadedResponse)
    {
        try {
            $response = yield api\Vk::call('video.addToAlbum', [
                'target_id'    => '-170121652',
                'album_id'     => $this->model->getAlbumId(),
                'owner_id'     => '-170121652',
                'video_id'     => $uploadedResponse->video_id,
            ]);
        
            return $response;
            
        } catch (\Throwable $t) {
            echo $t->getMessage() . PHP_EOL;
        }
        
        return false;
    }
    
    protected function startUpload($uploadResponse)
    {        
        $result = yield \app\Curl::single()->post($uploadResponse->upload_url, [
            'video_file' => curl_file_create($this->file)
        ], [CURLOPT_TIMEOUT => 0]);
        
        if (isset($result->json->size)) {
            yield \Amp\File\unlink($this->file);
            return $result->json;
        }
        
        return false;
    }
    
    protected function getUpload()
    {
        try {
            $response = yield api\Vk::call('video.save', [
                'album_id'     => $this->model->getAlbumId(),
                'name'         => basename($this->file, '.' . pathinfo($this->file, PATHINFO_EXTENSION)),
                'group_id'     => '170121652',
            ]);
        
            return $response;
            
        } catch (\Throwable $t) {
            echo $t->getMessage() . PHP_EOL;
        }
        
        return false;
    }
}
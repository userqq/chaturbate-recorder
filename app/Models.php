<?php

namespace app;

class Models
{
    public static function fromJson($path)
    {
        $models = json_decode(file_get_contents('models.json'), true);
        
        $v = [];
        foreach ($models as $key => $value) {
            $v[$key] = new Model($key, $value);
        }
        
        return $v;
    }
}
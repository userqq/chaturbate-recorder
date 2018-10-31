#!/usr/bin/php -dextension=eio.so
<?php

require 'vendor/autoload.php';

\Amp\Loop::run(function () {   
    $models = app\Model::fromJson('models.json');
    
    foreach ($models as $model) {
        $model->startPoll();
        yield new Amp\Delayed(300);
    }
    
});

function env(string $key) : string {
    static $dotenv = null;
    
    if ($dotenv === null) {
        $dotenv = new Dotenv\Dotenv(__DIR__);
        $dotenv->load();
        $dotenv->required(['ACCESS_TOKEN', 'TEMPORARY_PATH']);
    }
    
    return getenv($key);
}

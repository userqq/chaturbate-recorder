<?php

require 'vendor/autoload.php';

\Amp\Loop::run(function () {   
    $models = app\Model::fromJson('models.json');
    
    foreach ($models as $model) {
        $model->startPoll();
        yield new Amp\Delayed(300);
    }
    
});


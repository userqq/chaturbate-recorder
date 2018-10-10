<?php

require 'vendor/autoload.php';

\Amp\Loop::run(function () {   
    $models = app\Models::fromJson('models.json');
    
    foreach ($models as $model) {
        $model->startPoll();
        yield new Amp\Delayed(300);
    }
    
});


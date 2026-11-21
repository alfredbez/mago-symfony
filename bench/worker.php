<?php

declare(strict_types=1);

use AlfredBez\MagoSymfony\SymfonyExtension;
use Mago\Sdk\Worker;

require dirname(__DIR__) . '/vendor/autoload.php';

if (getenv('MAGO_SYMFONY_CONTAINER_MAP') === false || getenv('MAGO_SYMFONY_CONTAINER_MAP') === '') {
    putenv('MAGO_SYMFONY_CONTAINER_MAP=' . __DIR__ . '/container-map.json');
}

(new Worker(SymfonyExtension::create()))->run();

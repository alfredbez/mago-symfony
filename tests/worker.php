<?php

declare(strict_types=1);

use AlfredBez\MagoSymfony\SymfonyExtension;
use Mago\Sdk\Worker;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Worker(SymfonyExtension::create()))->run();

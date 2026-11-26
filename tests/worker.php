<?php

declare(strict_types=1);

use AlfredBez\MagoSymfony\SymfonyExtension;
use Mago\Sdk\Worker;

ini_set('display_errors', 'stderr');

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    (new Worker(SymfonyExtension::create()))->run();
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    fflush(STDERR);
    exit(1);
}

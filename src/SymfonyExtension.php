<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony;

use AlfredBez\MagoSymfony\Analyzer\SymfonyPlugin;
use Mago\Sdk\Extension;

final class SymfonyExtension
{
    public const VERSION = '0.1.0';

    private function __construct() {}

    public static function create(): Extension
    {
        return new Extension(
            identifier: 'alfredbez/mago-symfony',
            name: 'Symfony',
            version: self::VERSION,
            analyzerPlugins: [new SymfonyPlugin()],
        );
    }
}

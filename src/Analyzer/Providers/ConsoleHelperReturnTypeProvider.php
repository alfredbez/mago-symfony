<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

/**
 * Narrows HelperSet::get() and Command::getHelper() for Symfony Console's built-in
 * default helpers registered by Application::getDefaultHelperSet().
 */
final class ConsoleHelperReturnTypeProvider implements MethodReturnTypeProvider
{
    private const DEFAULT_HELPERS = [
        'formatter' => 'Symfony\\Component\\Console\\Helper\\FormatterHelper',
        'debug_formatter' => 'Symfony\\Component\\Console\\Helper\\DebugFormatterHelper',
        'process' => 'Symfony\\Component\\Console\\Helper\\ProcessHelper',
        'question' => 'Symfony\\Component\\Console\\Helper\\QuestionHelper',
    ];

    /** @var array<string, Type> */
    private array $typeCache = [];

    public function getTargets(): array
    {
        return [
            MethodTarget::exact('Symfony\\Component\\Console\\Helper\\HelperSet', 'get'),
            MethodTarget::exact('Symfony\\Component\\Console\\Command\\Command', 'getHelper'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $name = $context->invocation->getArgument(0, 'name')?->type?->getLiteralString();
        if ($name === null || !isset(self::DEFAULT_HELPERS[$name])) {
            return null;
        }

        $class = self::DEFAULT_HELPERS[$name];
        if (!$context->codebase->classExists($class)) {
            return null;
        }

        return $this->typeCache[$class] ??= Type::namedObject($class);
    }
}

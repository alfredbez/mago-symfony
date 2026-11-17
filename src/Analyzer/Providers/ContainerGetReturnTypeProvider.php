<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

/**
 * ContainerInterface::get() returns ?object, so every call loses its type.
 *
 * Two sources recover it:
 * - a literal class-string id, which names the service class directly
 * - a service map for string ids such as "doctrine" or "router"
 */
final class ContainerGetReturnTypeProvider implements MethodReturnTypeProvider
{
    private const RUNTIME_EXCEPTION_ON_INVALID_REFERENCE = 0;
    private const EXCEPTION_ON_INVALID_REFERENCE = 1;

    public function __construct(private readonly ServiceClassMap $services) {}

    public function getTargets(): array
    {
        return [
            MethodTarget::exact('Symfony\\Component\\DependencyInjection\\ContainerInterface', 'get'),
            MethodTarget::exact('Symfony\\Component\\DependencyInjection\\Container', 'get'),
            MethodTarget::exact('Psr\\Container\\ContainerInterface', 'get'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $id = $context->invocation->getArgument(0, 'id')?->type?->getLiteralString();
        if ($id === null) {
            return null;
        }

        $serviceClass = $this->resolveServiceClass($id, $context);
        if ($serviceClass === null) {
            return null;
        }

        $serviceType = Type::namedObject($serviceClass);

        $invalidBehavior = $context->invocation->getArgument(1, 'invalidBehavior');
        if ($invalidBehavior === null) {
            return $serviceType;
        }

        $literalBehavior = $invalidBehavior->type?->getLiteralInt();
        if (
            $literalBehavior === self::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE
            || $literalBehavior === self::EXCEPTION_ON_INVALID_REFERENCE
        ) {
            return $serviceType;
        }

        // NULL_ON_INVALID_REFERENCE, IGNORE_ON_INVALID_REFERENCE, IGNORE_ON_UNINITIALIZED_REFERENCE,
        // or a non-literal int can return null at runtime.
        return Type::union($serviceType, Type::null());
    }

    private function resolveServiceClass(string $id, ReturnTypeProviderContext $context): ?string
    {
        if ($context->codebase->classOrInterfaceExists($id)) {
            return $id;
        }

        $class = $this->services->classFor($id);
        if ($class === null || !$context->codebase->classOrInterfaceExists($class)) {
            return null;
        }

        return $class;
    }
}

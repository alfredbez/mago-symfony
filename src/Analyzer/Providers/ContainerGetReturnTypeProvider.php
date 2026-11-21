<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\IntegerType;
use Mago\Sdk\Analyzer\Type\IntegerTypeKind;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\Analyzer\Type\ScalarType;

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
    private const CONTAINER_BUILDER = 'Symfony\\Component\\DependencyInjection\\ContainerBuilder';
    private const LOWER_CONTAINER_BUILDER = 'symfony\\component\\dependencyinjection\\containerbuilder';

    private const FLAG_ALWAYS_THROWS = 1;
    private const FLAG_BUILDER_ONLY = 2;
    private const FLAG_NEVER_THROWS = 3;

    private const EXACT_NON_BUILDER_CONTAINERS = [
        'Symfony\\Component\\DependencyInjection\\Container' => true,
        'Symfony\\Component\\DependencyInjection\\ContainerInterface' => true,
        'Psr\\Container\\ContainerInterface' => true,
        'Symfony\\Component\\DependencyInjection\\ServiceLocator' => true,
        'Symfony\\Contracts\\Service\\ServiceProviderInterface' => true,
    ];

    private const NON_BUILDER_CONTAINERS = [
        'symfony\\component\\dependencyinjection\\container' => true,
        'symfony\\component\\dependencyinjection\\containerinterface' => true,
        'psr\\container\\containerinterface' => true,
        'symfony\\component\\dependencyinjection\\servicelocator' => true,
        'symfony\\contracts\\service\\serviceproviderinterface' => true,
    ];

    /** @var array<string, Type> */
    private array $serviceTypes = [];

    /** @var array<string, Type> */
    private array $nullableServiceTypes = [];

    private ?Type $nullType = null;
    private ?Type $containerBuilderType = null;
    private ?Type $throwingDefaultType = null;
    private ?Type $throwingBuilderType = null;

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

        $invalidBehavior = $context->invocation->getArgument(1, 'invalidBehavior');
        if ($invalidBehavior === null) {
            return $this->serviceType($serviceClass);
        }

        $flagType = $invalidBehavior->type;
        if ($flagType === null) {
            return $this->nullableServiceType($serviceClass);
        }

        $classification = self::classifyBehaviorFlag($flagType);
        if ($classification === self::FLAG_ALWAYS_THROWS) {
            return $this->serviceType($serviceClass);
        }

        if ($classification === self::FLAG_NEVER_THROWS) {
            return $this->nullableServiceType($serviceClass);
        }

        $isBuilder = $this->isContainerBuilder($context);
        if ($classification === self::FLAG_BUILDER_ONLY) {
            return $isBuilder ? $this->serviceType($serviceClass) : $this->nullableServiceType($serviceClass);
        }

        $throwing = $isBuilder ? $this->throwingBuilderType() : $this->throwingDefaultType();
        if ($context->types->isContainedBy($flagType, $throwing)) {
            return $this->serviceType($serviceClass);
        }

        // Container returns null for flag 0, despite ContainerInterface's conditional PHPDoc.
        return $this->nullableServiceType($serviceClass);
    }

    private function resolveServiceClass(string $id, ReturnTypeProviderContext $context): ?string
    {
        $mapped = $this->services->classFor($id);
        if ($mapped !== null) {
            return $context->codebase->classOrInterfaceExists($mapped) ? $mapped : null;
        }

        if ($id === '' || str_contains($id, '.')) {
            return null;
        }

        return $context->codebase->classOrInterfaceExists($id) ? $id : null;
    }

    private function serviceType(string $serviceClass): Type
    {
        return $this->serviceTypes[$serviceClass] ??= Type::namedObject($serviceClass);
    }

    private function nullableServiceType(string $serviceClass): Type
    {
        return $this->nullableServiceTypes[$serviceClass] ??= Type::union(
            $this->serviceType($serviceClass),
            $this->nullType ??= Type::null(),
        );
    }

    private function isContainerBuilder(ReturnTypeProviderContext $context): bool
    {
        $receiver = $context->invocation->receiverType;
        if ($receiver === null) {
            return false;
        }

        if (count($receiver->atomicTypes) === 1) {
            $atomic = $receiver->atomicTypes[0];
            if ($atomic instanceof NamedObjectType && $atomic->intersections === null) {
                $name = $atomic->name;
                if ($name === self::CONTAINER_BUILDER) {
                    return true;
                }

                if (isset(self::EXACT_NON_BUILDER_CONTAINERS[$name])) {
                    return false;
                }

                $lower = strtolower($name);
                if ($lower === self::LOWER_CONTAINER_BUILDER) {
                    return true;
                }

                if (isset(self::NON_BUILDER_CONTAINERS[$lower])) {
                    return false;
                }

                foreach ($context->codebase->getClassAncestors($name) as $ancestor) {
                    if ($ancestor === self::CONTAINER_BUILDER || strtolower($ancestor) === self::LOWER_CONTAINER_BUILDER) {
                        return true;
                    }
                }

                return false;
            }
        }

        return $context->types->isContainedBy(
            $receiver,
            $this->containerBuilderType ??= Type::namedObject(self::CONTAINER_BUILDER),
        );
    }

    private static function classifyBehaviorFlag(Type $type): ?int
    {
        $hasZero = false;
        foreach ($type->atomicTypes as $atomic) {
            if (!$atomic instanceof ScalarType || !$atomic->refinement instanceof IntegerType) {
                return null;
            }

            $intType = $atomic->refinement;
            if ($intType->kind === IntegerTypeKind::Literal) {
                if ($intType->minimum === self::EXCEPTION_ON_INVALID_REFERENCE) {
                    continue;
                }
                if ($intType->minimum === self::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE) {
                    $hasZero = true;
                    continue;
                }

                return self::FLAG_NEVER_THROWS;
            }

            if ($intType->kind === IntegerTypeKind::Range) {
                if (
                    $intType->minimum < self::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE
                    || $intType->maximum > self::EXCEPTION_ON_INVALID_REFERENCE
                ) {
                    return self::FLAG_NEVER_THROWS;
                }
                if ($intType->minimum === self::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE) {
                    $hasZero = true;
                }
                continue;
            }

            return self::FLAG_NEVER_THROWS;
        }

        return $hasZero ? self::FLAG_BUILDER_ONLY : self::FLAG_ALWAYS_THROWS;
    }

    private function throwingDefaultType(): Type
    {
        return $this->throwingDefaultType ??= Type::literalInt(self::EXCEPTION_ON_INVALID_REFERENCE);
    }

    private function throwingBuilderType(): Type
    {
        return $this->throwingBuilderType ??= Type::union(
            $this->throwingDefaultType(),
            Type::literalInt(self::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE),
        );
    }
}

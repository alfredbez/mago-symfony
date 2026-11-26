<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\ArrayItem;
use Mago\Sdk\Analyzer\Type\ArrayKey;
use Mago\Sdk\Analyzer\Type\ArrayKeyKind;
use Mago\Sdk\Analyzer\Type\FloatType;
use Mago\Sdk\Analyzer\Type\FloatTypeKind;
use Mago\Sdk\Analyzer\Type\KeyedArrayType;
use Mago\Sdk\Analyzer\Type\ListElement;
use Mago\Sdk\Analyzer\Type\ListType;
use Mago\Sdk\Analyzer\Type\ScalarType;
use Mago\Sdk\Analyzer\Type\ScalarTypeKind;

/**
 * Resolves parameter types for ContainerInterface::getParameter(),
 * ParameterBagInterface::get(), and AbstractController::getParameter() from
 * loaded container XML or JSON metadata.
 *
 * @phpstan-import-type ParameterDescriptor from ContainerXmlLoader
 *
 * @internal
 */
final class ParameterReturnTypeProvider implements MethodReturnTypeProvider
{
    /** @var array<string, Type|null> */
    private array $resolvedTypes = [];

    private ?Type $arrayKeyType = null;

    public function __construct(private readonly ServiceClassMap $container) {}

    public function getTargets(): array
    {
        return [
            MethodTarget::exact('Symfony\\Component\\DependencyInjection\\ParameterBag\\ParameterBagInterface', 'get'),
            MethodTarget::exact('Symfony\\Component\\DependencyInjection\\ContainerInterface', 'getParameter'),
            MethodTarget::exact('Symfony\\Component\\DependencyInjection\\Container', 'getParameter'),
            MethodTarget::exact('Symfony\\Bundle\\FrameworkBundle\\Controller\\AbstractController', 'getParameter'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $name = $context->invocation->getArgument(0, 'name')?->type?->getLiteralString();
        if ($name === null) {
            return null;
        }

        if (array_key_exists($name, $this->resolvedTypes)) {
            return $this->resolvedTypes[$name];
        }

        $descriptor = $this->container->parameterFor($name);
        if ($descriptor === null) {
            return null;
        }

        return $this->resolvedTypes[$name] = $this->descriptorToType($descriptor, $context);
    }

    /** @param ParameterDescriptor $descriptor */
    private function descriptorToType(array $descriptor, ReturnTypeProviderContext $context): ?Type
    {
        return match ($descriptor['kind']) {
            'null' => Type::null(),
            'bool' => ($descriptor['value'] ?? false) ? Type::true() : Type::false(),
            'int' => Type::literalInt((int) ($descriptor['value'] ?? 0)),
            'float' => Type::fromAtomic(new ScalarType(
                ScalarTypeKind::Float,
                new FloatType(FloatTypeKind::Literal, (float) ($descriptor['value'] ?? 0.0)),
            )),
            'string' => Type::literalString((string) ($descriptor['value'] ?? '')),
            'type' => $this->namedScalarOrCompoundType((string) ($descriptor['type'] ?? 'mixed')),
            'constant' => $this->resolveConstantType((string) ($descriptor['name'] ?? ''), $context),
            'list' => $this->buildListType($descriptor['items'] ?? [], $context),
            'map' => $this->buildMapType($descriptor['items'] ?? [], $context),
            default => null,
        };
    }

    private function namedScalarOrCompoundType(string $name): ?Type
    {
        return match ($name) {
            'int' => Type::int(),
            'float' => Type::float(),
            'bool' => Type::bool(),
            'string' => Type::string(),
            'non-empty-string' => Type::nonEmptyString(),
            'array' => Type::array($this->arrayKeyType ??= Type::union(Type::int(), Type::string()), Type::mixed()),
            default => null,
        };
    }

    private function resolveConstantType(string $name, ReturnTypeProviderContext $context): ?Type
    {
        if ($name === '') {
            return null;
        }

        if (str_contains($name, '::')) {
            [$class, $const] = explode('::', $name, 2);
            $meta = $context->codebase->getClassConstant($class, $const);

            return $meta?->inferredType ?? $meta?->type?->type ?? $meta?->declaredType?->type;
        }

        $meta = $context->codebase->getConstant($name);

        return $meta?->inferredType ?? $meta?->type?->type;
    }

    /** @param array<int|string, mixed> $items */
    private function buildListType(array $items, ReturnTypeProviderContext $context): Type
    {
        if ($items === []) {
            return Type::fromAtomic(new ListType(Type::never(), [], 0, false));
        }

        $known = [];
        $elementTypes = [];
        $index = 0;
        foreach ($items as $item) {
            $itemType = is_array($item) ? ($this->descriptorToType($item, $context) ?? Type::mixed()) : Type::mixed();
            $known[] = new ListElement($index, false, $itemType);
            $elementTypes[] = $itemType;
            $index++;
        }

        $elementUnion = count($elementTypes) === 1
            ? $elementTypes[0]
            : Type::union($elementTypes[0], $elementTypes[1], ...array_slice($elementTypes, 2));

        return Type::fromAtomic(new ListType($elementUnion, $known, count($known), true));
    }

    /** @param array<int|string, mixed> $items */
    private function buildMapType(array $items, ReturnTypeProviderContext $context): Type
    {
        $known = [];
        foreach ($items as $key => $item) {
            $arrayKey = is_int($key)
                ? new ArrayKey(ArrayKeyKind::Integer, $key)
                : new ArrayKey(ArrayKeyKind::String, (string) $key);
            $itemType = is_array($item) ? ($this->descriptorToType($item, $context) ?? Type::mixed()) : Type::mixed();
            $known[] = new ArrayItem($arrayKey, false, $itemType);
        }

        return Type::fromAtomic(new KeyedArrayType($known, null, null, $known !== []));
    }
}

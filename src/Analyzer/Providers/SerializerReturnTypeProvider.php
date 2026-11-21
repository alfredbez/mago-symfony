<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\ClassLikeStringType;
use Mago\Sdk\Analyzer\Type\ClassLikeStringVariant;
use Mago\Sdk\Analyzer\Type\ScalarType;

/**
 * Resolves the $type argument of SerializerInterface::deserialize() and
 * DenormalizerInterface::denormalize() (including 'Foo[]' and 'Foo[][]' array notation)
 * to concrete object or array types.
 */
final class SerializerReturnTypeProvider implements MethodReturnTypeProvider
{
    public function getTargets(): array
    {
        return [
            MethodTarget::exact('Symfony\\Component\\Serializer\\SerializerInterface', 'deserialize'),
            MethodTarget::exact('Symfony\\Component\\Serializer\\Normalizer\\DenormalizerInterface', 'denormalize'),
            MethodTarget::exact('Symfony\\Component\\Serializer\\Serializer', 'deserialize'),
            MethodTarget::exact('Symfony\\Component\\Serializer\\Serializer', 'denormalize'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $typeArg = $context->invocation->getArgument(1, 'type')?->type;
        if (!$typeArg instanceof Type) {
            return null;
        }

        $literal = $typeArg->getLiteralString();
        if ($literal !== null) {
            return self::resolveTypeSpecification($literal, $context->codebase);
        }

        if (count($typeArg->atomicTypes) === 1) {
            $atomic = $typeArg->atomicTypes[0];
            if (
                $atomic instanceof ScalarType
                && $atomic->refinement instanceof ClassLikeStringType
                && $atomic->refinement->variant === ClassLikeStringVariant::OfType
                && $atomic->refinement->constraint instanceof Type
            ) {
                return $atomic->refinement->constraint;
            }
        }

        return null;
    }

    private static function resolveTypeSpecification(string $spec, Codebase $codebase): ?Type
    {
        $trimmed = trim($spec);
        if ($trimmed === '') {
            return null;
        }

        $arrayDepth = 0;
        while (str_ends_with($trimmed, '[]')) {
            $arrayDepth++;
            $trimmed = substr($trimmed, 0, -2);
        }

        $className = ltrim(trim($trimmed), '\\');
        if ($className === '' || !$codebase->classLikeExists($className)) {
            return null;
        }

        $resolved = Type::namedObject($className);
        for ($i = 0; $i < $arrayDepth; ++$i) {
            $resolved = Type::array(Type::int(), $resolved);
        }

        return $resolved;
    }
}

<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\ClassLikeStringType;
use Mago\Sdk\Analyzer\Type\ClassLikeStringVariant;
use Mago\Sdk\Analyzer\Type\EnumType;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\Analyzer\Type\ScalarType;

/**
 * Narrows ParameterBag::getEnum() and InputBag::getEnum() to the target BackedEnum
 * when a non-null default of that enum is supplied (closing Symfony 6.4's missing
 * conditional @psalm-return annotation).
 */
final class ParameterBagEnumReturnTypeProvider implements MethodReturnTypeProvider
{
    public function getTargets(): array
    {
        return [
            MethodTarget::exact('Symfony\\Component\\HttpFoundation\\ParameterBag', 'getEnum'),
            MethodTarget::exact('Symfony\\Component\\HttpFoundation\\InputBag', 'getEnum'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $classType = $context->invocation->getArgument(1, 'class')?->type;
        $defaultType = $context->invocation->getArgument(2, 'default')?->type;
        if (!$classType instanceof Type || !$defaultType instanceof Type || $defaultType->atomicTypes === []) {
            return null;
        }

        $enumClass = self::resolveEnumClass($classType);
        if ($enumClass === null) {
            return null;
        }

        $enumMeta = $context->codebase->getEnum($enumClass);
        if ($enumMeta === null || $enumMeta->enumType === null) {
            return null;
        }

        $expectedLower = strtolower($enumClass);
        foreach ($defaultType->atomicTypes as $atomic) {
            if ($atomic instanceof EnumType) {
                if (strtolower($atomic->name) !== $expectedLower) {
                    return null;
                }
                continue;
            }
            if ($atomic instanceof NamedObjectType) {
                if (strtolower($atomic->name) !== $expectedLower) {
                    return null;
                }
                continue;
            }

            return null;
        }

        return Type::fromAtomic(new EnumType($enumClass));
    }

    private static function resolveEnumClass(Type $type): ?string
    {
        $literal = $type->getLiteralClassString() ?? $type->getLiteralString();
        if ($literal !== null) {
            $trimmed = ltrim(trim($literal), '\\');

            return $trimmed !== '' ? $trimmed : null;
        }

        if (count($type->atomicTypes) !== 1) {
            return null;
        }

        $atomic = $type->atomicTypes[0];
        if (
            $atomic instanceof ScalarType
            && $atomic->refinement instanceof ClassLikeStringType
            && $atomic->refinement->variant === ClassLikeStringVariant::OfType
        ) {
            $constraint = $atomic->refinement->constraint;
            if ($constraint instanceof EnumType) {
                return ltrim($constraint->name, '\\');
            }
            if ($constraint instanceof NamedObjectType) {
                return ltrim($constraint->name, '\\');
            }
        }

        return null;
    }
}

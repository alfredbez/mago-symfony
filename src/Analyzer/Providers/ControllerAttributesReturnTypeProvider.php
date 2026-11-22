<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\ClassLikeStringType;
use Mago\Sdk\Analyzer\Type\ClassLikeStringVariant;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\Analyzer\Type\ScalarType;

/**
 * Narrows ControllerEvent::getAttributes($className) and
 * ControllerArgumentsEvent::getAttributes($className) to list<T> when a concrete
 * attribute class name is supplied (closing Symfony 6.4's list<object> return type).
 */
final class ControllerAttributesReturnTypeProvider implements MethodReturnTypeProvider
{
    public function getTargets(): array
    {
        return [
            MethodTarget::exact('Symfony\\Component\\HttpKernel\\Event\\ControllerEvent', 'getAttributes'),
            MethodTarget::exact('Symfony\\Component\\HttpKernel\\Event\\ControllerArgumentsEvent', 'getAttributes'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $declaring = strtolower($context->invocation->declaringClass ?? '');
        if (
            $declaring !== 'symfony\\component\\httpkernel\\event\\controllerevent'
            && $declaring !== 'symfony\\component\\httpkernel\\event\\controllerargumentsevent'
        ) {
            return null;
        }

        $classArg = $context->invocation->getArgument(0, 'className')?->type;
        if (!$classArg instanceof Type) {
            return null;
        }

        $targetClass = self::resolveClassName($classArg);
        if ($targetClass === null || !$context->codebase->classOrInterfaceExists($targetClass)) {
            return null;
        }

        return Type::list(Type::namedObject($targetClass));
    }

    private static function resolveClassName(Type $type): ?string
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
            && $atomic->refinement->constraint instanceof NamedObjectType
        ) {
            return ltrim($atomic->refinement->constraint->name, '\\');
        }

        return null;
    }
}

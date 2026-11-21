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
use Mago\Sdk\Analyzer\Type\ScalarTypeKind;

/**
 * Narrows WrappedExceptionsInterface::getWrappedExceptions($class) to
 * array<array-key, T> when a non-empty class or interface name is supplied
 * (closing Symfony 6.4's untemplated Throwable[] return type).
 */
final class WrappedExceptionsReturnTypeProvider implements MethodReturnTypeProvider
{
    public function getTargets(): array
    {
        return [
            MethodTarget::exact('Symfony\\Component\\Messenger\\Exception\\WrappedExceptionsInterface', 'getWrappedExceptions'),
            MethodTarget::exact('Symfony\\Component\\Messenger\\Exception\\WrappedExceptionsTrait', 'getWrappedExceptions'),
            MethodTarget::exact('Symfony\\Component\\Messenger\\Exception\\HandlerFailedException', 'getWrappedExceptions'),
            MethodTarget::exact('Symfony\\Component\\Messenger\\Exception\\DelayedMessageHandlingException', 'getWrappedExceptions'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $classArg = $context->invocation->getArgument(0, 'class')?->type;
        if (!$classArg instanceof Type) {
            return null;
        }

        $targetClass = self::resolveClassName($classArg);
        if ($targetClass === null || !$context->codebase->classOrInterfaceExists($targetClass)) {
            return null;
        }

        return Type::array(
            Type::fromAtomic(new ScalarType(ScalarTypeKind::ArrayKey)),
            Type::namedObject($targetClass),
        );
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

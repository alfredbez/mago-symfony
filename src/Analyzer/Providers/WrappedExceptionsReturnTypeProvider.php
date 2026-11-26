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
 *
 * @internal
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

    /** @var array<string, true> */
    private const ALLOWED_DECLARING = [
        'symfony\\component\\messenger\\exception\\wrappedexceptionsinterface' => true,
        'symfony\\component\\messenger\\exception\\wrappedexceptionstrait' => true,
        'symfony\\component\\messenger\\exception\\handlerfailedexception' => true,
        'symfony\\component\\messenger\\exception\\delayedmessagehandlingexception' => true,
    ];

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $declaring = strtolower($context->invocation->declaringClass ?? '');
        if (!isset(self::ALLOWED_DECLARING[$declaring])) {
            return null;
        }

        $classArg = $context->invocation->getArgument(0, 'class')?->type;
        if (!$classArg instanceof Type) {
            return null;
        }

        $targetClass = self::resolveClassName($classArg);
        if ($targetClass === null || !self::isThrowableSubtype($targetClass, $context)) {
            return null;
        }

        return Type::array(
            Type::fromAtomic(new ScalarType(ScalarTypeKind::ArrayKey)),
            Type::namedObject($targetClass),
        );
    }

    private static function isThrowableSubtype(string $targetClass, ReturnTypeProviderContext $context): bool
    {
        if (strtolower($targetClass) === 'throwable') {
            return true;
        }

        if (!$context->codebase->classOrInterfaceExists($targetClass)) {
            return false;
        }

        foreach ($context->codebase->getClassAncestors($targetClass) as $ancestor) {
            if (strtolower($ancestor) === 'throwable') {
                return true;
            }
        }

        return false;
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

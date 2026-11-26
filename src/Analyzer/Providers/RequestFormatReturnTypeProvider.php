<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\ScalarType;
use Mago\Sdk\Analyzer\Type\ScalarTypeKind;
use Mago\Sdk\Analyzer\Type\SimpleAtomicType;
use Mago\Sdk\Analyzer\Type\SimpleAtomicTypeKind;

/**
 * Narrows Request::getRequestFormat() and Request::getPreferredFormat() to string
 * when $default is omitted (defaults to 'html') or is a non-null string.
 *
 * @internal
 */
final class RequestFormatReturnTypeProvider implements MethodReturnTypeProvider
{
    public function getTargets(): array
    {
        return [
            MethodTarget::exact('Symfony\\Component\\HttpFoundation\\Request', 'getRequestFormat'),
            MethodTarget::exact('Symfony\\Component\\HttpFoundation\\Request', 'getPreferredFormat'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $defaultArg = $context->invocation->getArgument(0, 'default');
        if ($defaultArg === null) {
            return Type::string();
        }

        $defaultType = $defaultArg->type;
        if (!$defaultType instanceof Type || $defaultType->atomicTypes === []) {
            return null;
        }

        foreach ($defaultType->atomicTypes as $atomic) {
            if ($atomic instanceof SimpleAtomicType && $atomic->kind === SimpleAtomicTypeKind::Null) {
                return null;
            }
            if (!$atomic instanceof ScalarType || $atomic->kind !== ScalarTypeKind::String) {
                return null;
            }
        }

        return Type::string();
    }
}

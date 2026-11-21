<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\ClassLikeStringType;
use Mago\Sdk\Analyzer\Type\ScalarType;
use Mago\Sdk\Analyzer\Type\ScalarTypeKind;
use Mago\Sdk\Analyzer\Type\SimpleAtomicType;
use Mago\Sdk\Analyzer\Type\SimpleAtomicTypeKind;
use Mago\Sdk\Analyzer\Type\StringType;

/**
 * Narrows HeaderBag::get($key, $default) when a non-null string default is supplied.
 */
final class HeaderBagGetReturnTypeProvider implements MethodReturnTypeProvider
{
    public function getTargets(): array
    {
        return [
            MethodTarget::exact('Symfony\\Component\\HttpFoundation\\HeaderBag', 'get'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $defaultType = $context->invocation->getArgument(1, 'default')?->type;
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

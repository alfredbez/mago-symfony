<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\ArrayKeyKind;
use Mago\Sdk\Analyzer\Type\KeyedArrayType;
use Mago\Sdk\Analyzer\Type\ListType;
use Mago\Sdk\Analyzer\Type\ScalarType;
use Mago\Sdk\Analyzer\Type\ScalarTypeKind;
use Mago\Sdk\Analyzer\Type\SimpleAtomicType;
use Mago\Sdk\Analyzer\Type\SimpleAtomicTypeKind;

/**
 * Narrows Request::getPreferredLanguage($locales) to string when $locales is a
 * non-empty 0-indexed list of strings.
 */
final class RequestPreferredLanguageReturnTypeProvider implements MethodReturnTypeProvider
{
    public function getTargets(): array
    {
        return [
            MethodTarget::exact('Symfony\\Component\\HttpFoundation\\Request', 'getPreferredLanguage'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $localesType = $context->invocation->getArgument(0, 'locales')?->type;
        if (!$localesType instanceof Type || $localesType->atomicTypes === []) {
            return null;
        }

        foreach ($localesType->atomicTypes as $atomic) {
            if ($atomic instanceof ListType) {
                if (!self::isNonEmptyStringList($atomic)) {
                    return null;
                }
                continue;
            }

            if ($atomic instanceof KeyedArrayType) {
                if (!self::isZeroIndexedStringArray($atomic)) {
                    return null;
                }
                continue;
            }

            return null;
        }

        return Type::string();
    }

    private static function isNonEmptyStringList(ListType $list): bool
    {
        $hasGuaranteedZero = $list->nonEmpty
            || ($list->knownCount !== null && $list->knownCount > 0)
            || (isset($list->knownElements[0]) && !$list->knownElements[0]->optional);
        if (!$hasGuaranteedZero) {
            return false;
        }

        if ($list->knownElements !== null) {
            foreach ($list->knownElements as $element) {
                if (!self::isPureStringType($element->type)) {
                    return false;
                }
            }
            if (!self::isNeverType($list->elementType) && !self::isPureStringType($list->elementType)) {
                return false;
            }

            return true;
        }

        return self::isPureStringType($list->elementType);
    }

    private static function isZeroIndexedStringArray(KeyedArrayType $array): bool
    {
        if ($array->knownItems === null || $array->knownItems === []) {
            return false;
        }

        $hasZeroKey = false;
        foreach ($array->knownItems as $item) {
            if (!self::isPureStringType($item->type)) {
                return false;
            }
            if (!$item->optional && $item->key->kind === ArrayKeyKind::Integer && $item->key->integerValue === 0) {
                $hasZeroKey = true;
            }
        }

        if (!$hasZeroKey) {
            return false;
        }

        if ($array->valueType !== null && !self::isNeverType($array->valueType) && !self::isPureStringType($array->valueType)) {
            return false;
        }

        return true;
    }

    private static function isPureStringType(Type $type): bool
    {
        if ($type->atomicTypes === []) {
            return false;
        }

        foreach ($type->atomicTypes as $atomic) {
            if (!$atomic instanceof ScalarType || $atomic->kind !== ScalarTypeKind::String) {
                return false;
            }
        }

        return true;
    }

    private static function isNeverType(Type $type): bool
    {
        if (count($type->atomicTypes) !== 1) {
            return false;
        }

        $atomic = $type->atomicTypes[0];

        return $atomic instanceof SimpleAtomicType && $atomic->kind === SimpleAtomicTypeKind::Never;
    }
}

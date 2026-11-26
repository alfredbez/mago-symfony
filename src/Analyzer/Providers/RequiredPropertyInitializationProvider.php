<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\Metadata\TypeMetadata;
use Mago\Sdk\Analyzer\PropertyInitializationProvider;
use Mago\Sdk\Analyzer\PropertyInitializationProviderContext;
use Mago\Sdk\Analyzer\PropertyTarget;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\Analyzer\Type\SimpleAtomicType;
use Mago\Sdk\Analyzer\Type\SimpleAtomicTypeKind;
use Mago\Sdk\Analyzer\Type\Visibility;

/**
 * Recognizes non-readonly public writable properties with a single named native type
 * injected by Symfony's AutowireRequiredPropertiesPass via #[Symfony\Contracts\Service\Attribute\Required].
 *
 * @internal
 */
final class RequiredPropertyInitializationProvider implements PropertyInitializationProvider
{
    private const LOWER_REQUIRED_ATTRIBUTE = 'symfony\\contracts\\service\\attribute\\required';

    public function getTargets(): array
    {
        return [
            PropertyTarget::anyClass('*'),
        ];
    }

    public function isPropertyInitialized(PropertyInitializationProviderContext $context): bool
    {
        $property = $context->property;
        if (
            $property->readVisibility !== Visibility::Public
            || $property->writeVisibility !== Visibility::Public
            || $property->flags->contains(MetadataFlags::READONLY)
            || $property->flags->contains(MetadataFlags::STATIC)
            || $property->flags->contains(MetadataFlags::VIRTUAL_PROPERTY)
            || !self::hasNamedDeclaredType($property->declaredType)
        ) {
            return false;
        }

        foreach ($property->attributes as $attribute) {
            if (strtolower(ltrim($attribute->name, '\\')) === self::LOWER_REQUIRED_ATTRIBUTE) {
                return true;
            }
        }

        return false;
    }

    /**
     * Symfony's AutowireRequiredPropertiesPass skips properties whose native reflection type
     * is not a ReflectionNamedType (untyped, union, and intersection properties).
     */
    private static function hasNamedDeclaredType(?TypeMetadata $declaredType): bool
    {
        if ($declaredType === null) {
            return false;
        }

        $nonNullCount = 0;
        foreach ($declaredType->type->atomicTypes as $atomic) {
            if ($atomic instanceof SimpleAtomicType && $atomic->kind === SimpleAtomicTypeKind::Null) {
                continue;
            }

            if ($atomic instanceof NamedObjectType && $atomic->intersections !== null && $atomic->intersections !== []) {
                return false;
            }

            $nonNullCount++;
            if ($nonNullCount > 1) {
                return false;
            }
        }

        return $nonNullCount === 1;
    }
}

<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\Metadata\TypeMetadata;
use Mago\Sdk\Analyzer\PropertyInitializationProvider;
use Mago\Sdk\Analyzer\PropertyInitializationProviderContext;
use Mago\Sdk\Analyzer\PropertyTarget;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\Analyzer\Type\ReferenceType;
use Mago\Sdk\Analyzer\Type\ReferenceTypeKind;
use Mago\Sdk\Analyzer\Type\SimpleAtomicType;
use Mago\Sdk\Analyzer\Type\SimpleAtomicTypeKind;
use Mago\Sdk\Analyzer\Type\Visibility;

/**
 * Recognizes non-readonly public writable properties with a single named native type
 * injected by Symfony's AutowireRequiredPropertiesPass via #[Symfony\Contracts\Service\Attribute\Required].
 *
 * Bare builtin types (scalar, array, mixed, object, iterable, callable) require a
 * Symfony\Component\DependencyInjection\Attribute\Autowire attribute (or subclass);
 * otherwise Symfony only autowires class or interface service types.
 *
 * @internal
 */
final class RequiredPropertyInitializationProvider implements PropertyInitializationProvider
{
    private const LOWER_REQUIRED_ATTRIBUTE = 'symfony\\contracts\\service\\attribute\\required';
    private const LOWER_AUTOWIRE_ATTRIBUTE = 'symfony\\component\\dependencyinjection\\attribute\\autowire';

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
        ) {
            return false;
        }

        $hasRequired = false;
        foreach ($property->attributes as $attribute) {
            if (strtolower(ltrim($attribute->name, '\\')) === self::LOWER_REQUIRED_ATTRIBUTE) {
                $hasRequired = true;
                break;
            }
        }

        if (!$hasRequired) {
            return false;
        }

        $hasAutowire = false;
        foreach ($property->attributes as $attribute) {
            $attrLower = strtolower(ltrim($attribute->name, '\\'));
            if ($attrLower === self::LOWER_REQUIRED_ATTRIBUTE) {
                continue;
            }
            if (self::isAutowireAttribute($attribute->name, $attrLower, $context)) {
                $hasAutowire = true;
                break;
            }
        }

        return self::hasInjectableDeclaredType($property->declaredType, $hasAutowire, $context);
    }

    private static function isAutowireAttribute(
        string $attributeName,
        string $lowerName,
        PropertyInitializationProviderContext $context,
    ): bool {
        if ($lowerName === self::LOWER_AUTOWIRE_ATTRIBUTE) {
            return true;
        }

        foreach ($context->codebase->getClassAncestors($attributeName) as $ancestor) {
            if (strtolower(ltrim($ancestor, '\\')) === self::LOWER_AUTOWIRE_ATTRIBUTE) {
                return true;
            }
        }

        return false;
    }

    /**
     * Symfony's AutowireRequiredPropertiesPass skips properties whose native reflection type
     * is not a ReflectionNamedType (untyped, union, and intersection properties), and without
     * an #[Autowire] attribute (or subclass) can only autowire class/interface service types.
     * Unloaded external class/interface types stay injectable; known traits and enums are rejected.
     */
    private static function hasInjectableDeclaredType(
        ?TypeMetadata $declaredType,
        bool $hasAutowire,
        PropertyInitializationProviderContext $context,
    ): bool {
        if ($declaredType === null) {
            return false;
        }

        $nonNullAtomic = null;
        foreach ($declaredType->type->atomicTypes as $atomic) {
            if ($atomic instanceof SimpleAtomicType && $atomic->kind === SimpleAtomicTypeKind::Null) {
                continue;
            }

            if (
                ($atomic instanceof NamedObjectType || $atomic instanceof ReferenceType)
                && $atomic->intersections !== null
                && $atomic->intersections !== []
            ) {
                return false;
            }

            if ($nonNullAtomic !== null) {
                return false;
            }

            $nonNullAtomic = $atomic;
        }

        if ($nonNullAtomic === null) {
            return false;
        }

        if ($hasAutowire) {
            return true;
        }

        $className = null;
        if ($nonNullAtomic instanceof NamedObjectType) {
            $className = $nonNullAtomic->name;
        } elseif ($nonNullAtomic instanceof ReferenceType && $nonNullAtomic->kind === ReferenceTypeKind::Symbol) {
            $className = $nonNullAtomic->name;
        }

        if ($className === null || $className === '') {
            return false;
        }

        $classLike = $context->codebase->getClassLike($className);

        return $classLike === null
            || $classLike->kind === ClassLikeKind::Class_
            || $classLike->kind === ClassLikeKind::Interface;
    }
}

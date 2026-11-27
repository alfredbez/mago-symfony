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
use Mago\Sdk\Analyzer\Type\SimpleAtomicType;
use Mago\Sdk\Analyzer\Type\SimpleAtomicTypeKind;
use Mago\Sdk\Analyzer\Type\Visibility;

/**
 * Recognizes non-readonly public writable properties with a single named native type
 * injected by Symfony's AutowireRequiredPropertiesPass via #[Symfony\Contracts\Service\Attribute\Required].
 *
 * Bare builtin types (scalar, array, mixed, object, iterable, callable) require a
 * Symfony\Component\DependencyInjection\Attribute\Autowire* attribute; otherwise
 * Symfony only autowires class or interface service types.
 *
 * @internal
 */
final class RequiredPropertyInitializationProvider implements PropertyInitializationProvider
{
    private const LOWER_REQUIRED_ATTRIBUTE = 'symfony\\contracts\\service\\attribute\\required';
    private const LOWER_AUTOWIRE_PREFIX = 'symfony\\component\\dependencyinjection\\attribute\\autowire';
    private const LOWER_AUTOWIRE_BASE = 'symfony\\component\\dependencyinjection\\attribute\\autowire';

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
        $hasAutowire = false;
        foreach ($property->attributes as $attribute) {
            $attrLower = strtolower(ltrim($attribute->name, '\\'));
            if ($attrLower === self::LOWER_REQUIRED_ATTRIBUTE) {
                $hasRequired = true;
                continue;
            }
            if (!$hasAutowire && self::isAutowireAttribute($attribute->name, $attrLower, $context)) {
                $hasAutowire = true;
            }
        }

        if (!$hasRequired) {
            return false;
        }

        return self::hasInjectableDeclaredType($property->declaredType, $hasAutowire, $context);
    }

    private static function isAutowireAttribute(
        string $attributeName,
        string $lowerName,
        PropertyInitializationProviderContext $context,
    ): bool {
        if (str_starts_with($lowerName, self::LOWER_AUTOWIRE_PREFIX)) {
            return true;
        }

        foreach ($context->codebase->getClassAncestors($attributeName) as $ancestor) {
            if (strtolower(ltrim($ancestor, '\\')) === self::LOWER_AUTOWIRE_BASE) {
                return true;
            }
        }

        return false;
    }

    /**
     * Symfony's AutowireRequiredPropertiesPass skips properties whose native reflection type
     * is not a ReflectionNamedType (untyped, union, and intersection properties), and without
     * an #[Autowire*] attribute can only autowire class/interface service types.
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

            if ($atomic instanceof NamedObjectType && $atomic->intersections !== null && $atomic->intersections !== []) {
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

        if (!$nonNullAtomic instanceof NamedObjectType) {
            return false;
        }

        $classLike = $context->codebase->getClassLike($nonNullAtomic->name);

        return $classLike !== null
            && ($classLike->kind === ClassLikeKind::Class_ || $classLike->kind === ClassLikeKind::Interface);
    }
}

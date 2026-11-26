<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\PropertyInitializationProvider;
use Mago\Sdk\Analyzer\PropertyInitializationProviderContext;
use Mago\Sdk\Analyzer\PropertyTarget;
use Mago\Sdk\Analyzer\Type\Visibility;

/**
 * Recognizes non-readonly public writable properties injected by Symfony's
 * AutowireRequiredPropertiesPass via #[Symfony\Contracts\Service\Attribute\Required].
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
}

<?php

declare(strict_types=1);

namespace SymfonyFixtureSerializer;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class SerializerClassStringNegative
{
    /**
     * @param class-string<PayloadDto> $dtoClass
     * @param class-string $unconstrainedClass
     */
    public function invalidCalls(
        SerializerInterface $serializer,
        DenormalizerInterface $denormalizer,
        string $dtoClass,
        string $unconstrainedClass,
    ): void {
        $serializer->deserialize('{"title":"ok"}', $dtoClass, 'json')->missingOnDeserialized();
        $denormalizer->denormalize(['title' => 'ok'], $dtoClass)->missingOnDenormalized();
        $serializer->deserialize('{"title":"ok"}', $unconstrainedClass, 'json')->unknownOnMixed();
    }
}

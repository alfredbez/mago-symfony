<?php

declare(strict_types=1);

namespace SymfonyFixtureSerializer;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class PayloadDto
{
    public function __construct(public readonly string $title) {}
}

final class SerializerClassStringUsage
{
    /**
     * @param class-string<PayloadDto> $dtoClass
     */
    public function hydrate(
        SerializerInterface $serializer,
        DenormalizerInterface $denormalizer,
        string $dtoClass,
    ): string {
        $fromJson = $serializer->deserialize('{"title":"ok"}', $dtoClass, 'json');
        $fromArray = $denormalizer->denormalize(['title' => 'ok'], $dtoClass);

        return $fromJson->title . ':' . $fromArray->title;
    }
}

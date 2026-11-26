<?php

declare(strict_types=1);

namespace SymfonyFixtureXml;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

final class SerializerClassStringAndPreferredLanguage
{
    /**
     * @param class-string<DomainEvent> $eventClass
     */
    public function deserializeAndDenormalizeClassString(
        Serializer $concreteSerializer,
        SerializerInterface $serializer,
        DenormalizerInterface $denormalizer,
        string $eventClass,
    ): string {
        $fromConcrete = $concreteSerializer->deserialize('{"name":"a"}', $eventClass, 'json');
        $fromInterface = $serializer->deserialize('{"name":"b"}', $eventClass, 'json');
        $fromConcreteDenorm = $concreteSerializer->denormalize(['name' => 'c'], $eventClass);
        $fromInterfaceDenorm = $denormalizer->denormalize(['name' => 'd'], $eventClass);

        return $fromConcrete->name . ':' . $fromInterface->name . ':' . $fromConcreteDenorm->name . ':' . $fromInterfaceDenorm->name;
    }

    /**
     * @param array{0: string, 1?: string} $shapedLocales
     */
    public function keyedPreferredLanguages(Request $request, array $shapedLocales): string
    {
        $literalKeyed = $request->getPreferredLanguage([0 => 'en', 'fallback' => 'de']);
        $shaped = $request->getPreferredLanguage($shapedLocales);

        return $literalKeyed . ':' . $shaped;
    }
}

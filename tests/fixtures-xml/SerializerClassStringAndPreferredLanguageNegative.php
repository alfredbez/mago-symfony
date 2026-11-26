<?php

declare(strict_types=1);

namespace SymfonyFixtureXml;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Serializer;

final class SerializerClassStringAndPreferredLanguageNegative
{
    /**
     * @param class-string<DomainEvent> $eventClass
     * @param class-string $unconstrainedClass
     */
    public function invalidSerializerCalls(
        Serializer $serializer,
        string $eventClass,
        string $unconstrainedClass,
    ): void {
        $serializer->deserialize('{"name":"a"}', $eventClass, 'json')->missingOnDeserializedEvent();
        $serializer->denormalize(['name' => 'b'], $eventClass)->missingOnDenormalizedEvent();
        $serializer->deserialize('{"name":"c"}', $unconstrainedClass, 'json')->unknownOnMixed();
    }

    /**
     * @param array{0?: string, fallback: string} $optionalZero
     * @param array<int, string> $unindexed
     */
    public function nullableKeyedPreferredLanguages(
        Request $request,
        array $optionalZero,
        array $unindexed,
    ): string {
        $missingZero = $request->getPreferredLanguage([1 => 'en', 'fallback' => 'de']);
        $optZero = $request->getPreferredLanguage($optionalZero);
        $plainMap = $request->getPreferredLanguage($unindexed);

        return $missingZero . ':' . $optZero . ':' . $plainMap;
    }
}

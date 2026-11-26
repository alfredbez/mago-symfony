<?php

declare(strict_types=1);

namespace Symfony\Component\Serializer {
    interface SerializerInterface
    {
        /** @param array<string, mixed> $context */
        public function serialize(mixed $data, string $format, array $context = []): string;

        /** @param array<string, mixed> $context */
        public function deserialize(mixed $data, string $type, string $format, array $context = []): mixed;
    }
}

namespace Symfony\Component\Serializer\Normalizer {
    interface DenormalizerInterface
    {
        /** @param array<string, mixed> $context */
        public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed;
    }
}

<?php

declare(strict_types=1);

namespace SymfonyFixture {

interface Greeter
{
    public function greet(string $name): string;
}

final class Mailer
{
    public function send(string $to): void {}
}

final class ConcreteGreeter implements Greeter
{
    public function greet(string $name): string
    {
        return $name;
    }

    public function implementationOnly(): string
    {
        return 'concrete';
    }
}

final class GreeterFactory
{
    public static function create(): ConcreteGreeter
    {
        return new ConcreteGreeter();
    }

    public static function createConcrete(): ConcreteGreeter
    {
        return new ConcreteGreeter();
    }
}

final class MailerFactoryService
{
    public function buildMailer(): Mailer
    {
        return new Mailer();
    }
}

final class FixtureConstants
{
    public const API_VERSION = 2;
}
}

namespace Symfony\Component\Serializer {
    if (!interface_exists(SerializerInterface::class)) {
        interface SerializerInterface
        {
            /** @param array<string, mixed> $context */
            public function serialize(mixed $data, string $format, array $context = []): string;

            /** @param array<string, mixed> $context */
            public function deserialize(mixed $data, string $type, string $format, array $context = []): mixed;
        }
    }
}

namespace Symfony\Component\Serializer\Normalizer {
    if (!interface_exists(DenormalizerInterface::class)) {
        interface DenormalizerInterface
        {
            /** @param array<string, mixed> $context */
            public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed;

            /** @param array<string, mixed> $context */
            public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool;

            /** @return array<string, bool|null> */
            public function getSupportedTypes(?string $format): array;
        }
    }
}

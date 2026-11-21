<?php

declare(strict_types=1);

namespace SymfonyFixture;

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

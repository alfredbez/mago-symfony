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

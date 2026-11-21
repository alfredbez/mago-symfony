<?php

declare(strict_types=1);

namespace SymfonyBench;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;

interface MailerInterface
{
    public function send(string $to, string $subject): bool;
}

final class ConcreteMailer implements MailerInterface
{
    public function send(string $to, string $subject): bool
    {
        return $to !== '' && $subject !== '';
    }

    public function queuedCount(): int
    {
        return 0;
    }
}

interface LoggerInterface
{
    public function info(string $message): void;
}

final class ConcreteLogger implements LoggerInterface
{
    public function info(string $message): void
    {
    }
}

final class BenchNodeBuilder extends NodeBuilder
{
}

final class BenchService00
{
    public function handle00(string $input): string
    {
        return $input;
    }

    public function count(): int
    {
        return 0;
    }
}

final class BenchService01
{
    public function handle01(string $input): string
    {
        return $input;
    }

    public function count(): int
    {
        return 1;
    }
}

final class BenchService02
{
    public function handle02(string $input): string
    {
        return $input;
    }

    public function count(): int
    {
        return 2;
    }
}

final class BenchService03
{
    public function handle03(string $input): string
    {
        return $input;
    }

    public function count(): int
    {
        return 3;
    }
}

final class BenchService04
{
    public function handle04(string $input): string
    {
        return $input;
    }

    public function count(): int
    {
        return 4;
    }
}

final class BenchService05
{
    public function handle05(string $input): string
    {
        return $input;
    }

    public function count(): int
    {
        return 5;
    }
}

final class BenchService06
{
    public function handle06(string $input): string
    {
        return $input;
    }

    public function count(): int
    {
        return 6;
    }
}

final class BenchService07
{
    public function handle07(string $input): string
    {
        return $input;
    }

    public function count(): int
    {
        return 7;
    }
}

final class BenchService08
{
    public function handle08(string $input): string
    {
        return $input;
    }

    public function count(): int
    {
        return 8;
    }
}

final class BenchService09
{
    public function handle09(string $input): string
    {
        return $input;
    }

    public function count(): int
    {
        return 9;
    }
}

final class BenchService10
{
    public function handle10(string $input): string
    {
        return $input;
    }

    public function count(): int
    {
        return 10;
    }
}

final class BenchService11
{
    public function handle11(string $input): string
    {
        return $input;
    }

    public function count(): int
    {
        return 11;
    }
}

final class BenchService12
{
    public function handle12(string $input): string
    {
        return $input;
    }

    public function count(): int
    {
        return 12;
    }
}

final class BenchService13
{
    public function handle13(string $input): string
    {
        return $input;
    }

    public function count(): int
    {
        return 13;
    }
}

final class BenchService14
{
    public function handle14(string $input): string
    {
        return $input;
    }

    public function count(): int
    {
        return 14;
    }
}

final class BenchService15
{
    public function handle15(string $input): string
    {
        return $input;
    }

    public function count(): int
    {
        return 15;
    }
}

<?php

declare(strict_types=1);

namespace {
    final class Logger
    {
        public function unrelatedGlobalMethod(): void {}
    }
}

namespace SymfonyFixtureCase {
    interface AppLogger
    {
        public function record(string $message): void;
    }

    final class AppService
    {
        public function run(): string
        {
            return 'ok';
        }
    }
}

<?php

declare(strict_types=1);

namespace SymfonyFixtureCase;

use Psr\Container\ContainerInterface as PsrContainer;
use Symfony\Component\DependencyInjection\ContainerInterface as SfContainer;

final class ContainerCaseFallback
{
    public function lowercaseLoggerDoesNotCollideWithGlobalClass(PsrContainer $psr, SfContainer $sf): AppLogger
    {
        if ($psr->get('logger') instanceof AppLogger) {
            return $this->requireLogger($sf);
        }

        return $this->requireLogger($sf);
    }

    public function exactCaseClassesResolve(PsrContainer $psr): int
    {
        $service = $psr->get(AppService::class);
        $byLiteral = $psr->get('SymfonyFixtureCase\\AppService');
        $clock = $psr->get('DateTimeImmutable');
        $exactGlobal = $psr->get('Logger');
        $exactGlobal->unrelatedGlobalMethod();

        return strlen($service->run()) + strlen($byLiteral->run()) + $clock->getTimestamp();
    }

    private function requireLogger(SfContainer $sf): AppLogger
    {
        $fromSf = $sf->get('logger');
        if (!$fromSf instanceof AppLogger) {
            throw new \RuntimeException('Missing logger');
        }

        return $fromSf;
    }
}

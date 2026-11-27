<?php

declare(strict_types=1);

namespace SymfonyFixtureCase;

use Psr\Container\ContainerInterface as PsrContainer;
use Symfony\Component\DependencyInjection\ContainerInterface as SfContainer;

final class ContainerCaseFallbackNegative
{
    public function lowercaseLoggerStaysUntyped(PsrContainer $psr, SfContainer $sf): void
    {
        $psr->get('logger')->unrelatedGlobalMethod();
        $sf->get('logger')->unrelatedGlobalMethod();
    }

    public function wrongCaseClassesDoNotResolve(PsrContainer $psr): void
    {
        $psr->get('datetimeimmutable')->getTimestamp();
        $psr->get('symfonyfixturecase\\appservice')->run();
    }
}

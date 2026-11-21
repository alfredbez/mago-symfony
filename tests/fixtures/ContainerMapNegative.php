<?php

declare(strict_types=1);

namespace SymfonyFixture;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Contracts\Service\ServiceProviderInterface;

final class ContainerMapNegative
{
    public function wrongMethod(ContainerInterface $container): void
    {
        $container->get(Greeter::class)->send('dev@example.com');
        $container->get('42')->greet('world');
    }

    public function missingMappedClass(ContainerInterface $container): void
    {
        $container->get(ConcreteGreeter::class)->implementationOnly();
    }

    public function locator(ServiceLocator $locator, ServiceProviderInterface $provider): void
    {
        $locator->get('shop.greeter')->missingOnLocator();
        $provider->get('shop.mailer')->missingOnProvider();
        $locator->get('shop.unknown')->greet('world');
    }
}

<?php

declare(strict_types=1);

namespace SymfonyFixture;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Contracts\Service\ServiceProviderInterface;

final class ContainerMap
{
    public function mappedClassName(ContainerInterface $container): string
    {
        return $container->get(Greeter::class)->implementationOnly();
    }

    public function numericId(ContainerInterface $container): void
    {
        $container->get('42')->send('dev@example.com');
    }

    public function locator(ServiceLocator $locator, ServiceProviderInterface $provider): void
    {
        $locator->get('shop.greeter')->greet('world');
        $provider->get('shop.mailer')->send('dev@example.com');
    }
}

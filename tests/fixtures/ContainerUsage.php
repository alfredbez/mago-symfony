<?php

declare(strict_types=1);

namespace SymfonyFixture;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Container as SfContainer;
use Symfony\Component\DependencyInjection\ContainerInterface as SfContainerInterface;

/**
 * Services fetched from the container keep their type.
 */
final class ContainerUsage
{
    /**
     * A class-string id names the service class directly.
     */
    public function getByClassString(ContainerInterface $container): string
    {
        $greeter = $container->get(Greeter::class);
        $greeter->greet('world');

        $mailer = $container->get(Mailer::class);
        $mailer->send('dev@example.com');

        return $greeter->greet('again');
    }

    /**
     * A string id resolves through .mago/symfony-container.json.
     */
    public function getById(ContainerInterface $container): void
    {
        $container->get('shop.greeter')->greet('world');
        $container->get('shop.mailer')->send('dev@example.com');
    }

    /**
     * Symfony ContainerInterface and Container resolve with default or exception behaviors.
     */
    public function getFromSymfonyContainer(SfContainerInterface $sfContainer, SfContainer $concreteContainer): void
    {
        $sfContainer->get(Greeter::class)->greet('world');
        $sfContainer->get(Greeter::class, SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE)->greet('world');
        $sfContainer->get(Greeter::class, SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE)?->greet('world');
        $sfContainer->get('shop.greeter')->greet('world');

        $concreteContainer->get(Greeter::class)->greet('world');
        $concreteContainer->get('shop.mailer')->send('dev@example.com');
    }
}

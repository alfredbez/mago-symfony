<?php

declare(strict_types=1);

namespace SymfonyFixture;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class ContainerFlagsNegative
{
    /** @param 0|1 $behavior */
    public function runtimeFlag(Container $container, ContainerInterface $contract, int $behavior): void
    {
        $container->get('shop.greeter', Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE)->greet('world');
        $contract->get('shop.greeter', ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE)->greet('world');
        $container->get('shop.greeter', $behavior)->greet('world');
    }

    public function nullableFlags(Container $container, int $behavior): void
    {
        $container->get('shop.greeter', Container::NULL_ON_INVALID_REFERENCE)->greet('world');
        $container->get('shop.greeter', Container::IGNORE_ON_INVALID_REFERENCE)->greet('world');
        $container->get('shop.greeter', Container::IGNORE_ON_UNINITIALIZED_REFERENCE)->greet('world');
        $container->get(invalidBehavior: Container::NULL_ON_INVALID_REFERENCE, id: 'shop.greeter')->greet('world');
        $container->get('shop.greeter', $behavior)->greet('world');
    }

    public function wrongMethod(Container $container): void
    {
        $container->get('shop.greeter', Container::EXCEPTION_ON_INVALID_REFERENCE)->missingWithFlag();
    }
}

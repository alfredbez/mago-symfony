<?php

declare(strict_types=1);

namespace SymfonyFixture;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class ContainerFlags
{
    public function throwing(Container $container, ContainerInterface $contract): void
    {
        $container->get('shop.greeter', Container::EXCEPTION_ON_INVALID_REFERENCE)->greet('world');
        $contract->get(invalidBehavior: ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'shop.greeter')->greet('world');
    }

    /** @param 0|1 $behavior */
    public function builder(ContainerBuilder $builder, int $behavior): void
    {
        $builder->get('shop.greeter', ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE)->greet('world');
        $builder->get('shop.greeter', $behavior)->greet('world');
    }

    public function guarded(Container $container): void
    {
        $container->get('shop.greeter', Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE)?->greet('world');
        $container->get('shop.greeter', Container::NULL_ON_INVALID_REFERENCE)?->greet('world');
        $container->get('shop.greeter', Container::IGNORE_ON_INVALID_REFERENCE)?->greet('world');
        $container->get('shop.greeter', Container::IGNORE_ON_UNINITIALIZED_REFERENCE)?->greet('world');
    }
}

<?php

declare(strict_types=1);

namespace SymfonyFixture;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Container as SfContainer;
use Symfony\Component\DependencyInjection\ContainerInterface as SfContainerInterface;

/**
 * The extension must narrow, not silence.
 */
final class ContainerNegative
{
    /**
     * An unknown id keeps the declared ?object type.
     */
    public function unknownId(ContainerInterface $container): void
    {
        $container->get('shop.unknown')->greet('world');
    }

    /**
     * A known class still reports unknown methods.
     */
    public function unknownMethod(ContainerInterface $container): void
    {
        $container->get(Greeter::class)->missingMethod();
    }

    /**
     * Nullable invalidBehavior flags must produce a nullable return type.
     *
     * @param SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE|SfContainerInterface::NULL_ON_INVALID_REFERENCE $dynamicBehavior
     */
    public function nullableInvalidBehavior(
        SfContainerInterface $sfContainer,
        SfContainer $concreteContainer,
        int $dynamicBehavior,
    ): void {
        $sfContainer->get(Greeter::class, SfContainerInterface::NULL_ON_INVALID_REFERENCE)->greet('world');
        $concreteContainer->get(Greeter::class, SfContainerInterface::IGNORE_ON_INVALID_REFERENCE)->greet('world');
        $sfContainer->get('shop.greeter', SfContainerInterface::NULL_ON_INVALID_REFERENCE)->greet('world');
        $sfContainer->get(Greeter::class, $dynamicBehavior)->greet('world');
    }
}

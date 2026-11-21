<?php

declare(strict_types=1);

namespace SymfonyFixture;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

final class CustomNodeBuilderConfigNegative
{
    public function invalidMethodsOnCustomNodes(): void
    {
        $root = new ArrayNodeDefinition('root');

        $root
            ->children()
                ->setNodeClass('custom', CustomNodeDefinition::class)
                ->node('a', 'custom')
                    ->missingOnCustomNode();

        $root
            ->children()
                ->setNodeClass('custom', CustomNodeDefinition::class)
                ->scalarNode('s')
                    ->customSetting();

        $root
            ->children()
                ->setNodeClass('scalar', CustomScalarNodeDefinition::class)
                ->scalarNode('s')
                    ->children();

        $root
            ->children()
                ->setNodeClass('custom', CustomNodeDefinition::class)
                ->node('a', 'custom')
                    ->end()
                ->end()
            ->missingOnRootArray();
    }

    /** @param class-string<\Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition> $dynamicScalarClass */
    public function dynamicOverrideInvalidatesPreviousMapping(string $dynamicScalarClass): void
    {
        $root = new ArrayNodeDefinition('root');

        $root
            ->children()
                ->setNodeClass('scalar', CustomScalarNodeDefinition::class)
                ->setNodeClass('scalar', $dynamicScalarClass)
                ->scalarNode('s')
                    ->customScalarSetting();
    }

    public function unparentedSetNodeClassStaysNullable(): void
    {
        (new NodeBuilder())
            ->setNodeClass('custom', CustomNodeDefinition::class)
            ->node('a', 'custom')
            ->end()
            ->scalarNode('past_root');
    }
}

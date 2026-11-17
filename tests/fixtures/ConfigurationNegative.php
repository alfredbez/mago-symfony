<?php

declare(strict_types=1);

namespace SymfonyFixture;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * Invalid Config TreeBuilder usages must not be silenced by the extension.
 */
final class ConfigurationNegative
{
    public function scalarTreeBuilderVariableWithChildren(): void
    {
        $treeBuilder = new TreeBuilder('root', 'scalar');
        $treeBuilder->getRootNode()->children();
    }

    public function scalarTreeBuilderInlineWithChildren(): void
    {
        (new TreeBuilder('root', 'scalar'))->getRootNode()->children();
    }

    public function extraEndPastTreeBuilderRoot(): void
    {
        $treeBuilder = new TreeBuilder('root');
        $treeBuilder
            ->getRootNode()
            ->children()
            ->scalarNode('a')
            ->end()
            ->end()
            ->end()
            ->scalarNode('past_root');
    }

    public function extraEndPastStandaloneArrayNode(): void
    {
        $node = new ArrayNodeDefinition('extra');
        $node
            ->children()
            ->scalarNode('a')
            ->end()
            ->end()
            ->end()
            ->scalarNode('past_root');
    }

    public function scalarPrototypeWithChildren(): void
    {
        (new ArrayNodeDefinition('items'))
            ->prototype('scalar')
            ->children();
    }

    public function unknownMethodAfterValidateEnd(): void
    {
        (new ArrayNodeDefinition('items'))
            ->validate()
            ->ifNull()
            ->thenEmptyArray()
            ->end()
            ->missingOnArrayNode();
    }
}

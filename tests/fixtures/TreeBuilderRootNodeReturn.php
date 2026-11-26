<?php

declare(strict_types=1);

namespace SymfonyFixture;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

function buildUnparameterizedRootSection(): ArrayNodeDefinition
{
    $rootNode = (new TreeBuilder('fn_section'))->getRootNode();
    $rootNode
        ->children()
            ->scalarNode('name')->end()
        ->end();

    return $rootNode;
}

final class TreeBuilderRootNodeReturn
{
    public function createSection(): ArrayNodeDefinition
    {
        $rootNode = (new TreeBuilder('cdn'))->getRootNode();
        $rootNode
            ->children()
                ->scalarNode('url')->end()
            ->end();

        return $rootNode;
    }

    /**
     * Prose-only docblock without an explicit @return tag.
     */
    public function createDocumentedSection(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('api', 'array');
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->booleanNode('enabled')->defaultTrue()->end()
            ->end();

        return $rootNode;
    }

    public function preserveEndParentGeneric(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('root');

        return $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('host')->end()
            ->end()
            ->end();
    }
}

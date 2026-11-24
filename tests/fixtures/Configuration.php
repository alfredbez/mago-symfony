<?php

declare(strict_types=1);

namespace SymfonyFixture;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class CustomNodeBuilder extends NodeBuilder
{
}

/**
 * Fluent Symfony Config TreeBuilder and ArrayNodeDefinition chains.
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sfm_bridge');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
            ->scalarNode('translator_locale')
            ->isRequired()
            ->defaultValue('en')
            ->end()
            ->scalarNode('base_dir')
            ->isRequired()
            ->cannotBeEmpty()
            ->end()
            ->variableNode('connections')
            ->end();

        $rootNode
            ->children()
            ->arrayNode('nested')
            ->children()
            ->scalarNode('enabled')->defaultTrue()->end()
            ->arrayNode('deeper')
            ->children()
            ->integerNode('port')->defaultValue(8080)->end()
            ->end()
            ->end()
            ->end()
            ->end();

        return $treeBuilder;
    }

    public function prototypesAndValidation(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('relations');

        $node
            ->requiresAtLeastOneElement()
            ->useAttributeAsKey('name')
            ->prototype('array')
            ->beforeNormalization()
            ->ifString()
            ->then(static fn (string $v): array => ['targetEntity' => $v])
            ->end()
            ->children()
            ->scalarNode('targetEntity')->isRequired()->end()
            ->end()
            ->validate()
            ->ifEmpty()
            ->thenInvalid('Invalid relation')
            ->end();

        $callbacks = new ArrayNodeDefinition('lifecycleCallbacks');
        $callbacks
            ->defaultValue([])
            ->validate()
            ->ifNull()
            ->thenEmptyArray()
            ->end()
            ->prototype('array')
            ->prototype('scalar')
            ->end()
            ->end();

        return $callbacks;
    }

    public function scalarRootTreeBuilder(): void
    {
        $treeBuilder = new TreeBuilder('scalar_root', 'scalar');
        $treeBuilder->getRootNode()->isRequired()->cannotBeEmpty()->end();

        (new TreeBuilder('enum_root', 'enum'))->getRootNode()->values(['fast', 'slow'])->end();
        (new TreeBuilder('int_root', 'integer'))->getRootNode()->min(1)->max(10)->end();
    }

    public function customNodeBuilder(CustomNodeBuilder $builder): ?ArrayNodeDefinition
    {
        return $builder->end();
    }
}

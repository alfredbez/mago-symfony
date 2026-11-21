<?php

declare(strict_types=1);

namespace SymfonyBench;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

final class ConfigWorkload0
{
    public function buildTree0(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('bench_0_0');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('locale')->isRequired()->defaultValue('en')->end()
                ->booleanNode('debug')->defaultFalse()->end()
                ->integerNode('ttl')->min(0)->defaultValue(60)->end()
                ->floatNode('ratio')->max(1.0)->defaultValue(0.5)->end()
                ->enumNode('mode')->values(['sync', 'async', 'batch'])->defaultValue('sync')->end()
                ->variableNode('extra')->defaultNull()->end()
                ->node('retries', 'integer')->min(0)->defaultValue(3)->end()
                ->node(type: 'scalar', name: 'channel')->cannotBeEmpty()->end()
                ->arrayNode('connections')
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifString()
                            ->then(static fn (string $v): array => ['dsn' => $v])
                        ->end()
                        ->children()
                            ->scalarNode('dsn')->isRequired()->cannotBeEmpty()->end()
                            ->integerNode('pool_size')->min(1)->defaultValue(10)->end()
                            ->booleanNode('tls')->defaultTrue()->end()
                            ->arrayNode('options')
                                ->children()
                                    ->scalarNode('charset')->defaultValue('utf8mb4')->end()
                                    ->floatNode('timeout')->max(30.0)->defaultValue(5.0)->end()
                                ->end()
                            ->end()
                        ->end()
                        ->validate()
                            ->ifEmpty()
                            ->thenInvalid('Empty connection')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('tags')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->arrayNode('flags')
                    ->booleanPrototype()->defaultTrue()->end()
                ->end()
                ->arrayNode('weights')
                    ->floatPrototype()->max(10.0)->end()
                ->end()
                ->arrayNode('matrix')
                    ->arrayPrototype()
                        ->children()
                            ->integerNode('weight')->min(0)->end()
                            ->variableNode('payload')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    public function standaloneAndPrototypes0(BenchNodeBuilder $builder): ?ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('bench_0_0_standalone');
        $node
            ->prototype('INTEGER')->min(0)->end()
            ->prototype('enum')->values(['a', 'b'])->end()
            ->variablePrototype()->defaultNull()->end()
            ->validate()
                ->ifNull()
                ->thenEmptyArray()
            ->end();

        return $builder->end();
    }

    public function buildTree1(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('bench_0_1');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('locale')->isRequired()->defaultValue('en')->end()
                ->booleanNode('debug')->defaultFalse()->end()
                ->integerNode('ttl')->min(0)->defaultValue(60)->end()
                ->floatNode('ratio')->max(1.0)->defaultValue(0.5)->end()
                ->enumNode('mode')->values(['sync', 'async', 'batch'])->defaultValue('sync')->end()
                ->variableNode('extra')->defaultNull()->end()
                ->node('retries', 'integer')->min(0)->defaultValue(3)->end()
                ->node(type: 'scalar', name: 'channel')->cannotBeEmpty()->end()
                ->arrayNode('connections')
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifString()
                            ->then(static fn (string $v): array => ['dsn' => $v])
                        ->end()
                        ->children()
                            ->scalarNode('dsn')->isRequired()->cannotBeEmpty()->end()
                            ->integerNode('pool_size')->min(1)->defaultValue(10)->end()
                            ->booleanNode('tls')->defaultTrue()->end()
                            ->arrayNode('options')
                                ->children()
                                    ->scalarNode('charset')->defaultValue('utf8mb4')->end()
                                    ->floatNode('timeout')->max(30.0)->defaultValue(5.0)->end()
                                ->end()
                            ->end()
                        ->end()
                        ->validate()
                            ->ifEmpty()
                            ->thenInvalid('Empty connection')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('tags')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->arrayNode('flags')
                    ->booleanPrototype()->defaultTrue()->end()
                ->end()
                ->arrayNode('weights')
                    ->floatPrototype()->max(10.0)->end()
                ->end()
                ->arrayNode('matrix')
                    ->arrayPrototype()
                        ->children()
                            ->integerNode('weight')->min(0)->end()
                            ->variableNode('payload')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    public function standaloneAndPrototypes1(BenchNodeBuilder $builder): ?ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('bench_0_1_standalone');
        $node
            ->prototype('INTEGER')->min(0)->end()
            ->prototype('enum')->values(['a', 'b'])->end()
            ->variablePrototype()->defaultNull()->end()
            ->validate()
                ->ifNull()
                ->thenEmptyArray()
            ->end();

        return $builder->end();
    }

    public function buildTree2(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('bench_0_2');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('locale')->isRequired()->defaultValue('en')->end()
                ->booleanNode('debug')->defaultFalse()->end()
                ->integerNode('ttl')->min(0)->defaultValue(60)->end()
                ->floatNode('ratio')->max(1.0)->defaultValue(0.5)->end()
                ->enumNode('mode')->values(['sync', 'async', 'batch'])->defaultValue('sync')->end()
                ->variableNode('extra')->defaultNull()->end()
                ->node('retries', 'integer')->min(0)->defaultValue(3)->end()
                ->node(type: 'scalar', name: 'channel')->cannotBeEmpty()->end()
                ->arrayNode('connections')
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifString()
                            ->then(static fn (string $v): array => ['dsn' => $v])
                        ->end()
                        ->children()
                            ->scalarNode('dsn')->isRequired()->cannotBeEmpty()->end()
                            ->integerNode('pool_size')->min(1)->defaultValue(10)->end()
                            ->booleanNode('tls')->defaultTrue()->end()
                            ->arrayNode('options')
                                ->children()
                                    ->scalarNode('charset')->defaultValue('utf8mb4')->end()
                                    ->floatNode('timeout')->max(30.0)->defaultValue(5.0)->end()
                                ->end()
                            ->end()
                        ->end()
                        ->validate()
                            ->ifEmpty()
                            ->thenInvalid('Empty connection')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('tags')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->arrayNode('flags')
                    ->booleanPrototype()->defaultTrue()->end()
                ->end()
                ->arrayNode('weights')
                    ->floatPrototype()->max(10.0)->end()
                ->end()
                ->arrayNode('matrix')
                    ->arrayPrototype()
                        ->children()
                            ->integerNode('weight')->min(0)->end()
                            ->variableNode('payload')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    public function standaloneAndPrototypes2(BenchNodeBuilder $builder): ?ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('bench_0_2_standalone');
        $node
            ->prototype('INTEGER')->min(0)->end()
            ->prototype('enum')->values(['a', 'b'])->end()
            ->variablePrototype()->defaultNull()->end()
            ->validate()
                ->ifNull()
                ->thenEmptyArray()
            ->end();

        return $builder->end();
    }

    public function buildTree3(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('bench_0_3');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('locale')->isRequired()->defaultValue('en')->end()
                ->booleanNode('debug')->defaultFalse()->end()
                ->integerNode('ttl')->min(0)->defaultValue(60)->end()
                ->floatNode('ratio')->max(1.0)->defaultValue(0.5)->end()
                ->enumNode('mode')->values(['sync', 'async', 'batch'])->defaultValue('sync')->end()
                ->variableNode('extra')->defaultNull()->end()
                ->node('retries', 'integer')->min(0)->defaultValue(3)->end()
                ->node(type: 'scalar', name: 'channel')->cannotBeEmpty()->end()
                ->arrayNode('connections')
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifString()
                            ->then(static fn (string $v): array => ['dsn' => $v])
                        ->end()
                        ->children()
                            ->scalarNode('dsn')->isRequired()->cannotBeEmpty()->end()
                            ->integerNode('pool_size')->min(1)->defaultValue(10)->end()
                            ->booleanNode('tls')->defaultTrue()->end()
                            ->arrayNode('options')
                                ->children()
                                    ->scalarNode('charset')->defaultValue('utf8mb4')->end()
                                    ->floatNode('timeout')->max(30.0)->defaultValue(5.0)->end()
                                ->end()
                            ->end()
                        ->end()
                        ->validate()
                            ->ifEmpty()
                            ->thenInvalid('Empty connection')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('tags')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->arrayNode('flags')
                    ->booleanPrototype()->defaultTrue()->end()
                ->end()
                ->arrayNode('weights')
                    ->floatPrototype()->max(10.0)->end()
                ->end()
                ->arrayNode('matrix')
                    ->arrayPrototype()
                        ->children()
                            ->integerNode('weight')->min(0)->end()
                            ->variableNode('payload')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    public function standaloneAndPrototypes3(BenchNodeBuilder $builder): ?ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('bench_0_3_standalone');
        $node
            ->prototype('INTEGER')->min(0)->end()
            ->prototype('enum')->values(['a', 'b'])->end()
            ->variablePrototype()->defaultNull()->end()
            ->validate()
                ->ifNull()
                ->thenEmptyArray()
            ->end();

        return $builder->end();
    }

    public function buildTree4(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('bench_0_4');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('locale')->isRequired()->defaultValue('en')->end()
                ->booleanNode('debug')->defaultFalse()->end()
                ->integerNode('ttl')->min(0)->defaultValue(60)->end()
                ->floatNode('ratio')->max(1.0)->defaultValue(0.5)->end()
                ->enumNode('mode')->values(['sync', 'async', 'batch'])->defaultValue('sync')->end()
                ->variableNode('extra')->defaultNull()->end()
                ->node('retries', 'integer')->min(0)->defaultValue(3)->end()
                ->node(type: 'scalar', name: 'channel')->cannotBeEmpty()->end()
                ->arrayNode('connections')
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifString()
                            ->then(static fn (string $v): array => ['dsn' => $v])
                        ->end()
                        ->children()
                            ->scalarNode('dsn')->isRequired()->cannotBeEmpty()->end()
                            ->integerNode('pool_size')->min(1)->defaultValue(10)->end()
                            ->booleanNode('tls')->defaultTrue()->end()
                            ->arrayNode('options')
                                ->children()
                                    ->scalarNode('charset')->defaultValue('utf8mb4')->end()
                                    ->floatNode('timeout')->max(30.0)->defaultValue(5.0)->end()
                                ->end()
                            ->end()
                        ->end()
                        ->validate()
                            ->ifEmpty()
                            ->thenInvalid('Empty connection')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('tags')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->arrayNode('flags')
                    ->booleanPrototype()->defaultTrue()->end()
                ->end()
                ->arrayNode('weights')
                    ->floatPrototype()->max(10.0)->end()
                ->end()
                ->arrayNode('matrix')
                    ->arrayPrototype()
                        ->children()
                            ->integerNode('weight')->min(0)->end()
                            ->variableNode('payload')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    public function standaloneAndPrototypes4(BenchNodeBuilder $builder): ?ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('bench_0_4_standalone');
        $node
            ->prototype('INTEGER')->min(0)->end()
            ->prototype('enum')->values(['a', 'b'])->end()
            ->variablePrototype()->defaultNull()->end()
            ->validate()
                ->ifNull()
                ->thenEmptyArray()
            ->end();

        return $builder->end();
    }

    public function buildTree5(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('bench_0_5');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('locale')->isRequired()->defaultValue('en')->end()
                ->booleanNode('debug')->defaultFalse()->end()
                ->integerNode('ttl')->min(0)->defaultValue(60)->end()
                ->floatNode('ratio')->max(1.0)->defaultValue(0.5)->end()
                ->enumNode('mode')->values(['sync', 'async', 'batch'])->defaultValue('sync')->end()
                ->variableNode('extra')->defaultNull()->end()
                ->node('retries', 'integer')->min(0)->defaultValue(3)->end()
                ->node(type: 'scalar', name: 'channel')->cannotBeEmpty()->end()
                ->arrayNode('connections')
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifString()
                            ->then(static fn (string $v): array => ['dsn' => $v])
                        ->end()
                        ->children()
                            ->scalarNode('dsn')->isRequired()->cannotBeEmpty()->end()
                            ->integerNode('pool_size')->min(1)->defaultValue(10)->end()
                            ->booleanNode('tls')->defaultTrue()->end()
                            ->arrayNode('options')
                                ->children()
                                    ->scalarNode('charset')->defaultValue('utf8mb4')->end()
                                    ->floatNode('timeout')->max(30.0)->defaultValue(5.0)->end()
                                ->end()
                            ->end()
                        ->end()
                        ->validate()
                            ->ifEmpty()
                            ->thenInvalid('Empty connection')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('tags')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->arrayNode('flags')
                    ->booleanPrototype()->defaultTrue()->end()
                ->end()
                ->arrayNode('weights')
                    ->floatPrototype()->max(10.0)->end()
                ->end()
                ->arrayNode('matrix')
                    ->arrayPrototype()
                        ->children()
                            ->integerNode('weight')->min(0)->end()
                            ->variableNode('payload')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    public function standaloneAndPrototypes5(BenchNodeBuilder $builder): ?ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('bench_0_5_standalone');
        $node
            ->prototype('INTEGER')->min(0)->end()
            ->prototype('enum')->values(['a', 'b'])->end()
            ->variablePrototype()->defaultNull()->end()
            ->validate()
                ->ifNull()
                ->thenEmptyArray()
            ->end();

        return $builder->end();
    }

    public function buildTree6(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('bench_0_6');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('locale')->isRequired()->defaultValue('en')->end()
                ->booleanNode('debug')->defaultFalse()->end()
                ->integerNode('ttl')->min(0)->defaultValue(60)->end()
                ->floatNode('ratio')->max(1.0)->defaultValue(0.5)->end()
                ->enumNode('mode')->values(['sync', 'async', 'batch'])->defaultValue('sync')->end()
                ->variableNode('extra')->defaultNull()->end()
                ->node('retries', 'integer')->min(0)->defaultValue(3)->end()
                ->node(type: 'scalar', name: 'channel')->cannotBeEmpty()->end()
                ->arrayNode('connections')
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifString()
                            ->then(static fn (string $v): array => ['dsn' => $v])
                        ->end()
                        ->children()
                            ->scalarNode('dsn')->isRequired()->cannotBeEmpty()->end()
                            ->integerNode('pool_size')->min(1)->defaultValue(10)->end()
                            ->booleanNode('tls')->defaultTrue()->end()
                            ->arrayNode('options')
                                ->children()
                                    ->scalarNode('charset')->defaultValue('utf8mb4')->end()
                                    ->floatNode('timeout')->max(30.0)->defaultValue(5.0)->end()
                                ->end()
                            ->end()
                        ->end()
                        ->validate()
                            ->ifEmpty()
                            ->thenInvalid('Empty connection')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('tags')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->arrayNode('flags')
                    ->booleanPrototype()->defaultTrue()->end()
                ->end()
                ->arrayNode('weights')
                    ->floatPrototype()->max(10.0)->end()
                ->end()
                ->arrayNode('matrix')
                    ->arrayPrototype()
                        ->children()
                            ->integerNode('weight')->min(0)->end()
                            ->variableNode('payload')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    public function standaloneAndPrototypes6(BenchNodeBuilder $builder): ?ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('bench_0_6_standalone');
        $node
            ->prototype('INTEGER')->min(0)->end()
            ->prototype('enum')->values(['a', 'b'])->end()
            ->variablePrototype()->defaultNull()->end()
            ->validate()
                ->ifNull()
                ->thenEmptyArray()
            ->end();

        return $builder->end();
    }

    public function buildTree7(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('bench_0_7');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('locale')->isRequired()->defaultValue('en')->end()
                ->booleanNode('debug')->defaultFalse()->end()
                ->integerNode('ttl')->min(0)->defaultValue(60)->end()
                ->floatNode('ratio')->max(1.0)->defaultValue(0.5)->end()
                ->enumNode('mode')->values(['sync', 'async', 'batch'])->defaultValue('sync')->end()
                ->variableNode('extra')->defaultNull()->end()
                ->node('retries', 'integer')->min(0)->defaultValue(3)->end()
                ->node(type: 'scalar', name: 'channel')->cannotBeEmpty()->end()
                ->arrayNode('connections')
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifString()
                            ->then(static fn (string $v): array => ['dsn' => $v])
                        ->end()
                        ->children()
                            ->scalarNode('dsn')->isRequired()->cannotBeEmpty()->end()
                            ->integerNode('pool_size')->min(1)->defaultValue(10)->end()
                            ->booleanNode('tls')->defaultTrue()->end()
                            ->arrayNode('options')
                                ->children()
                                    ->scalarNode('charset')->defaultValue('utf8mb4')->end()
                                    ->floatNode('timeout')->max(30.0)->defaultValue(5.0)->end()
                                ->end()
                            ->end()
                        ->end()
                        ->validate()
                            ->ifEmpty()
                            ->thenInvalid('Empty connection')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('tags')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->arrayNode('flags')
                    ->booleanPrototype()->defaultTrue()->end()
                ->end()
                ->arrayNode('weights')
                    ->floatPrototype()->max(10.0)->end()
                ->end()
                ->arrayNode('matrix')
                    ->arrayPrototype()
                        ->children()
                            ->integerNode('weight')->min(0)->end()
                            ->variableNode('payload')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    public function standaloneAndPrototypes7(BenchNodeBuilder $builder): ?ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('bench_0_7_standalone');
        $node
            ->prototype('INTEGER')->min(0)->end()
            ->prototype('enum')->values(['a', 'b'])->end()
            ->variablePrototype()->defaultNull()->end()
            ->validate()
                ->ifNull()
                ->thenEmptyArray()
            ->end();

        return $builder->end();
    }

    public function buildTree8(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('bench_0_8');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('locale')->isRequired()->defaultValue('en')->end()
                ->booleanNode('debug')->defaultFalse()->end()
                ->integerNode('ttl')->min(0)->defaultValue(60)->end()
                ->floatNode('ratio')->max(1.0)->defaultValue(0.5)->end()
                ->enumNode('mode')->values(['sync', 'async', 'batch'])->defaultValue('sync')->end()
                ->variableNode('extra')->defaultNull()->end()
                ->node('retries', 'integer')->min(0)->defaultValue(3)->end()
                ->node(type: 'scalar', name: 'channel')->cannotBeEmpty()->end()
                ->arrayNode('connections')
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifString()
                            ->then(static fn (string $v): array => ['dsn' => $v])
                        ->end()
                        ->children()
                            ->scalarNode('dsn')->isRequired()->cannotBeEmpty()->end()
                            ->integerNode('pool_size')->min(1)->defaultValue(10)->end()
                            ->booleanNode('tls')->defaultTrue()->end()
                            ->arrayNode('options')
                                ->children()
                                    ->scalarNode('charset')->defaultValue('utf8mb4')->end()
                                    ->floatNode('timeout')->max(30.0)->defaultValue(5.0)->end()
                                ->end()
                            ->end()
                        ->end()
                        ->validate()
                            ->ifEmpty()
                            ->thenInvalid('Empty connection')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('tags')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->arrayNode('flags')
                    ->booleanPrototype()->defaultTrue()->end()
                ->end()
                ->arrayNode('weights')
                    ->floatPrototype()->max(10.0)->end()
                ->end()
                ->arrayNode('matrix')
                    ->arrayPrototype()
                        ->children()
                            ->integerNode('weight')->min(0)->end()
                            ->variableNode('payload')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    public function standaloneAndPrototypes8(BenchNodeBuilder $builder): ?ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('bench_0_8_standalone');
        $node
            ->prototype('INTEGER')->min(0)->end()
            ->prototype('enum')->values(['a', 'b'])->end()
            ->variablePrototype()->defaultNull()->end()
            ->validate()
                ->ifNull()
                ->thenEmptyArray()
            ->end();

        return $builder->end();
    }

    public function buildTree9(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('bench_0_9');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('locale')->isRequired()->defaultValue('en')->end()
                ->booleanNode('debug')->defaultFalse()->end()
                ->integerNode('ttl')->min(0)->defaultValue(60)->end()
                ->floatNode('ratio')->max(1.0)->defaultValue(0.5)->end()
                ->enumNode('mode')->values(['sync', 'async', 'batch'])->defaultValue('sync')->end()
                ->variableNode('extra')->defaultNull()->end()
                ->node('retries', 'integer')->min(0)->defaultValue(3)->end()
                ->node(type: 'scalar', name: 'channel')->cannotBeEmpty()->end()
                ->arrayNode('connections')
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifString()
                            ->then(static fn (string $v): array => ['dsn' => $v])
                        ->end()
                        ->children()
                            ->scalarNode('dsn')->isRequired()->cannotBeEmpty()->end()
                            ->integerNode('pool_size')->min(1)->defaultValue(10)->end()
                            ->booleanNode('tls')->defaultTrue()->end()
                            ->arrayNode('options')
                                ->children()
                                    ->scalarNode('charset')->defaultValue('utf8mb4')->end()
                                    ->floatNode('timeout')->max(30.0)->defaultValue(5.0)->end()
                                ->end()
                            ->end()
                        ->end()
                        ->validate()
                            ->ifEmpty()
                            ->thenInvalid('Empty connection')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('tags')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->arrayNode('flags')
                    ->booleanPrototype()->defaultTrue()->end()
                ->end()
                ->arrayNode('weights')
                    ->floatPrototype()->max(10.0)->end()
                ->end()
                ->arrayNode('matrix')
                    ->arrayPrototype()
                        ->children()
                            ->integerNode('weight')->min(0)->end()
                            ->variableNode('payload')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    public function standaloneAndPrototypes9(BenchNodeBuilder $builder): ?ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('bench_0_9_standalone');
        $node
            ->prototype('INTEGER')->min(0)->end()
            ->prototype('enum')->values(['a', 'b'])->end()
            ->variablePrototype()->defaultNull()->end()
            ->validate()
                ->ifNull()
                ->thenEmptyArray()
            ->end();

        return $builder->end();
    }

    public function buildTree10(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('bench_0_10');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('locale')->isRequired()->defaultValue('en')->end()
                ->booleanNode('debug')->defaultFalse()->end()
                ->integerNode('ttl')->min(0)->defaultValue(60)->end()
                ->floatNode('ratio')->max(1.0)->defaultValue(0.5)->end()
                ->enumNode('mode')->values(['sync', 'async', 'batch'])->defaultValue('sync')->end()
                ->variableNode('extra')->defaultNull()->end()
                ->node('retries', 'integer')->min(0)->defaultValue(3)->end()
                ->node(type: 'scalar', name: 'channel')->cannotBeEmpty()->end()
                ->arrayNode('connections')
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifString()
                            ->then(static fn (string $v): array => ['dsn' => $v])
                        ->end()
                        ->children()
                            ->scalarNode('dsn')->isRequired()->cannotBeEmpty()->end()
                            ->integerNode('pool_size')->min(1)->defaultValue(10)->end()
                            ->booleanNode('tls')->defaultTrue()->end()
                            ->arrayNode('options')
                                ->children()
                                    ->scalarNode('charset')->defaultValue('utf8mb4')->end()
                                    ->floatNode('timeout')->max(30.0)->defaultValue(5.0)->end()
                                ->end()
                            ->end()
                        ->end()
                        ->validate()
                            ->ifEmpty()
                            ->thenInvalid('Empty connection')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('tags')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->arrayNode('flags')
                    ->booleanPrototype()->defaultTrue()->end()
                ->end()
                ->arrayNode('weights')
                    ->floatPrototype()->max(10.0)->end()
                ->end()
                ->arrayNode('matrix')
                    ->arrayPrototype()
                        ->children()
                            ->integerNode('weight')->min(0)->end()
                            ->variableNode('payload')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    public function standaloneAndPrototypes10(BenchNodeBuilder $builder): ?ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('bench_0_10_standalone');
        $node
            ->prototype('INTEGER')->min(0)->end()
            ->prototype('enum')->values(['a', 'b'])->end()
            ->variablePrototype()->defaultNull()->end()
            ->validate()
                ->ifNull()
                ->thenEmptyArray()
            ->end();

        return $builder->end();
    }

    public function buildTree11(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('bench_0_11');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('locale')->isRequired()->defaultValue('en')->end()
                ->booleanNode('debug')->defaultFalse()->end()
                ->integerNode('ttl')->min(0)->defaultValue(60)->end()
                ->floatNode('ratio')->max(1.0)->defaultValue(0.5)->end()
                ->enumNode('mode')->values(['sync', 'async', 'batch'])->defaultValue('sync')->end()
                ->variableNode('extra')->defaultNull()->end()
                ->node('retries', 'integer')->min(0)->defaultValue(3)->end()
                ->node(type: 'scalar', name: 'channel')->cannotBeEmpty()->end()
                ->arrayNode('connections')
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifString()
                            ->then(static fn (string $v): array => ['dsn' => $v])
                        ->end()
                        ->children()
                            ->scalarNode('dsn')->isRequired()->cannotBeEmpty()->end()
                            ->integerNode('pool_size')->min(1)->defaultValue(10)->end()
                            ->booleanNode('tls')->defaultTrue()->end()
                            ->arrayNode('options')
                                ->children()
                                    ->scalarNode('charset')->defaultValue('utf8mb4')->end()
                                    ->floatNode('timeout')->max(30.0)->defaultValue(5.0)->end()
                                ->end()
                            ->end()
                        ->end()
                        ->validate()
                            ->ifEmpty()
                            ->thenInvalid('Empty connection')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('tags')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->arrayNode('flags')
                    ->booleanPrototype()->defaultTrue()->end()
                ->end()
                ->arrayNode('weights')
                    ->floatPrototype()->max(10.0)->end()
                ->end()
                ->arrayNode('matrix')
                    ->arrayPrototype()
                        ->children()
                            ->integerNode('weight')->min(0)->end()
                            ->variableNode('payload')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    public function standaloneAndPrototypes11(BenchNodeBuilder $builder): ?ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('bench_0_11_standalone');
        $node
            ->prototype('INTEGER')->min(0)->end()
            ->prototype('enum')->values(['a', 'b'])->end()
            ->variablePrototype()->defaultNull()->end()
            ->validate()
                ->ifNull()
                ->thenEmptyArray()
            ->end();

        return $builder->end();
    }
}

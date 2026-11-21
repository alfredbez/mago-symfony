<?php

declare(strict_types=1);

namespace SymfonyFixture;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;

final class CustomNodeDefinition extends ScalarNodeDefinition
{
    private bool $customFlag = false;

    public function customSetting(): static
    {
        $this->customFlag = true;

        return $this;
    }

    public function hasCustomFlag(): bool
    {
        return $this->customFlag;
    }
}

final class CustomScalarNodeDefinition extends ScalarNodeDefinition
{
    public function customScalarSetting(): static
    {
        return $this->cannotBeEmpty();
    }
}

final class ExtendedNodeBuilder extends NodeBuilder
{
    public function builderSpecificHelper(): string
    {
        return 'ok';
    }
}

final class CustomNodeBuilderConfig
{
    public function setNodeClassAndNestedInheritance(): ArrayNodeDefinition
    {
        $root = new ArrayNodeDefinition('root');

        return $root
            ->children()
                ->setNodeClass('custom', CustomNodeDefinition::class)
                ->setNodeClass('scalar', CustomScalarNodeDefinition::class)
                ->node('first', 'custom')
                    ->customSetting()
                ->end()
                ->scalarNode('overridden')
                    ->customScalarSetting()
                ->end()
                ->arrayNode('nested')
                    ->children()
                        ->node('inner', 'custom')
                            ->customSetting()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('items')
                    ->prototype('scalar')
                        ->customScalarSetting()
                    ->end()
                ->end()
                ->arrayNode('shortcutItems')
                    ->scalarPrototype()
                        ->customScalarSetting()
                    ->end()
                ->end()
                ->arrayNode('deepPrototypeItems')
                    ->arrayPrototype()
                        ->children()
                            ->node('deep', 'custom')
                                ->customSetting()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    public function customBuilderSubclassPreserved(): string
    {
        $root = new ArrayNodeDefinition('root');
        $builder = (new ExtendedNodeBuilder())->setParent($root);

        return $builder
            ->scalarNode('a')->end()
            ->arrayNode('nested')
                ->children()
                    ->scalarNode('b')->end()
                    ->builderSpecificHelper();
    }
}

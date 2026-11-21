<?php

declare(strict_types=1);

namespace SymfonyFixture;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

final class ConfigNodes
{
    public function nodes(): ArrayNodeDefinition
    {
        return (new ArrayNodeDefinition('root'))->children()
            ->scalarNode('s')->defaultValue('x')->end()
            ->booleanNode('b')->defaultTrue()->end()
            ->integerNode('i')->min(0)->end()
            ->floatNode('f')->max(2.0)->end()
            ->enumNode('e')->values(['x'])->end()
            ->variableNode('v')->defaultNull()->end()
            ->arrayNode('a')->prototype('array')->children()
                ->node(type: 'integer', name: 'n')->min(0)->end()
            ->end()->end()->end()
        ->end();
    }

    public function prototypes(): ArrayNodeDefinition
    {
        return (new ArrayNodeDefinition('items'))
            ->prototype('scalar')->cannotBeEmpty()->end()
            ->booleanPrototype()->defaultTrue()->end()
            ->prototype('integer')->min(0)->end()
            ->floatPrototype()->max(2.0)->end()
            ->prototype('enum')->values(['x'])->end()
            ->variablePrototype()->defaultNull()->end()
            ->arrayPrototype()->children()->scalarNode('s')->end()->end()->end();
    }
}

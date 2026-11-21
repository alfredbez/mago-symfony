<?php

declare(strict_types=1);

namespace SymfonyFixture;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

final class ConfigNodesNegative
{
    public function unparentedBuilder(): void
    {
        (new NodeBuilder())->end()->children();
        (new NodeBuilder())->scalarNode('s')->end()->scalarNode('past_root');
    }

    public function concreteNodes(): void
    {
        $root = new ArrayNodeDefinition('root');
        $root->children()->scalarNode('s')->children();
        $root->children()->booleanNode('b')->min(0);
        $root->children()->integerNode('i')->values(['x']);
        $root->children()->floatNode('f')->children();
        $root->children()->enumNode('e')->children();
        $root->children()->variableNode('v')->children();
        $root->children()->arrayNode('a')->min(0);
        $root->scalarPrototype()->end()->min(0);
    }
}

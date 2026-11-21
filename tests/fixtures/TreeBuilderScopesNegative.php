<?php

declare(strict_types=1);

namespace SymfonyFixture;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

final class TreeBuilderScopesNegative
{
    public function nestedTreeAssignment(): void
    {
        $tree = new TreeBuilder('root', 'scalar');
        $factory = static function (): TreeBuilder {
            $tree = new TreeBuilder('inner');
            return $tree;
        };
        $factory();
        $tree->getRootNode()->children();
    }

    public function nestedRootAssignment(): void
    {
        $tree = new TreeBuilder('root', 'scalar');
        $root = $tree->getRootNode();
        $factory = static function (): ArrayNodeDefinition {
            $root = new ArrayNodeDefinition('inner');
            return $root;
        };
        $factory();
        $root->children();
    }
}

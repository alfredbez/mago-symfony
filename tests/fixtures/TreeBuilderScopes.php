<?php

declare(strict_types=1);

namespace SymfonyFixture;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

final class TreeBuilderScopes
{
    public function nestedTreeAssignment(): void
    {
        $tree = new TreeBuilder('root');
        $factory = static function (): TreeBuilder {
            $tree = new TreeBuilder('inner', 'scalar');
            return $tree;
        };
        $factory();
        $tree->getRootNode()->children();
    }

    public function shadowedRoot(): void
    {
        $tree = new TreeBuilder('root', 'scalar');
        $root = $tree->getRootNode();
        $configure = static function (ArrayNodeDefinition $root): void {
            $root->children();
        };
        $configure(new ArrayNodeDefinition('inner'));
        $root->isRequired();
    }
}

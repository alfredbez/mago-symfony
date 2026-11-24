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
        $factory = static function (): void {
            $tree = new TreeBuilder('inner', 'scalar');
            $tree->getRootNode()->isRequired();
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

    /** @param list<TreeBuilder> $trees */
    public function foreachReboundTree(array $trees): void
    {
        $tree = new TreeBuilder('old', 'scalar');
        $tree->getRootNode()->isRequired();
        foreach ($trees as $tree) {
            $tree->getRootNode()->children();
        }
    }
}

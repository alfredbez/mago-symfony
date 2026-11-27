<?php

declare(strict_types=1);

use Symfony\Component\Config\Definition\Builder\TreeBuilder;

final class GlobalEnumTreeBuilderNegative
{
    public function invalidChildrenOnEnum(): void
    {
        $treeBuilder = new TreeBuilder('global_enum', 'enum');
        $treeBuilder->getRootNode()->children();
    }
}

function globalScalarTreeBuilderNegative(): void
{
    $treeBuilder = new TreeBuilder('global_scalar', 'scalar');
    $treeBuilder->getRootNode()->children();
}

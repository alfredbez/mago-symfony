<?php

declare(strict_types=1);

use Symfony\Component\Config\Definition\Builder\TreeBuilder;

final class GlobalEnumTreeBuilderConfig
{
    public function configure(): void
    {
        $treeBuilder = new TreeBuilder('global_enum', 'enum');
        $treeBuilder->getRootNode()->values(['a', 'b'])->isRequired();
    }
}

function globalScalarTreeBuilderConfig(): void
{
    $treeBuilder = new TreeBuilder('global_scalar', 'scalar');
    $treeBuilder->getRootNode()->defaultValue('ok')->isRequired();
}

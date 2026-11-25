<?php

declare(strict_types=1);

namespace SymfonyFixture;

use Symfony\Component\Config\Definition\Builder\{ArrayNodeDefinition, TreeBuilder};
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class StraightLineArrayConfig implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('straight');
        $treeBuilder->getRootNode()->children()->scalarNode('name')->end();

        return $treeBuilder;
    }
}

final class BothBranchesArrayConfig implements ConfigurationInterface
{
    public function __construct(private readonly bool $flag) {}

    public function getConfigTreeBuilder(): TreeBuilder
    {
        if ($this->flag) {
            $treeBuilder = new TreeBuilder('branch_a');
        } else {
            $treeBuilder = new TreeBuilder('branch_b', type: 'array');
        }

        return $treeBuilder;
    }
}

final class PostLoopOverwriteArrayConfig implements ConfigurationInterface
{
    /** @param list<string> $items */
    public function __construct(private readonly array $items) {}

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('initial', 'scalar');
        foreach ($this->items as $_item) {
            $treeBuilder = new TreeBuilder('loop_scalar', 'scalar');
        }
        $treeBuilder = new TreeBuilder('final_array');

        return $treeBuilder;
    }
}

final class ClosureDoesNotLeakArrayConfig implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('outer_array');
        $inner = static function (): void {
            $treeBuilder = new TreeBuilder('inner_scalar', 'scalar');
            $treeBuilder->getRootNode()->isRequired();
        };
        $inner();

        return $treeBuilder;
    }
}

final class EarlyReturnAndMatchArrayConfig implements ConfigurationInterface
{
    public function __construct(private readonly int $mode) {}

    public function getConfigTreeBuilder(): TreeBuilder
    {
        if ($this->mode === 0) {
            $early = new TreeBuilder('early_array');

            return $early;
        }

        return match ($this->mode) {
            1 => new TreeBuilder('match_a'),
            default => new TreeBuilder('match_b', 'array'),
        };
    }
}

final class GroupUseArrayConfig implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('group_use');
        $treeBuilder->getRootNode()->children()->scalarNode('item')->end();
        $_standalone = new ArrayNodeDefinition('standalone');

        return $treeBuilder;
    }
}

final class ArrowDefaultWithDoubleArrowArrayConfig implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('outer_array');
        $mutator = static fn (array $defaults = ['k' => 'v']): object => $treeBuilder = new TreeBuilder('inner_scalar', 'scalar');
        $mutator();
        $arrayFactory = static fn (array $defaults = ['k' => 'v']): TreeBuilder => new TreeBuilder('inner_array');
        $arrayFactory();

        return $treeBuilder;
    }
}

final class AnonymousClassPropertyArrayConfig implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('outer_array');
        $_holder = new class {
            public ?TreeBuilder $treeBuilder = null;
        };

        return $treeBuilder;
    }
}

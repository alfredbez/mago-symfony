<?php

declare(strict_types=1);

namespace SymfonyFixture;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class ConditionalScalarToArrayConfig implements ConfigurationInterface
{
    public function __construct(private readonly bool $flag) {}

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('root', 'scalar');
        if ($this->flag) {
            $treeBuilder = new TreeBuilder('root');
        }

        return $treeBuilder;
    }
}

final class ConditionalArrayToScalarConfig implements ConfigurationInterface
{
    public function __construct(private readonly bool $flag) {}

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('root');
        if ($this->flag) {
            $treeBuilder = new TreeBuilder('root', 'scalar');
        }

        return $treeBuilder;
    }
}

final class LoopConditionalRebindConfig implements ConfigurationInterface
{
    /** @param list<string> $items */
    public function __construct(private readonly array $items) {}

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('root', 'scalar');
        foreach ($this->items as $_item) {
            $treeBuilder = new TreeBuilder('root');
        }

        return $treeBuilder;
    }
}

final class DynamicRootTypeConfig implements ConfigurationInterface
{
    /** @param 'array'|'scalar' $rootType */
    public function __construct(private readonly string $rootType) {}

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('root', $this->rootType);

        return $treeBuilder;
    }
}

final class TernaryMixedRootConfig implements ConfigurationInterface
{
    public function __construct(private readonly bool $flag) {}

    public function getConfigTreeBuilder(): TreeBuilder
    {
        return $this->flag
            ? new TreeBuilder('root')
            : new TreeBuilder('root', 'scalar');
    }
}

final class TryCatchIncompleteRebindConfig implements ConfigurationInterface
{
    public function __construct(private readonly bool $fail) {}

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('root', 'scalar');
        try {
            if ($this->fail) {
                throw new \RuntimeException('abort');
            }
            $treeBuilder = new TreeBuilder('root');
        } catch (\RuntimeException) {
        }

        return $treeBuilder;
    }
}

final class TryNestedIfThrowConfig implements ConfigurationInterface
{
    public function __construct(private readonly bool $flag) {}

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('root');
        try {
            if ($this->flag) {
                $treeBuilder = new TreeBuilder('root', 'scalar');
                throw new \RuntimeException('abort');
            }
        } catch (\RuntimeException) {
        }

        return $treeBuilder;
    }
}

final class TryNestedIfReturnFinallyConfig implements ConfigurationInterface
{
    public function __construct(private readonly bool $flag) {}

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('root');
        try {
            if ($this->flag) {
                $treeBuilder = new TreeBuilder('root', 'scalar');

                return new TreeBuilder('root');
            }
        } finally {
            return $treeBuilder;
        }
    }
}

final class TryBareBlockThrowConfig implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('root');
        try {
            {
                $treeBuilder = new TreeBuilder('root', 'scalar');
                throw new \RuntimeException('abort');
            }
        } catch (\RuntimeException) {
        }

        return $treeBuilder;
    }
}

final class TwoHopWhileLoopConfig implements ConfigurationInterface
{
    public function __construct(private readonly int $iterations) {}

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $a = new TreeBuilder('root');
        $b = new TreeBuilder('root');
        $c = new TreeBuilder('root', 'scalar');
        $i = 0;
        while ($i < $this->iterations) {
            $a = $b;
            $b = $c;
            ++$i;
        }

        return $a;
    }
}

final class TwoHopForLoopConfig implements ConfigurationInterface
{
    public function __construct(private readonly int $iterations) {}

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $a = new TreeBuilder('root');
        $b = new TreeBuilder('root');
        $c = new TreeBuilder('root', 'scalar');
        for ($i = 0; $i < $this->iterations; ++$i) {
            $a = $b;
            $b = $c;
        }

        return $a;
    }
}

final class UnpackedArgsTreeBuilderConfig implements ConfigurationInterface
{
    /** @param array{string, 'array'|'scalar'} $args */
    public function __construct(private readonly array $args) {}

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(...$this->args);

        return $treeBuilder;
    }
}

final class NestedAssignmentInCallArgConfig implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('root');
        $this->noop($x = $treeBuilder = new TreeBuilder('root', 'scalar'));
        unset($x);

        return $treeBuilder;
    }

    private function noop(mixed $value): void
    {
        unset($value);
    }
}

final class DestructuringInIfConditionConfig implements ConfigurationInterface
{
    public function __construct(private readonly int $count) {}

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('root');
        if (\count([$treeBuilder] = [new TreeBuilder('root', 'scalar')]) === $this->count) {
            $treeBuilder->getRootNode();
        }

        return $treeBuilder;
    }
}

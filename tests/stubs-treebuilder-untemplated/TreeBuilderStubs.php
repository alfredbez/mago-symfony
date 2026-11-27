<?php

declare(strict_types=1);

namespace Symfony\Component\Config\Definition\Builder;

interface NodeParentInterface {}

interface ParentNodeDefinitionInterface {}

class NodeDefinition implements NodeParentInterface
{
    public function isRequired(): static
    {
        return $this;
    }

    public function defaultValue(mixed $value): static
    {
        return $this;
    }

    public function end(): ?NodeParentInterface
    {
        return null;
    }
}

class VariableNodeDefinition extends NodeDefinition {}

class ScalarNodeDefinition extends VariableNodeDefinition {}

class EnumNodeDefinition extends ScalarNodeDefinition
{
    /** @param list<mixed> $values */
    public function values(array $values): static
    {
        return $this;
    }
}

class ArrayNodeDefinition extends NodeDefinition implements ParentNodeDefinitionInterface
{
    public function children(): NodeBuilder
    {
        return new NodeBuilder();
    }
}

class NodeBuilder implements NodeParentInterface
{
    public function end(): ?NodeParentInterface
    {
        return null;
    }
}

class TreeBuilder implements NodeParentInterface
{
    public function __construct(string $name, string $type = 'array', ?NodeBuilder $builder = null) {}

    public function getRootNode(): NodeDefinition|ArrayNodeDefinition
    {
        return new ArrayNodeDefinition();
    }
}

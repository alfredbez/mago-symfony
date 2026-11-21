<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;

/**
 * Tracks parent-child fluent chains across Symfony Config TreeBuilder nodes.
 *
 * - `ArrayNodeDefinition::children()` returns `NodeBuilder` with the array node as parent.
 * - `ArrayNodeDefinition::prototype($type)` and `*Prototype()` return the concrete
 *   `*NodeDefinition` subclass with the array node as parent.
 * - `NodeBuilder::*Node()` / `node()` return the concrete `*NodeDefinition` with the builder as parent.
 * - `NodeDefinition::validate()` / `beforeNormalization()` return `ExprBuilder` with the node as parent.
 * - `end()` on `NodeDefinition`, `NodeBuilder`, `ExprBuilder`, or `MergeBuilder` pops the tracked
 *   parent type (or returns `null` past an unparented root node).
 */
final class ConfigEndReturnTypeProvider implements MethodReturnTypeProvider
{
    private const NS = 'Symfony\\Component\\Config\\Definition\\Builder\\';
    private const NODE_BUILDER = self::NS . 'NodeBuilder';
    private const NODE_DEFINITION = self::NS . 'NodeDefinition';
    private const ARRAY_NODE_DEFINITION = self::NS . 'ArrayNodeDefinition';
    private const EXPR_BUILDER = self::NS . 'ExprBuilder';
    private const MERGE_BUILDER = self::NS . 'MergeBuilder';

    private const NODE_TYPE_MAP = [
        'variable' => self::NS . 'VariableNodeDefinition',
        'scalar' => self::NS . 'ScalarNodeDefinition',
        'boolean' => self::NS . 'BooleanNodeDefinition',
        'integer' => self::NS . 'IntegerNodeDefinition',
        'float' => self::NS . 'FloatNodeDefinition',
        'array' => self::ARRAY_NODE_DEFINITION,
        'enum' => self::NS . 'EnumNodeDefinition',
    ];

    private const BUILDER_NODE_METHODS = [
        'arraynode' => self::ARRAY_NODE_DEFINITION,
        'booleannode' => self::NODE_TYPE_MAP['boolean'],
        'enumnode' => self::NODE_TYPE_MAP['enum'],
        'floatnode' => self::NODE_TYPE_MAP['float'],
        'integernode' => self::NODE_TYPE_MAP['integer'],
        'scalarnode' => self::NODE_TYPE_MAP['scalar'],
        'variablenode' => self::NODE_TYPE_MAP['variable'],
    ];

    private const PROTOTYPE_SHORTCUTS = [
        'arrayprototype' => self::ARRAY_NODE_DEFINITION,
        'booleanprototype' => self::NODE_TYPE_MAP['boolean'],
        'enumprototype' => self::NODE_TYPE_MAP['enum'],
        'floatprototype' => self::NODE_TYPE_MAP['float'],
        'integerprototype' => self::NODE_TYPE_MAP['integer'],
        'scalarprototype' => self::NODE_TYPE_MAP['scalar'],
        'variableprototype' => self::NODE_TYPE_MAP['variable'],
    ];

    public function getTargets(): array
    {
        $targets = [
            MethodTarget::exact(self::NODE_DEFINITION, 'end'),
            MethodTarget::exact(self::NODE_DEFINITION, 'validate'),
            MethodTarget::exact(self::NODE_DEFINITION, 'beforeNormalization'),
            MethodTarget::exact(self::ARRAY_NODE_DEFINITION, 'children'),
            MethodTarget::exact(self::ARRAY_NODE_DEFINITION, 'prototype'),
            MethodTarget::exact(self::NODE_BUILDER, 'end'),
            MethodTarget::exact(self::NODE_BUILDER, 'node'),
            MethodTarget::exact(self::EXPR_BUILDER, 'end'),
            MethodTarget::exact(self::MERGE_BUILDER, 'end'),
        ];

        foreach (array_keys(self::BUILDER_NODE_METHODS) as $method) {
            $targets[] = MethodTarget::exact(self::NODE_BUILDER, $method);
        }

        foreach (array_keys(self::PROTOTYPE_SHORTCUTS) as $method) {
            $targets[] = MethodTarget::exact(self::ARRAY_NODE_DEFINITION, $method);
        }

        return $targets;
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $method = strtolower($context->invocation->name);
        $receiver = $context->invocation->receiverType;

        if ($method === 'end') {
            return $this->resolveEnd($context, $receiver);
        }

        if ($method === 'children') {
            return $this->withParent(self::NODE_BUILDER, $receiver);
        }

        if ($method === 'validate' || $method === 'beforenormalization') {
            return $this->withParent(self::EXPR_BUILDER, $receiver);
        }

        if ($method === 'prototype') {
            $typeArg = $context->invocation->getArgument(0, 'type')?->type?->getLiteralString();
            if ($typeArg === null) {
                return null;
            }

            $nodeClass = self::NODE_TYPE_MAP[strtolower($typeArg)] ?? null;
            if ($nodeClass === null || !$context->codebase->classExists($nodeClass)) {
                return null;
            }

            return $this->withParent($nodeClass, $receiver);
        }

        if (isset(self::PROTOTYPE_SHORTCUTS[$method])) {
            $nodeClass = self::PROTOTYPE_SHORTCUTS[$method];
            if (!$context->codebase->classExists($nodeClass)) {
                return null;
            }

            return $this->withParent($nodeClass, $receiver);
        }

        if ($method === 'node') {
            $typeArg = $context->invocation->getArgument(1, 'type')?->type?->getLiteralString();
            if ($typeArg === null) {
                return null;
            }

            $nodeClass = self::NODE_TYPE_MAP[strtolower($typeArg)] ?? null;
            if ($nodeClass === null || !$context->codebase->classExists($nodeClass)) {
                return null;
            }

            return $this->withParent($nodeClass, $this->nodeParent($receiver));
        }

        if (isset(self::BUILDER_NODE_METHODS[$method])) {
            $nodeClass = self::BUILDER_NODE_METHODS[$method];
            if (!$context->codebase->classExists($nodeClass)) {
                return null;
            }

            return $this->withParent($nodeClass, $this->nodeParent($receiver));
        }

        return null;
    }

    private function nodeParent(?Type $builder): ?Type
    {
        if ($builder === null || $this->extractParent($builder) !== null) {
            return $builder;
        }

        // NodeBuilder::append() sets the child's parent only when the builder has a parent.
        return Type::union($builder, Type::null());
    }

    private function resolveEnd(ReturnTypeProviderContext $context, ?Type $receiver): ?Type
    {
        $trackedParent = $this->extractParent($receiver);
        if ($trackedParent !== null) {
            return $trackedParent;
        }

        $declaring = $context->invocation->declaringClass;
        if ($declaring !== null && $this->isOrExtends($context->codebase, $declaring, self::NODE_BUILDER)) {
            return Type::union(Type::namedObject(self::ARRAY_NODE_DEFINITION), Type::null());
        }

        // Calling end() directly on an unparented NodeDefinition (e.g. past the root node
        // or on a standalone `new ArrayNodeDefinition()`) yields null at runtime.
        if ($declaring !== null && $this->isOrExtends($context->codebase, $declaring, self::NODE_DEFINITION)) {
            return Type::null();
        }

        return null;
    }

    private function withParent(string $class, ?Type $parent): Type
    {
        if ($parent === null) {
            return Type::namedObject($class);
        }

        return Type::fromAtomic(new NamedObjectType(
            name: $class,
            parameters: [$parent],
            variances: null,
            static: false,
            isThis: false,
            intersections: null,
            remappedParameters: false,
        ));
    }

    private function extractParent(?Type $type): ?Type
    {
        if ($type === null) {
            return null;
        }

        foreach ($type->atomicTypes as $atomic) {
            if ($atomic instanceof NamedObjectType && $atomic->parameters !== null && isset($atomic->parameters[0])) {
                return $atomic->parameters[0];
            }
        }

        return null;
    }

    private function isOrExtends(Codebase $codebase, string $class, string $target): bool
    {
        $lowerTarget = strtolower($target);
        if (strtolower($class) === $lowerTarget) {
            return true;
        }

        foreach ($codebase->getClassAncestors($class) as $ancestor) {
            if (strtolower($ancestor) === $lowerTarget) {
                return true;
            }
        }

        return false;
    }
}

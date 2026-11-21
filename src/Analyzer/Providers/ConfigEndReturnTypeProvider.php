<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\Analyzer\Type\SimpleAtomicType;
use Mago\Sdk\Analyzer\Type\SimpleAtomicTypeKind;

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

    private const LOWER_NODE_BUILDER = 'symfony\\component\\config\\definition\\builder\\nodebuilder';
    private const LOWER_NODE_DEFINITION = 'symfony\\component\\config\\definition\\builder\\nodedefinition';
    private const LOWER_EXPR_BUILDER = 'symfony\\component\\config\\definition\\builder\\exprbuilder';
    private const LOWER_MERGE_BUILDER = 'symfony\\component\\config\\definition\\builder\\mergebuilder';

    private const NODE_TYPE_MAP = [
        'variable' => self::NS . 'VariableNodeDefinition',
        'scalar' => self::NS . 'ScalarNodeDefinition',
        'string' => self::NS . 'StringNodeDefinition',
        'boolean' => self::NS . 'BooleanNodeDefinition',
        'integer' => self::NS . 'IntegerNodeDefinition',
        'float' => self::NS . 'FloatNodeDefinition',
        'array' => self::ARRAY_NODE_DEFINITION,
        'enum' => self::NS . 'EnumNodeDefinition',
    ];

    private const BUILTIN_NODE_DEFINITIONS = [
        self::NODE_DEFINITION => true,
        self::ARRAY_NODE_DEFINITION => true,
        self::NS . 'ScalarNodeDefinition' => true,
        self::NS . 'StringNodeDefinition' => true,
        self::NS . 'BooleanNodeDefinition' => true,
        self::NS . 'IntegerNodeDefinition' => true,
        self::NS . 'FloatNodeDefinition' => true,
        self::NS . 'EnumNodeDefinition' => true,
        self::NS . 'VariableNodeDefinition' => true,
    ];

    private const LOWER_BUILTIN_NODE_DEFINITIONS = [
        self::LOWER_NODE_DEFINITION => true,
        'symfony\\component\\config\\definition\\builder\\arraynodedefinition' => true,
        'symfony\\component\\config\\definition\\builder\\scalarnodedefinition' => true,
        'symfony\\component\\config\\definition\\builder\\stringnodedefinition' => true,
        'symfony\\component\\config\\definition\\builder\\booleannodedefinition' => true,
        'symfony\\component\\config\\definition\\builder\\integernodedefinition' => true,
        'symfony\\component\\config\\definition\\builder\\floatnodedefinition' => true,
        'symfony\\component\\config\\definition\\builder\\enumnodedefinition' => true,
        'symfony\\component\\config\\definition\\builder\\variablenodedefinition' => true,
    ];

    private const BUILDER_NODE_METHODS = [
        'arraynode' => self::ARRAY_NODE_DEFINITION,
        'booleannode' => self::NODE_TYPE_MAP['boolean'],
        'enumnode' => self::NODE_TYPE_MAP['enum'],
        'floatnode' => self::NODE_TYPE_MAP['float'],
        'integernode' => self::NODE_TYPE_MAP['integer'],
        'scalarnode' => self::NODE_TYPE_MAP['scalar'],
        'stringnode' => self::NODE_TYPE_MAP['string'],
        'variablenode' => self::NODE_TYPE_MAP['variable'],
    ];

    private const PROTOTYPE_SHORTCUTS = [
        'arrayprototype' => self::ARRAY_NODE_DEFINITION,
        'booleanprototype' => self::NODE_TYPE_MAP['boolean'],
        'enumprototype' => self::NODE_TYPE_MAP['enum'],
        'floatprototype' => self::NODE_TYPE_MAP['float'],
        'integerprototype' => self::NODE_TYPE_MAP['integer'],
        'scalarprototype' => self::NODE_TYPE_MAP['scalar'],
        'stringprototype' => self::NODE_TYPE_MAP['string'],
        'variableprototype' => self::NODE_TYPE_MAP['variable'],
    ];

    private ?Type $nullType = null;
    private ?Type $nullableArrayNodeType = null;

    /** @var array<string, Type> */
    private array $plainTypes = [];

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
        $receiver = $context->invocation->receiverType;
        $name = $context->invocation->name;

        return match ($name) {
            'end' => $this->resolveEnd($context, $receiver),
            'children' => $this->withParent(self::NODE_BUILDER, $receiver),
            'validate', 'beforeNormalization' => $this->withParent(self::EXPR_BUILDER, $receiver),
            'prototype' => $this->resolvePrototype($context, $receiver),
            'node' => $this->resolveDynamicNode($context, $receiver),
            'arrayNode' => $this->resolveBuilderNode($context, self::ARRAY_NODE_DEFINITION, $receiver),
            'booleanNode' => $this->resolveBuilderNode($context, self::NODE_TYPE_MAP['boolean'], $receiver),
            'enumNode' => $this->resolveBuilderNode($context, self::NODE_TYPE_MAP['enum'], $receiver),
            'floatNode' => $this->resolveBuilderNode($context, self::NODE_TYPE_MAP['float'], $receiver),
            'integerNode' => $this->resolveBuilderNode($context, self::NODE_TYPE_MAP['integer'], $receiver),
            'scalarNode' => $this->resolveBuilderNode($context, self::NODE_TYPE_MAP['scalar'], $receiver),
            'stringNode' => $this->resolveBuilderNode($context, self::NODE_TYPE_MAP['string'], $receiver),
            'variableNode' => $this->resolveBuilderNode($context, self::NODE_TYPE_MAP['variable'], $receiver),
            'arrayPrototype' => $this->resolvePrototypeShortcut($context, self::ARRAY_NODE_DEFINITION, $receiver),
            'booleanPrototype' => $this->resolvePrototypeShortcut($context, self::NODE_TYPE_MAP['boolean'], $receiver),
            'enumPrototype' => $this->resolvePrototypeShortcut($context, self::NODE_TYPE_MAP['enum'], $receiver),
            'floatPrototype' => $this->resolvePrototypeShortcut($context, self::NODE_TYPE_MAP['float'], $receiver),
            'integerPrototype' => $this->resolvePrototypeShortcut($context, self::NODE_TYPE_MAP['integer'], $receiver),
            'scalarPrototype' => $this->resolvePrototypeShortcut($context, self::NODE_TYPE_MAP['scalar'], $receiver),
            'stringPrototype' => $this->resolvePrototypeShortcut($context, self::NODE_TYPE_MAP['string'], $receiver),
            'variablePrototype' => $this->resolvePrototypeShortcut($context, self::NODE_TYPE_MAP['variable'], $receiver),
            default => $this->dispatchNormalizedMethod($context, strtolower($name), $receiver),
        };
    }

    private function dispatchNormalizedMethod(ReturnTypeProviderContext $context, string $method, ?Type $receiver): ?Type
    {
        return match ($method) {
            'end' => $this->resolveEnd($context, $receiver),
            'children' => $this->withParent(self::NODE_BUILDER, $receiver),
            'validate', 'beforenormalization' => $this->withParent(self::EXPR_BUILDER, $receiver),
            'prototype' => $this->resolvePrototype($context, $receiver),
            'node' => $this->resolveDynamicNode($context, $receiver),
            default => match (true) {
                isset(self::BUILDER_NODE_METHODS[$method]) => $this->resolveBuilderNode($context, self::BUILDER_NODE_METHODS[$method], $receiver),
                isset(self::PROTOTYPE_SHORTCUTS[$method]) => $this->resolvePrototypeShortcut($context, self::PROTOTYPE_SHORTCUTS[$method], $receiver),
                default => null,
            },
        };
    }

    private function resolvePrototype(ReturnTypeProviderContext $context, ?Type $receiver): ?Type
    {
        $typeArg = $context->invocation->getArgument(0, 'type')?->type?->getLiteralString();
        if ($typeArg === null) {
            return null;
        }

        $nodeClass = self::NODE_TYPE_MAP[$typeArg] ?? self::NODE_TYPE_MAP[strtolower($typeArg)] ?? null;
        if ($nodeClass === null || !$context->codebase->classExists($nodeClass)) {
            return null;
        }

        return $this->withParent($nodeClass, $receiver);
    }

    private function resolvePrototypeShortcut(ReturnTypeProviderContext $context, string $nodeClass, ?Type $receiver): ?Type
    {
        if (!$context->codebase->classExists($nodeClass)) {
            return null;
        }

        return $this->withParent($nodeClass, $receiver);
    }

    private function resolveDynamicNode(ReturnTypeProviderContext $context, ?Type $receiver): ?Type
    {
        $typeArg = $context->invocation->getArgument(1, 'type')?->type?->getLiteralString();
        if ($typeArg === null) {
            return null;
        }

        $nodeClass = self::NODE_TYPE_MAP[$typeArg] ?? self::NODE_TYPE_MAP[strtolower($typeArg)] ?? null;
        if ($nodeClass === null || !$context->codebase->classExists($nodeClass)) {
            return null;
        }

        return $this->withParent($nodeClass, $this->nodeParent($receiver));
    }

    private function resolveBuilderNode(ReturnTypeProviderContext $context, string $nodeClass, ?Type $receiver): ?Type
    {
        if (!$context->codebase->classExists($nodeClass)) {
            return null;
        }

        return $this->withParent($nodeClass, $this->nodeParent($receiver));
    }

    private function nodeParent(?Type $builder): ?Type
    {
        if ($builder === null) {
            return null;
        }

        $builderParent = $this->extractParent($builder);
        if ($builderParent === null) {
            // NodeBuilder::append() sets the child's parent only when the builder has a parent.
            return Type::union($builder, $this->nullType ??= Type::null());
        }

        if (self::isNullOnly($builderParent)) {
            return $this->nullType ??= Type::null();
        }

        if (self::containsNull($builderParent)) {
            return Type::union($builder, $this->nullType ??= Type::null());
        }

        return $builder;
    }

    private static function isNullOnly(Type $type): bool
    {
        return count($type->atomicTypes) === 1
            && $type->atomicTypes[0] instanceof SimpleAtomicType
            && $type->atomicTypes[0]->kind === SimpleAtomicTypeKind::Null;
    }

    private static function containsNull(Type $type): bool
    {
        foreach ($type->atomicTypes as $atomic) {
            if ($atomic instanceof SimpleAtomicType && $atomic->kind === SimpleAtomicTypeKind::Null) {
                return true;
            }
        }

        return false;
    }

    private function resolveEnd(ReturnTypeProviderContext $context, ?Type $receiver): ?Type
    {
        $trackedParent = $this->extractParent($receiver);
        if ($trackedParent !== null) {
            return $trackedParent;
        }

        $declaring = $context->invocation->declaringClass;
        if ($declaring === null) {
            return null;
        }

        if ($declaring === self::NODE_BUILDER) {
            return $this->nullableArrayNodeType();
        }

        if (isset(self::BUILTIN_NODE_DEFINITIONS[$declaring])) {
            return $this->nullType ??= Type::null();
        }

        if ($declaring === self::EXPR_BUILDER || $declaring === self::MERGE_BUILDER) {
            return null;
        }

        $lowerDeclaring = strtolower($declaring);
        if ($lowerDeclaring === self::LOWER_NODE_BUILDER) {
            return $this->nullableArrayNodeType();
        }

        if (isset(self::LOWER_BUILTIN_NODE_DEFINITIONS[$lowerDeclaring])) {
            return $this->nullType ??= Type::null();
        }

        if ($lowerDeclaring === self::LOWER_EXPR_BUILDER || $lowerDeclaring === self::LOWER_MERGE_BUILDER) {
            return null;
        }

        $extendsNodeDefinition = false;
        foreach ($context->codebase->getClassAncestors($declaring) as $ancestor) {
            $lowerAncestor = strtolower($ancestor);
            if ($lowerAncestor === self::LOWER_NODE_BUILDER) {
                return $this->nullableArrayNodeType();
            }
            if ($lowerAncestor === self::LOWER_NODE_DEFINITION) {
                $extendsNodeDefinition = true;
            }
        }

        if ($extendsNodeDefinition) {
            return $this->nullType ??= Type::null();
        }

        return null;
    }

    private function nullableArrayNodeType(): Type
    {
        return $this->nullableArrayNodeType ??= Type::union(
            $this->plainTypes[self::ARRAY_NODE_DEFINITION] ??= Type::namedObject(self::ARRAY_NODE_DEFINITION),
            $this->nullType ??= Type::null(),
        );
    }

    private function withParent(string $class, ?Type $parent): Type
    {
        if ($parent === null) {
            return $this->plainTypes[$class] ??= Type::namedObject($class);
        }

        return Type::namedObject($class, $parent);
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
}

<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\ArrayItem;
use Mago\Sdk\Analyzer\Type\ArrayKey;
use Mago\Sdk\Analyzer\Type\ArrayKeyKind;
use Mago\Sdk\Analyzer\Type\KeyedArrayType;
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
    private const TREE_BUILDER = self::NS . 'TreeBuilder';
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
        'arraynode' => 'array',
        'booleannode' => 'boolean',
        'enumnode' => 'enum',
        'floatnode' => 'float',
        'integernode' => 'integer',
        'scalarnode' => 'scalar',
        'stringnode' => 'string',
        'variablenode' => 'variable',
    ];

    private const PROTOTYPE_SHORTCUTS = [
        'arrayprototype' => 'array',
        'booleanprototype' => 'boolean',
        'enumprototype' => 'enum',
        'floatprototype' => 'float',
        'integerprototype' => 'integer',
        'scalarprototype' => 'scalar',
        'stringprototype' => 'string',
        'variableprototype' => 'variable',
    ];

    private ?Type $nullType = null;
    private ?Type $nullableArrayNodeType = null;

    /** @var array<string, Type> */
    private array $plainTypes = [];

    /** @var array<string, bool> */
    private array $nodeBuilderClassCache = [];

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
            MethodTarget::exact(self::NODE_BUILDER, 'setNodeClass'),
            MethodTarget::exact(self::NODE_BUILDER, 'setParent'),
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
            'children' => $this->resolveChildren($context, $receiver),
            'validate', 'beforeNormalization' => $this->withParent(self::EXPR_BUILDER, $receiver),
            'prototype' => $this->resolvePrototype($context, $receiver),
            'node' => $this->resolveDynamicNode($context, $receiver),
            'setNodeClass' => $this->resolveSetNodeClass($context, $receiver),
            'setParent' => $this->resolveSetParent($context, $receiver),
            'arrayNode' => $this->resolveBuilderNode($context, 'array', self::ARRAY_NODE_DEFINITION, $receiver),
            'booleanNode' => $this->resolveBuilderNode($context, 'boolean', self::NODE_TYPE_MAP['boolean'], $receiver),
            'enumNode' => $this->resolveBuilderNode($context, 'enum', self::NODE_TYPE_MAP['enum'], $receiver),
            'floatNode' => $this->resolveBuilderNode($context, 'float', self::NODE_TYPE_MAP['float'], $receiver),
            'integerNode' => $this->resolveBuilderNode($context, 'integer', self::NODE_TYPE_MAP['integer'], $receiver),
            'scalarNode' => $this->resolveBuilderNode($context, 'scalar', self::NODE_TYPE_MAP['scalar'], $receiver),
            'stringNode' => $this->resolveBuilderNode($context, 'string', self::NODE_TYPE_MAP['string'], $receiver),
            'variableNode' => $this->resolveBuilderNode($context, 'variable', self::NODE_TYPE_MAP['variable'], $receiver),
            'arrayPrototype' => $this->resolvePrototypeShortcut($context, 'array', self::ARRAY_NODE_DEFINITION, $receiver),
            'booleanPrototype' => $this->resolvePrototypeShortcut($context, 'boolean', self::NODE_TYPE_MAP['boolean'], $receiver),
            'enumPrototype' => $this->resolvePrototypeShortcut($context, 'enum', self::NODE_TYPE_MAP['enum'], $receiver),
            'floatPrototype' => $this->resolvePrototypeShortcut($context, 'float', self::NODE_TYPE_MAP['float'], $receiver),
            'integerPrototype' => $this->resolvePrototypeShortcut($context, 'integer', self::NODE_TYPE_MAP['integer'], $receiver),
            'scalarPrototype' => $this->resolvePrototypeShortcut($context, 'scalar', self::NODE_TYPE_MAP['scalar'], $receiver),
            'stringPrototype' => $this->resolvePrototypeShortcut($context, 'string', self::NODE_TYPE_MAP['string'], $receiver),
            'variablePrototype' => $this->resolvePrototypeShortcut($context, 'variable', self::NODE_TYPE_MAP['variable'], $receiver),
            default => $this->dispatchNormalizedMethod($context, strtolower($name), $receiver),
        };
    }

    private function dispatchNormalizedMethod(ReturnTypeProviderContext $context, string $method, ?Type $receiver): ?Type
    {
        return match ($method) {
            'end' => $this->resolveEnd($context, $receiver),
            'children' => $this->resolveChildren($context, $receiver),
            'validate', 'beforenormalization' => $this->withParent(self::EXPR_BUILDER, $receiver),
            'prototype' => $this->resolvePrototype($context, $receiver),
            'node' => $this->resolveDynamicNode($context, $receiver),
            'setnodeclass' => $this->resolveSetNodeClass($context, $receiver),
            'setparent' => $this->resolveSetParent($context, $receiver),
            default => match (true) {
                isset(self::BUILDER_NODE_METHODS[$method]) => $this->resolveBuilderNode(
                    $context,
                    self::BUILDER_NODE_METHODS[$method],
                    self::NODE_TYPE_MAP[self::BUILDER_NODE_METHODS[$method]],
                    $receiver,
                ),
                isset(self::PROTOTYPE_SHORTCUTS[$method]) => $this->resolvePrototypeShortcut(
                    $context,
                    self::PROTOTYPE_SHORTCUTS[$method],
                    self::NODE_TYPE_MAP[self::PROTOTYPE_SHORTCUTS[$method]],
                    $receiver,
                ),
                default => null,
            },
        };
    }

    private function resolveChildren(ReturnTypeProviderContext $context, ?Type $receiver): Type
    {
        $parent = $this->extractParent($receiver);
        if ($parent !== null) {
            $parentObject = $this->firstNamedObject($parent);
            if ($parentObject !== null && $this->isNodeBuilderClass($context, $parentObject->name)) {
                $mappings = $parentObject->parameters[1] ?? null;
                if ($mappings !== null && $receiver !== null) {
                    return Type::namedObject($parentObject->name, $receiver, $mappings);
                }

                return $this->withParent($parentObject->name, $receiver);
            }
        }

        return $this->withParent(self::NODE_BUILDER, $receiver);
    }

    private function resolveSetNodeClass(ReturnTypeProviderContext $context, ?Type $receiver): ?Type
    {
        $builderObject = $this->firstNamedObject($receiver);
        if ($builderObject === null) {
            return null;
        }

        $typeArg = $context->invocation->getArgument(0, 'type')?->type?->getLiteralString();
        $classType = $context->invocation->getArgument(1, 'class')?->type;
        $classArg = $classType?->getLiteralClassString() ?? $classType?->getLiteralString();

        if ($typeArg === null || $classArg === null) {
            return $receiver;
        }

        $nodeClass = ltrim($classArg, '\\');
        if ($nodeClass === '' || !$context->codebase->classExists($nodeClass)) {
            return $receiver;
        }

        $mappings = $this->extractNodeMappings($receiver);
        $mappings[strtolower($typeArg)] = $nodeClass;

        $parentParam = $builderObject->parameters[0] ?? $this->nullableArrayNodeType();

        return Type::namedObject($builderObject->name, $parentParam, self::encodeNodeMappings($mappings));
    }

    private function resolveSetParent(ReturnTypeProviderContext $context, ?Type $receiver): ?Type
    {
        $builderObject = $this->firstNamedObject($receiver);
        if ($builderObject === null) {
            return null;
        }

        $parentArg = $context->invocation->getArgument(0, 'parent')?->type;
        if ($parentArg === null) {
            return $receiver;
        }

        $mappings = $builderObject->parameters[1] ?? null;
        if ($mappings !== null) {
            return Type::namedObject($builderObject->name, $parentArg, $mappings);
        }

        return Type::namedObject($builderObject->name, $parentArg);
    }

    private function resolvePrototype(ReturnTypeProviderContext $context, ?Type $receiver): ?Type
    {
        $typeArg = $context->invocation->getArgument(0, 'type')?->type?->getLiteralString();
        if ($typeArg === null) {
            return null;
        }

        $lowerType = strtolower($typeArg);
        $nodeClass = $this->extractNodeMapping($this->extractParent($receiver), $lowerType)
            ?? self::NODE_TYPE_MAP[$typeArg]
            ?? self::NODE_TYPE_MAP[$lowerType]
            ?? null;
        if ($nodeClass === null || !$context->codebase->classExists($nodeClass)) {
            return null;
        }

        return $this->withParent($nodeClass, $receiver);
    }

    private function resolvePrototypeShortcut(
        ReturnTypeProviderContext $context,
        string $nodeType,
        string $defaultNodeClass,
        ?Type $receiver,
    ): ?Type {
        $nodeClass = $this->extractNodeMapping($this->extractParent($receiver), $nodeType) ?? $defaultNodeClass;
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

        $lowerType = strtolower($typeArg);
        $nodeClass = $this->extractNodeMapping($receiver, $lowerType)
            ?? self::NODE_TYPE_MAP[$typeArg]
            ?? self::NODE_TYPE_MAP[$lowerType]
            ?? null;
        if ($nodeClass === null || !$context->codebase->classExists($nodeClass)) {
            return null;
        }

        return $this->withParent($nodeClass, $this->nodeParent($receiver));
    }

    private function resolveBuilderNode(
        ReturnTypeProviderContext $context,
        string $nodeType,
        string $defaultNodeClass,
        ?Type $receiver,
    ): ?Type {
        $nodeClass = $this->extractNodeMapping($receiver, $nodeType) ?? $defaultNodeClass;
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

    private function firstNamedObject(?Type $type): ?NamedObjectType
    {
        if ($type === null) {
            return null;
        }

        foreach ($type->atomicTypes as $atomic) {
            if ($atomic instanceof NamedObjectType) {
                return $atomic;
            }
        }

        return null;
    }

    private function isNodeBuilderClass(ReturnTypeProviderContext $context, string $className): bool
    {
        if ($className === self::NODE_BUILDER) {
            return true;
        }

        if ($className === self::TREE_BUILDER || isset(self::BUILTIN_NODE_DEFINITIONS[$className])) {
            return false;
        }

        $lower = strtolower($className);
        if (isset($this->nodeBuilderClassCache[$lower])) {
            return $this->nodeBuilderClassCache[$lower];
        }

        if ($lower === self::LOWER_NODE_BUILDER) {
            return $this->nodeBuilderClassCache[$lower] = true;
        }

        foreach ($context->codebase->getClassAncestors($className) as $ancestor) {
            if (strtolower($ancestor) === self::LOWER_NODE_BUILDER) {
                return $this->nodeBuilderClassCache[$lower] = true;
            }
        }

        return $this->nodeBuilderClassCache[$lower] = false;
    }

    private function extractNodeMapping(?Type $type, string $nodeType): ?string
    {
        if ($type === null) {
            return null;
        }

        foreach ($type->atomicTypes as $atomic) {
            if (!$atomic instanceof NamedObjectType || $atomic->parameters === null || !isset($atomic->parameters[1])) {
                continue;
            }

            foreach ($atomic->parameters[1]->atomicTypes as $mapAtomic) {
                if (!$mapAtomic instanceof KeyedArrayType || $mapAtomic->knownItems === null) {
                    continue;
                }

                foreach ($mapAtomic->knownItems as $item) {
                    if ($item->key->kind === ArrayKeyKind::String && $item->key->value === $nodeType) {
                        $className = $item->type->getLiteralString();
                        if ($className !== null) {
                            return $className;
                        }
                    }
                }
            }
        }

        return null;
    }

    /** @return array<string, string> */
    private function extractNodeMappings(?Type $type): array
    {
        if ($type === null) {
            return [];
        }

        $mappings = [];
        foreach ($type->atomicTypes as $atomic) {
            if (!$atomic instanceof NamedObjectType || $atomic->parameters === null || !isset($atomic->parameters[1])) {
                continue;
            }

            foreach ($atomic->parameters[1]->atomicTypes as $mapAtomic) {
                if (!$mapAtomic instanceof KeyedArrayType || $mapAtomic->knownItems === null) {
                    continue;
                }

                foreach ($mapAtomic->knownItems as $item) {
                    if ($item->key->kind === ArrayKeyKind::String && is_string($item->key->value)) {
                        $className = $item->type->getLiteralString();
                        if ($className !== null) {
                            $mappings[$item->key->value] = $className;
                        }
                    }
                }
            }
        }

        return $mappings;
    }

    /** @param array<string, string> $mappings */
    private static function encodeNodeMappings(array $mappings): Type
    {
        $items = [];
        foreach ($mappings as $key => $className) {
            $items[] = new ArrayItem(new ArrayKey(ArrayKeyKind::String, $key), false, Type::literalString($className));
        }

        return Type::fromAtomic(new KeyedArrayType($items, null, null, true));
    }
}

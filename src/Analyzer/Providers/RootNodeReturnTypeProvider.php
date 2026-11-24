<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;

/**
 * TreeBuilder::getRootNode() declares `NodeDefinition|ArrayNodeDefinition`.
 *
 * Mago resolves a method call on a union against one member only, and it picks
 * NodeDefinition, which has no children(). Symfony returns the concrete
 * `*NodeDefinition` matching the TreeBuilder's `$type` argument (defaulting to
 * `ArrayNodeDefinition`), with the TreeBuilder itself as parent.
 */
final class RootNodeReturnTypeProvider implements MethodReturnTypeProvider
{
    private const NS = 'Symfony\\Component\\Config\\Definition\\Builder\\';
    private const TREE_BUILDER = self::NS . 'TreeBuilder';
    private const LOWER_TREE_BUILDER = 'symfony\\component\\config\\definition\\builder\\treebuilder';
    private const ARRAY_NODE_DEFINITION = self::NS . 'ArrayNodeDefinition';

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

    private ?Type $defaultTreeBuilderType = null;

    public function __construct(
        private readonly ?TreeBuilderRootNodeIndex $index = null,
    ) {}

    public function getTargets(): array
    {
        return [
            MethodTarget::exact(self::TREE_BUILDER, 'getRootNode'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $receiver = $context->invocation->receiverType;
        $receiverAtomic = ($receiver !== null && count($receiver->atomicTypes) === 1)
            ? $receiver->atomicTypes[0]
            : null;
        $hasReceiverTemplate = $receiverAtomic instanceof NamedObjectType
            && $receiverAtomic->parameters !== null
            && isset($receiverAtomic->parameters[0])
            && strtolower($receiverAtomic->name) === self::LOWER_TREE_BUILDER;

        $rootType = null;
        $receiverTemplateLiteral = null;
        if ($hasReceiverTemplate) {
            $receiverTemplateLiteral = $receiverAtomic->parameters[0]->getLiteralString();
            if ($receiverTemplateLiteral !== null) {
                $lower = strtolower($receiverTemplateLiteral);
                if (isset(self::NODE_TYPE_MAP[$lower])) {
                    $rootType = $lower;
                }
            }
        }

        $rootType ??= $this->index?->rootTypeForSpan($context->invocation->span) ?? 'array';

        $nodeClass = self::NODE_TYPE_MAP[$rootType] ?? self::ARRAY_NODE_DEFINITION;
        if (!$context->codebase->classExists($nodeClass)) {
            return null;
        }

        if ($hasReceiverTemplate && $receiverTemplateLiteral === null) {
            $parent = Type::namedObject($receiverAtomic->name, Type::literalString($rootType));
        } else {
            $parent = $receiver
                ?? ($this->defaultTreeBuilderType ??= Type::namedObject(self::TREE_BUILDER));
        }

        return Type::namedObject($nodeClass, $parent);
    }
}

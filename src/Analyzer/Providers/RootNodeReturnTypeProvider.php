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
 * NodeDefinition, which has no children(). Symfony returns an
 * ArrayNodeDefinition whenever the tree type is "array" (the default), with
 * the TreeBuilder itself as parent.
 */
final class RootNodeReturnTypeProvider implements MethodReturnTypeProvider
{
    private const TREE_BUILDER = 'Symfony\\Component\\Config\\Definition\\Builder\\TreeBuilder';
    private const ARRAY_NODE_DEFINITION = 'Symfony\\Component\\Config\\Definition\\Builder\\ArrayNodeDefinition';

    public function getTargets(): array
    {
        return [
            MethodTarget::exact(self::TREE_BUILDER, 'getRootNode'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        if (!$context->codebase->classExists(self::ARRAY_NODE_DEFINITION)) {
            return null;
        }

        $parent = $context->invocation->receiverType ?? Type::namedObject(self::TREE_BUILDER);

        return Type::fromAtomic(new NamedObjectType(
            name: self::ARRAY_NODE_DEFINITION,
            parameters: [$parent],
            variances: null,
            static: false,
            isThis: false,
            intersections: null,
            remappedParameters: false,
        ));
    }
}

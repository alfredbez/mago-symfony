<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer;

use AlfredBez\MagoSymfony\Analyzer\Providers\RootNodeReturnTypeProvider;
use AlfredBez\MagoSymfony\Analyzer\Providers\TreeBuilderRootNodeIndex;
use Mago\Sdk\Analyzer\Plugin;
use Mago\Sdk\Analyzer\PluginDefinition;
use Mago\Sdk\Analyzer\PluginRegistry;

/**
 * Types `TreeBuilder::getRootNode()` using call-span root-type indexing without disabling
 * provider memoization on the main Symfony plugin.
 */
final class TreeBuilderRootTypePlugin implements Plugin
{
    public function __construct(
        private readonly TreeBuilderRootNodeIndex $index,
    ) {}

    public function getDefinition(): PluginDefinition
    {
        return new PluginDefinition(
            identifier: 'alfredbez/symfony-tree-builder-root',
            name: 'Symfony TreeBuilder Root',
            description: 'Resolves concrete TreeBuilder::getRootNode() node types from constructor literals.',
            defaultEnabled: true,
        );
    }

    public function register(PluginRegistry $registry): void
    {
        $registry->registerBeforeAnalysisHook($this->index);
        $registry->registerMethodReturnTypeProvider(new RootNodeReturnTypeProvider($this->index));
    }
}

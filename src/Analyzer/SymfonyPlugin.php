<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer;

use AlfredBez\MagoSymfony\Analyzer\Providers\ConfigEndReturnTypeProvider;
use AlfredBez\MagoSymfony\Analyzer\Providers\ContainerGetReturnTypeProvider;
use AlfredBez\MagoSymfony\Analyzer\Providers\RootNodeReturnTypeProvider;
use AlfredBez\MagoSymfony\Analyzer\Providers\ServiceClassMap;
use AlfredBez\MagoSymfony\Analyzer\Providers\TreeBuilderRootNodeHook;
use Mago\Sdk\Analyzer\Plugin;
use Mago\Sdk\Analyzer\PluginDefinition;
use Mago\Sdk\Analyzer\PluginRegistry;

final class SymfonyPlugin implements Plugin
{
    public function getDefinition(): PluginDefinition
    {
        return new PluginDefinition(
            identifier: 'alfredbez/symfony',
            name: 'Symfony',
            description: 'Types container services and Config TreeBuilder fluent chains.',
            defaultEnabled: true,
        );
    }

    public function register(PluginRegistry $registry): void
    {
        $registry->registerMethodReturnTypeProvider(new ContainerGetReturnTypeProvider(ServiceClassMap::fromEnvironment()));
        $registry->registerMethodReturnTypeProvider(new RootNodeReturnTypeProvider());
        $registry->registerMethodReturnTypeProvider(new ConfigEndReturnTypeProvider());
        $registry->registerMethodCallAnalysisHook(new TreeBuilderRootNodeHook());
    }
}

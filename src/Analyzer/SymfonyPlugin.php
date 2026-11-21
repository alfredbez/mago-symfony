<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer;

use AlfredBez\MagoSymfony\Analyzer\Providers\ConfigEndReturnTypeProvider;
use AlfredBez\MagoSymfony\Analyzer\Providers\ConsoleCommandArgumentsHook;
use AlfredBez\MagoSymfony\Analyzer\Providers\ConsoleHelperReturnTypeProvider;
use AlfredBez\MagoSymfony\Analyzer\Providers\ContainerGetAccessHook;
use AlfredBez\MagoSymfony\Analyzer\Providers\ContainerGetReturnTypeProvider;
use AlfredBez\MagoSymfony\Analyzer\Providers\ControllerAttributesReturnTypeProvider;
use AlfredBez\MagoSymfony\Analyzer\Providers\FormGetErrorsReturnTypeProvider;
use AlfredBez\MagoSymfony\Analyzer\Providers\HeaderBagGetReturnTypeProvider;
use AlfredBez\MagoSymfony\Analyzer\Providers\ParameterBagEnumReturnTypeProvider;
use AlfredBez\MagoSymfony\Analyzer\Providers\ParameterReturnTypeProvider;
use AlfredBez\MagoSymfony\Analyzer\Providers\RequestFormatReturnTypeProvider;
use AlfredBez\MagoSymfony\Analyzer\Providers\RequestPreferredLanguageReturnTypeProvider;
use AlfredBez\MagoSymfony\Analyzer\Providers\RequiredInitializerProvider;
use AlfredBez\MagoSymfony\Analyzer\Providers\ResponseHeaderBagCookiesReturnTypeProvider;
use AlfredBez\MagoSymfony\Analyzer\Providers\RootNodeReturnTypeProvider;
use AlfredBez\MagoSymfony\Analyzer\Providers\SecurityUserReturnTypeProvider;
use AlfredBez\MagoSymfony\Analyzer\Providers\SerializerReturnTypeProvider;
use AlfredBez\MagoSymfony\Analyzer\Providers\ServiceClassMap;
use AlfredBez\MagoSymfony\Analyzer\Providers\TreeBuilderReturnIssueFilter;
use AlfredBez\MagoSymfony\Analyzer\Providers\TreeBuilderRootNodeHook;
use AlfredBez\MagoSymfony\Analyzer\Providers\WrappedExceptionsReturnTypeProvider;
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
            description: 'Types container services, Config TreeBuilder fluent chains, HTTP/Form/Serializer contracts, and Console commands.',
            defaultEnabled: true,
        );
    }

    public function register(PluginRegistry $registry): void
    {
        $container = ServiceClassMap::fromEnvironment();
        $registry->enableProviderMemoization();
        $registry->registerMethodReturnTypeProvider(new ContainerGetReturnTypeProvider($container));
        if ($container->hasParameters()) {
            $registry->registerMethodReturnTypeProvider(new ParameterReturnTypeProvider($container));
        }
        $registry->registerMethodReturnTypeProvider(new RootNodeReturnTypeProvider());
        $registry->registerMethodReturnTypeProvider(new ConfigEndReturnTypeProvider());
        $registry->registerMethodReturnTypeProvider(new HeaderBagGetReturnTypeProvider());
        $registry->registerMethodReturnTypeProvider(new ParameterBagEnumReturnTypeProvider());
        $registry->registerMethodReturnTypeProvider(new RequestFormatReturnTypeProvider());
        $registry->registerMethodReturnTypeProvider(new RequestPreferredLanguageReturnTypeProvider());
        $registry->registerMethodReturnTypeProvider(new ResponseHeaderBagCookiesReturnTypeProvider());
        $registry->registerMethodReturnTypeProvider(new ConsoleHelperReturnTypeProvider());
        $registry->registerMethodReturnTypeProvider(new ControllerAttributesReturnTypeProvider());
        $registry->registerMethodReturnTypeProvider(new FormGetErrorsReturnTypeProvider());
        $registry->registerMethodReturnTypeProvider(new SerializerReturnTypeProvider());
        $registry->registerMethodReturnTypeProvider(new WrappedExceptionsReturnTypeProvider());
        $userClass = SecurityUserReturnTypeProvider::configuredUserClass();
        if ($userClass !== null) {
            $registry->registerMethodReturnTypeProvider(new SecurityUserReturnTypeProvider($userClass));
        }
        $registry->registerClassInitializerProvider(new RequiredInitializerProvider());
        $registry->registerMethodCallAnalysisHook(new TreeBuilderRootNodeHook());
        if ($container->isXmlLoaded()) {
            $registry->registerMethodCallAnalysisHook(
                new ContainerGetAccessHook(
                    $container,
                    ServiceClassMap::strictUnknownServicesFromEnvironment(),
                ),
            );
        }
        $registry->registerClassLikeAnalysisHook(new ConsoleCommandArgumentsHook());
        $registry->registerIssueFilterHook(new TreeBuilderReturnIssueFilter());
    }
}

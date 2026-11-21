<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/fixtures/Support.php';

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\ServiceLocator;
use SymfonyFixture\ConcreteGreeter;
use SymfonyFixture\Greeter;
use SymfonyFixture\Mailer;

function check(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException($label);
    }
}

$container = new Container();
$greeter = new ConcreteGreeter();
$container->set(Greeter::class, $greeter);
$container->set('42', new Mailer());
check($container->get(Greeter::class) === $greeter, 'Class-name service ids can resolve to a concrete implementation');
check($container->get('42') instanceof Mailer, 'Numeric string service ids are valid');
$locator = new ServiceLocator(['shop.greeter' => static fn () => $greeter]);
check($locator->get('shop.greeter') === $greeter, 'ServiceLocator get preserves the service');

foreach ([0, 1, 2, 3, 4] as $behavior) {
    try {
        $service = $container->get('missing', $behavior);
        check($behavior !== ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE && $service === null, 'Only flag 1 throws for a missing service in Container');
    } catch (ServiceNotFoundException) {
        check($behavior === ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, 'Unexpected throwing behavior');
    }
}
try {
    (new ContainerBuilder())->get('missing', ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
    throw new RuntimeException('ContainerBuilder must throw for flag 0');
} catch (ServiceNotFoundException) {
}

$builder = new \Symfony\Component\Config\Definition\Builder\NodeBuilder();
check($builder->end() === null, 'An unparented NodeBuilder ends at null');
check($builder->scalarNode('s')->end() === null, 'A child of an unparented builder has no parent');
require __DIR__ . '/fixtures/ConfigNodes.php';
$nodes = new \SymfonyFixture\ConfigNodes();
check($nodes->nodes() instanceof \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition, 'Every node end returns to its actual parent');
check($nodes->prototypes() instanceof \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition, 'Every prototype end returns to its array');
check(
    (new \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition('upper'))
        ->prototype('INTEGER')->end() instanceof \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition,
    'Uppercase prototype names remain valid at runtime',
);

require __DIR__ . '/fixtures/CustomNodeBuilderConfig.php';
$customConfig = new \SymfonyFixture\CustomNodeBuilderConfig();
check($customConfig->setNodeClassAndNestedInheritance() instanceof \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition, 'Custom setNodeClass mappings and nested inheritance return root ArrayNodeDefinition');
check($customConfig->customBuilderSubclassPreserved() === 'ok', 'Custom NodeBuilder subclass survives end() and nested children()');
check(
    (new \Symfony\Component\Config\Definition\Builder\NodeBuilder())
        ->setNodeClass('custom', \SymfonyFixture\CustomNodeDefinition::class)
        ->node('a', 'custom')
        ->end() === null,
    'Unparented NodeBuilder with setNodeClass still produces a child whose end() is null',
);

require __DIR__ . '/fixtures/TreeBuilderScopes.php';
require __DIR__ . '/fixtures/TreeBuilderScopesNegative.php';
$scopes = new \SymfonyFixture\TreeBuilderScopes();
$scopes->nestedTreeAssignment();
$scopes->shadowedRoot();
$invalidScopes = new \SymfonyFixture\TreeBuilderScopesNegative();
foreach (['nestedTreeAssignment', 'nestedRootAssignment'] as $method) {
    try {
        $invalidScopes->$method();
        throw new RuntimeException('Scalar roots have no children method');
    } catch (Error $error) {
        check(str_contains($error->getMessage(), 'children'), 'Expected the missing children method');
    }
}

$xmlBuilder = new ContainerBuilder();
$xmlBuilder->setParameter('app.name', 'shop-app');
$xmlBuilder->setParameter('app.debug', true);
$xmlBuilder->setParameter('app.retries', 3);
$xmlBuilder->setParameter('app.ratio', 1.5);
$xmlBuilder->setParameter('app.list', ['alpha', 'beta']);
$xmlBuilder->setParameter('app.map', ['host' => 'localhost', 'port' => 3306]);
$xmlBuilder->register('xml.public.greeter', ConcreteGreeter::class)->setPublic(true);
$xmlBuilder->register('xml.private.greeter', ConcreteGreeter::class)->setPublic(false);
$xmlBuilder->setAlias('xml.alias.step', 'xml.private.greeter');
$xmlBuilder->setAlias('xml.alias.chain', 'xml.alias.step');
$xmlBuilder->register('xml.factory.class', Greeter::class)
    ->setFactory([\SymfonyFixture\GreeterFactory::class, 'create'])
    ->setPublic(true);
$dumper = new \Symfony\Component\DependencyInjection\Dumper\XmlDumper($xmlBuilder);
$dumpedXml = $dumper->dump();
$loaded = \AlfredBez\MagoSymfony\Analyzer\Providers\ServiceClassMap::fromXmlString($dumpedXml);
check($loaded->classFor('xml.public.greeter') === ConcreteGreeter::class, 'XmlDumper public service class resolved');
check($loaded->classFor('xml.private.greeter') === ConcreteGreeter::class, 'XmlDumper private service class resolved');
check($loaded->classFor('xml.alias.chain') === ConcreteGreeter::class, 'XmlDumper chained alias resolved');
check($loaded->factoryFor('xml.factory.class') === ['kind' => 'method', 'class' => \SymfonyFixture\GreeterFactory::class, 'method' => 'create'], 'XmlDumper factory resolved');
check($loaded->parameterFor('app.name') === ['kind' => 'string', 'value' => 'shop-app'], 'XmlDumper string parameter resolved');
check($loaded->parameterFor('app.retries') === ['kind' => 'int', 'value' => 3], 'XmlDumper int parameter resolved');
check($loaded->parameterFor('app.debug') === ['kind' => 'bool', 'value' => true], 'XmlDumper bool parameter resolved');

fwrite(STDOUT, "Symfony runtime contracts PASS\n\n");

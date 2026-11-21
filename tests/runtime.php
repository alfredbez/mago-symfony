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

fwrite(STDOUT, "Symfony runtime contracts PASS\n\n");

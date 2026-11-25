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
check($loaded->isXmlLoaded() === true, 'XmlDumper map reports XML loaded');
check($loaded->isKnownService('xml.public.greeter') === true && $loaded->isPrivateService('xml.public.greeter') === false, 'XmlDumper public service visibility is public');
check($loaded->isKnownService('xml.private.greeter') === true && $loaded->isPrivateService('xml.private.greeter') === true, 'XmlDumper private service visibility is private');
check($loaded->isKnownService('xml.alias.chain') === true && $loaded->isPrivateService('xml.alias.chain') === true, 'XmlDumper private alias visibility is private');
check($loaded->isKnownService('xml.missing.service') === false && $loaded->isPrivateService('xml.missing.service') === false, 'XmlDumper unknown service is not known or private');
check($loaded->classFor('xml.alias.chain') === ConcreteGreeter::class, 'XmlDumper chained alias resolved');
check($loaded->factoryFor('xml.factory.class') === ['kind' => 'method', 'class' => \SymfonyFixture\GreeterFactory::class, 'method' => 'create'], 'XmlDumper factory resolved');
check($loaded->parameterFor('app.name') === ['kind' => 'string', 'value' => 'shop-app'], 'XmlDumper string parameter resolved');
check($loaded->parameterFor('app.retries') === ['kind' => 'int', 'value' => 3], 'XmlDumper int parameter resolved');
check($loaded->parameterFor('app.debug') === ['kind' => 'bool', 'value' => true], 'XmlDumper bool parameter resolved');

$prefixedOption = new \Symfony\Component\Console\Input\InputOption('--bypass-locking', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE);
check($prefixedOption->getName() === 'bypass-locking', 'InputOption strips leading dashes from option names at runtime');

$headers = new \Symfony\Component\HttpFoundation\HeaderBag(['X-Present' => 'val']);
check($headers->get('X-Present', 'fallback') === 'val', 'HeaderBag::get returns present header value');
check($headers->get('X-Missing', 'fallback') === 'fallback', 'HeaderBag::get returns string default when header is missing');
check($headers->get('X-Missing') === null, 'HeaderBag::get returns null when header is missing and default is null');

$formFactory = \Symfony\Component\Form\Forms::createFormFactory();
$rootForm = $formFactory->createBuilder()->add('child')->getForm();
$rootForm->get('child')->addError(new \Symfony\Component\Form\FormError('child error'));
foreach ($rootForm->getErrors(true, true) as $flatErr) {
    check($flatErr instanceof \Symfony\Component\Form\FormError, 'getErrors(true, true) yields FormError instances');
}
foreach ($rootForm->getErrors(true, false) as $nestedEntry) {
    check($nestedEntry instanceof \Symfony\Component\Form\FormErrorIterator, 'getErrors(true, false) yields nested FormErrorIterator for child errors');
}

$refMethod = new ReflectionMethod(\Symfony\Bundle\FrameworkBundle\Controller\AbstractController::class, 'setContainer');
$requiredAttrs = $refMethod->getAttributes(\Symfony\Contracts\Service\Attribute\Required::class);
check($requiredAttrs !== [], 'AbstractController::setContainer carries #[Required] attribute');

require_once __DIR__ . '/fixtures-xml/SymfonyComponents.php';
$serializer = new \Symfony\Component\Serializer\Serializer(
    [
        new \Symfony\Component\Serializer\Normalizer\ArrayDenormalizer(),
        new \Symfony\Component\Serializer\Normalizer\ObjectNormalizer(),
    ],
    [new \Symfony\Component\Serializer\Encoder\JsonEncoder()],
);
$deserializedEvent = $serializer->deserialize('{"name":"wave5"}', \SymfonyFixtureXml\DomainEvent::class, 'json');
check($deserializedEvent instanceof \SymfonyFixtureXml\DomainEvent && $deserializedEvent->name === 'wave5', 'Serializer::deserialize hydrates DomainEvent');

$deserializedList = $serializer->deserialize('[{"name":"a"},{"name":"b"}]', \SymfonyFixtureXml\DomainEvent::class . '[]', 'json');
check(
    is_array($deserializedList)
    && count($deserializedList) === 2
    && $deserializedList[0] instanceof \SymfonyFixtureXml\DomainEvent
    && $deserializedList[1] instanceof \SymfonyFixtureXml\DomainEvent
    && $deserializedList[1]->name === 'b',
    'Serializer::deserialize hydrates DomainEvent[] list',
);

$deserializedMap = $serializer->deserialize('{"primary":{"name":"mapped"}}', \SymfonyFixtureXml\DomainEvent::class . '[]', 'json');
check(
    is_array($deserializedMap)
    && isset($deserializedMap['primary'])
    && $deserializedMap['primary'] instanceof \SymfonyFixtureXml\DomainEvent
    && $deserializedMap['primary']->name === 'mapped',
    'Serializer::deserialize preserves string keys for DomainEvent[]',
);

$denormalizedMatrix = $serializer->denormalize(
    ['primary' => ['first' => ['name' => 'nested']]],
    \SymfonyFixtureXml\DomainEvent::class . '[][]',
);
check(
    is_array($denormalizedMatrix)
    && isset($denormalizedMatrix['primary']['first'])
    && $denormalizedMatrix['primary']['first'] instanceof \SymfonyFixtureXml\DomainEvent
    && $denormalizedMatrix['primary']['first']->name === 'nested',
    'Denormalizer::denormalize hydrates nested DomainEvent[][] preserving associative keys',
);

require_once __DIR__ . '/fixtures-security/SecurityUserUsage.php';
$appUser = new \SymfonyFixtureSecurity\AppUser('Ada');
$token = new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($appUser, 'main', ['ROLE_USER']);
check($token->getUser() === $appUser, 'UsernamePasswordToken::getUser returns the configured UserInterface implementation');
$tokenStorage = new \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage();
$tokenStorage->setToken($token);
$securityContainer = new Container();
$securityContainer->set('security.token_storage', $tokenStorage);
$securityHelper = new \Symfony\Bundle\SecurityBundle\Security($securityContainer);
check($securityHelper->getUser() === $appUser, 'SecurityBundle Security::getUser returns the configured user from token storage');
$tokenStorage->setToken(null);
check($securityHelper->getUser() === null, 'SecurityBundle Security::getUser returns null when unauthenticated');

$req = new \Symfony\Component\HttpFoundation\Request();
check($req->getRequestFormat() === 'html', 'Request::getRequestFormat default returns string html');
check($req->getRequestFormat('json') === 'json', 'Request::getRequestFormat returns non-null string default');
check($req->getRequestFormat(null) === null, 'Request::getRequestFormat(null) returns null when unset');
check($req->getPreferredFormat() === 'html', 'Request::getPreferredFormat default returns string html');
check($req->getPreferredFormat(null) === null, 'Request::getPreferredFormat(null) returns null when unset');

$respHeaders = new \Symfony\Component\HttpFoundation\ResponseHeaderBag();
$respHeaders->setCookie(\Symfony\Component\HttpFoundation\Cookie::create('sid', 'abc'));
$respHeaders->setCookie(\Symfony\Component\HttpFoundation\Cookie::create('123', 'numeric-name'));
$flatCookies = $respHeaders->getCookies(\Symfony\Component\HttpFoundation\ResponseHeaderBag::COOKIES_FLAT);
check(array_is_list($flatCookies) && count($flatCookies) === 2 && $flatCookies[0] instanceof \Symfony\Component\HttpFoundation\Cookie, 'ResponseHeaderBag::getCookies(COOKIES_FLAT) returns list<Cookie>');
$nestedCookies = $respHeaders->getCookies(\Symfony\Component\HttpFoundation\ResponseHeaderBag::COOKIES_ARRAY);
check(isset($nestedCookies['']['/']['sid']) && $nestedCookies['']['/']['sid'] instanceof \Symfony\Component\HttpFoundation\Cookie, 'ResponseHeaderBag::getCookies(COOKIES_ARRAY) returns domain/path/name nested Cookie map');
check(array_key_exists(123, $nestedCookies['']['/']) && $nestedCookies['']['/'][123] instanceof \Symfony\Component\HttpFoundation\Cookie, 'ResponseHeaderBag::getCookies(COOKIES_ARRAY) converts numeric string cookie names to int keys');

$consoleApp = new \Symfony\Component\Console\Application();
$defaultHelpers = $consoleApp->getHelperSet();
check($defaultHelpers->get('question') instanceof \Symfony\Component\Console\Helper\QuestionHelper, 'HelperSet::get(question) returns QuestionHelper');
check($defaultHelpers->get('formatter') instanceof \Symfony\Component\Console\Helper\FormatterHelper, 'HelperSet::get(formatter) returns FormatterHelper');
check($defaultHelpers->get('debug_formatter') instanceof \Symfony\Component\Console\Helper\DebugFormatterHelper, 'HelperSet::get(debug_formatter) returns DebugFormatterHelper');
check($defaultHelpers->get('process') instanceof \Symfony\Component\Console\Helper\ProcessHelper, 'HelperSet::get(process) returns ProcessHelper');

foreach ([
    static fn () => new \Symfony\Component\Console\Input\InputArgument('a', \Symfony\Component\Console\Input\InputArgument::REQUIRED, '', 'oops'),
    static fn () => new \Symfony\Component\Console\Input\InputArgument('a', \Symfony\Component\Console\Input\InputArgument::OPTIONAL | \Symfony\Component\Console\Input\InputArgument::IS_ARRAY, '', 'bad'),
    static fn () => new \Symfony\Component\Console\Input\InputOption('o', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE, '', false),
    static fn () => new \Symfony\Component\Console\Input\InputOption('o', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE | \Symfony\Component\Console\Input\InputOption::VALUE_IS_ARRAY),
    static fn () => new \Symfony\Component\Console\Input\InputOption('o', null, \Symfony\Component\Console\Input\InputOption::VALUE_NEGATABLE | \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED),
    static fn () => new \Symfony\Component\Console\Input\InputOption('o', null, \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED | \Symfony\Component\Console\Input\InputOption::VALUE_IS_ARRAY, '', 'bad'),
] as $idx => $invalidDefFactory) {
    try {
        $invalidDefFactory();
        throw new RuntimeException("Invalid console definition #{$idx} must throw at runtime");
    } catch (\Symfony\Component\Console\Exception\ExceptionInterface) {
    }
}

require_once __DIR__ . '/fixtures-xml/Wave6ValidatorInitializer.php';
$validatorInstance = new \SymfonyFixtureXml\Wave6ConstraintValidator();
$validatorContext = new \Symfony\Component\Validator\Context\ExecutionContext(
    \Symfony\Component\Validator\Validation::createValidator(),
    null,
    new class implements \Symfony\Contracts\Translation\TranslatorInterface {
        public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
        {
            return $id;
        }

        public function getLocale(): string
        {
            return 'en';
        }
    },
);
$validatorInstance->initialize($validatorContext);
$validatorInstance->validate('', new \SymfonyFixtureXml\Wave6NonBlankConstraint());
check(count($validatorContext->getViolations()) === 1, 'ConstraintValidator::initialize wires ExecutionContextInterface before validate()');

$inputHelper = new \SymfonyFixtureXml\Wave6InputAwareHelper();
$arrayInput = new \Symfony\Component\Console\Input\ArrayInput([]);
$arrayInput->setInteractive(false);
$inputHelper->setInput($arrayInput);
check($inputHelper->rawInput() === $arrayInput, 'InputAwareHelper::setInput wires InputInterface before use');

require_once __DIR__ . '/fixtures-xml/Wave6Contracts.php';
$langReq = new \Symfony\Component\HttpFoundation\Request();
check($langReq->getPreferredLanguage(['en', 'de']) === 'en', 'Request::getPreferredLanguage returns first locale string for non-empty list');
check($langReq->getPreferredLanguage([]) === null, 'Request::getPreferredLanguage returns null for empty list without Accept-Language');
check(@$langReq->getPreferredLanguage(['a' => 'en', 'b' => 'de']) === null, 'Request::getPreferredLanguage returns null for associative non-0-indexed array');

$enumBag = new \Symfony\Component\HttpFoundation\ParameterBag();
check($enumBag->getEnum('status', \SymfonyFixtureXml\Wave6Status::class, \SymfonyFixtureXml\Wave6Status::Draft) === \SymfonyFixtureXml\Wave6Status::Draft, 'ParameterBag::getEnum returns non-null default when missing');
$enumBag->set('status', 'published');
check($enumBag->getEnum('status', \SymfonyFixtureXml\Wave6Status::class, \SymfonyFixtureXml\Wave6Status::Draft) === \SymfonyFixtureXml\Wave6Status::Published, 'ParameterBag::getEnum hydrates backed enum when present');
$enumInput = new \Symfony\Component\HttpFoundation\InputBag();
check($enumInput->getEnum('status', \SymfonyFixtureXml\Wave6Status::class, \SymfonyFixtureXml\Wave6Status::Published) === \SymfonyFixtureXml\Wave6Status::Published, 'InputBag::getEnum returns non-null default when missing');

$attrController = new class {
    #[\SymfonyFixtureXml\Wave6MarkerAttribute('alpha')]
    public function handle(): void {}
};
$dummyKernel = new class implements \Symfony\Component\HttpKernel\HttpKernelInterface {
    public function handle(\Symfony\Component\HttpFoundation\Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): \Symfony\Component\HttpFoundation\Response
    {
        return new \Symfony\Component\HttpFoundation\Response();
    }
};
$ctrlEvent = new \Symfony\Component\HttpKernel\Event\ControllerEvent(
    $dummyKernel,
    [$attrController, 'handle'],
    new \Symfony\Component\HttpFoundation\Request(),
    \Symfony\Component\HttpKernel\HttpKernelInterface::MAIN_REQUEST,
);
$attrs = $ctrlEvent->getAttributes(\SymfonyFixtureXml\Wave6MarkerAttribute::class);
check(array_is_list($attrs) && count($attrs) === 1 && $attrs[0] instanceof \SymfonyFixtureXml\Wave6MarkerAttribute && $attrs[0]->describe() === 'alpha', 'ControllerEvent::getAttributes returns list<T> for concrete attribute class');
$ctrlArgsEvent = new \Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent(
    $dummyKernel,
    $ctrlEvent,
    [],
    new \Symfony\Component\HttpFoundation\Request(),
    \Symfony\Component\HttpKernel\HttpKernelInterface::MAIN_REQUEST,
);
$argAttrs = $ctrlArgsEvent->getAttributes(\SymfonyFixtureXml\Wave6MarkerAttribute::class);
check(array_is_list($argAttrs) && count($argAttrs) === 1 && $argAttrs[0] instanceof \SymfonyFixtureXml\Wave6MarkerAttribute, 'ControllerArgumentsEvent::getAttributes returns list<T> for concrete attribute class');

$domainEx = new \SymfonyFixtureXml\Wave6DomainException('boom');
$otherEx = new \LogicException('other');
$failedEx = new \Symfony\Component\Messenger\Exception\HandlerFailedException(
    new \Symfony\Component\Messenger\Envelope(new \stdClass()),
    ['handler.a' => $domainEx, 'handler.b' => $otherEx],
);
$filteredEx = $failedEx->getWrappedExceptions(\SymfonyFixtureXml\Wave6DomainException::class);
check(count($filteredEx) === 1 && isset($filteredEx['handler.a']) && $filteredEx['handler.a'] === $domainEx, 'HandlerFailedException::getWrappedExceptions preserves string handler keys and filters by class');

check(
    (new \Symfony\Component\Config\Definition\Builder\TreeBuilder('scalar_root', 'scalar'))->getRootNode() instanceof \Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition,
    'TreeBuilder with scalar root returns ScalarNodeDefinition',
);
check(
    (new \Symfony\Component\Config\Definition\Builder\TreeBuilder('enum_root', 'enum'))->getRootNode() instanceof \Symfony\Component\Config\Definition\Builder\EnumNodeDefinition,
    'TreeBuilder with enum root returns EnumNodeDefinition',
);
check(
    (new \Symfony\Component\Config\Definition\Builder\TreeBuilder('int_root', 'integer'))->getRootNode() instanceof \Symfony\Component\Config\Definition\Builder\IntegerNodeDefinition,
    'TreeBuilder with integer root returns IntegerNodeDefinition',
);

$optionOnlyDef = new \Symfony\Component\Console\Input\InputDefinition([
    new \Symfony\Component\Console\Input\InputOption('dry-run', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE),
]);
$optionOnlyInput = new \Symfony\Component\Console\Input\ArrayInput([], $optionOnlyDef);
try {
    $optionOnlyInput->getArgument('undeclared_arg');
    throw new RuntimeException('Option-only InputDefinition must reject undeclared argument');
} catch (\Symfony\Component\Console\Exception\InvalidArgumentException) {
}

$argOnlyDef = new \Symfony\Component\Console\Input\InputDefinition([
    new \Symfony\Component\Console\Input\InputArgument('target', \Symfony\Component\Console\Input\InputArgument::REQUIRED),
]);
$argOnlyInput = new \Symfony\Component\Console\Input\ArrayInput(['target' => 'x'], $argOnlyDef);
try {
    $argOnlyInput->getOption('undeclared-opt');
    throw new RuntimeException('Argument-only InputDefinition must reject undeclared option');
} catch (\Symfony\Component\Console\Exception\InvalidArgumentException) {
}

require_once __DIR__ . '/fixtures-xml/ConsoleCommandNegative.php';

$bufferedOut = new \Symfony\Component\Console\Output\BufferedOutput();
$transitiveCmd = new \SymfonyFixtureXml\TransitiveConstructorConfiguredCommand();
check(
    $transitiveCmd->run(new \Symfony\Component\Console\Input\ArrayInput(['ctor_arg' => 'ok']), $bufferedOut) === \Symfony\Component\Console\Command\Command::SUCCESS,
    'Transitive constructor helper argument succeeds at runtime',
);

$childCustomParentCmd = new \SymfonyFixtureXml\ChildOfCustomParentConstructorCommand();
check(
    $childCustomParentCmd->run(new \Symfony\Component\Console\Input\ArrayInput(['parent_arg' => 'p', 'child_arg' => 'c']), $bufferedOut) === \Symfony\Component\Console\Command\Command::SUCCESS,
    'Custom parent constructor argument and child configure argument succeed at runtime',
);

check(
    (new \SymfonyFixtureXml\TraitSafeMethodOverrideCommand())->run(new \Symfony\Component\Console\Input\ArrayInput(['known' => 'k', 'trait_arg' => 't']), $bufferedOut) === \Symfony\Component\Console\Command\Command::SUCCESS,
    'Trait-overridden setDescription adding constructor argument succeeds at runtime',
);
check(
    (new \SymfonyFixtureXml\AnonymousClassCtorArgCommand())->run(new \Symfony\Component\Console\Input\ArrayInput(['known' => 'k', 'anon_ctor_arg' => 'a']), $bufferedOut) === \Symfony\Component\Console\Command\Command::SUCCESS,
    'Anonymous-class constructor argument adding command argument succeeds at runtime',
);
check(
    (new \SymfonyFixtureXml\ObjectStaticHelperCommand())->run(new \Symfony\Component\Console\Input\ArrayInput(['known' => 'k', 'obj_static_arg' => 'o']), $bufferedOut) === \Symfony\Component\Console\Command\Command::SUCCESS,
    'Object-syntax static helper adding constructor argument succeeds at runtime',
);
check(
    (new \SymfonyFixtureXml\FirstClassCallableHelperCommand())->run(new \Symfony\Component\Console\Input\ArrayInput(['known' => 'k', 'fcc_arg' => 'f']), $bufferedOut) === \Symfony\Component\Console\Command\Command::SUCCESS,
    'First-class callable helper adding constructor argument succeeds at runtime',
);

foreach ([
    'unrelated helper' => new \SymfonyFixtureXml\UnrelatedConstructorHelperCommand(),
    'final protected helper' => new \SymfonyFixtureXml\FinalProtectedHelperCommand(),
    'parent-only constructor' => new \SymfonyFixtureXml\ParentOnlyConstructorInvalidCommand(),
    'cyclic helper' => new \SymfonyFixtureXml\CyclicConstructorHelperCommand(),
    'nested anonymous class' => new \SymfonyFixtureXml\NestedAnonymousClassConstructorCommand(),
] as $label => $invalidCtorCmd) {
    try {
        $invalidCtorCmd->run(new \Symfony\Component\Console\Input\ArrayInput(['known' => 'ok']), $bufferedOut);
        throw new RuntimeException("Command with {$label} must reject undeclared argument");
    } catch (\Symfony\Component\Console\Exception\InvalidArgumentException) {
    }
}

$dashedApp = new \Symfony\Component\Console\Application();
$dashedApp->setAutoExit(false);
$dashedCmd = new class extends \Symfony\Component\Console\Command\Command {
    public function __construct()
    {
        parent::__construct('runtime:dashed');
    }

    protected function configure(): void
    {
        $this
            ->addArgument('target', \Symfony\Component\Console\Input\InputArgument::REQUIRED)
            ->addOption('--known', 'k', \Symfony\Component\Console\Input\InputOption::VALUE_NONE);
    }

    protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
    {
        check($input->getOption('known') === true, 'Plain option lookup succeeds for --known declaration');
        check($input->getOption('verbose') === false, 'Plain built-in verbose option lookup succeeds');
        check($input->getArgument('target') === 't1', 'Plain argument lookup succeeds');

        foreach (['--known', '-known', '-k', 'k', '--verbose', '-v', 'unknown_plain'] as $invalidOption) {
            try {
                $input->getOption($invalidOption);
                throw new RuntimeException("Dashed or undeclared option lookup {$invalidOption} must throw");
            } catch (\Symfony\Component\Console\Exception\InvalidArgumentException) {
            }
        }

        try {
            $input->getArgument('--target');
            throw new RuntimeException('Dashed argument lookup --target must throw');
        } catch (\Symfony\Component\Console\Exception\InvalidArgumentException) {
        }

        return \Symfony\Component\Console\Command\Command::SUCCESS;
    }
};
$dashedApp->add($dashedCmd);
check(
    $dashedApp->run(new \Symfony\Component\Console\Input\ArrayInput(['command' => 'runtime:dashed', 'target' => 't1', '--known' => true]), $bufferedOut) === \Symfony\Component\Console\Command\Command::SUCCESS,
    'Console option lookup runtime assertions pass under Application',
);

$setDefDashedCmd = new \SymfonyFixtureXml\SetDefinitionDashedOptionLookupCommand();
try {
    $setDefDashedCmd->run(new \Symfony\Component\Console\Input\ArrayInput(['--known' => true]), $bufferedOut);
    throw new RuntimeException('setDefinition dashed option lookup --known must throw at runtime');
} catch (\Symfony\Component\Console\Exception\InvalidArgumentException) {
}

foreach ([-1, 0, 8] as $badArgMode) {
    try {
        new \Symfony\Component\Console\Input\InputArgument('bad', $badArgMode);
        throw new RuntimeException("Argument mode {$badArgMode} must throw InvalidArgumentException");
    } catch (\Symfony\Component\Console\Exception\InvalidArgumentException) {
    }
}
foreach ([1, 2, 3, 4, 5, 6, 7] as $okArgMode) {
    $arg = new \Symfony\Component\Console\Input\InputArgument('ok', $okArgMode);
    check($arg->getName() === 'ok', "Argument mode {$okArgMode} is accepted at runtime");
}

foreach ([-1, 0, 8, 9, 18, 20, 24, 32] as $badOptMode) {
    try {
        new \Symfony\Component\Console\Input\InputOption('bad', null, $badOptMode);
        throw new RuntimeException("Option mode {$badOptMode} must throw InvalidArgumentException");
    } catch (\Symfony\Component\Console\Exception\InvalidArgumentException) {
    }
}
foreach ([1, 2, 4, 10, 12, 16] as $okOptMode) {
    $opt = new \Symfony\Component\Console\Input\InputOption('ok', null, $okOptMode);
    check($opt->getName() === 'ok', "Option mode {$okOptMode} is accepted at runtime");
}

$singleDashOpt = new \Symfony\Component\Console\Input\InputOption('-dash', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE);
check($singleDashOpt->getName() === '-dash', 'Single-dash option name stays literal at runtime');
$tripleDashOpt = new \Symfony\Component\Console\Input\InputOption('---triple', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE);
check($tripleDashOpt->getName() === '-triple', 'Triple-dash option strips only one leading double-dash');
foreach (['', '--'] as $emptyOptName) {
    try {
        new \Symfony\Component\Console\Input\InputOption($emptyOptName);
        throw new RuntimeException("Empty option name {$emptyOptName} must throw");
    } catch (\Symfony\Component\Console\Exception\InvalidArgumentException) {
    }
}

$singleDashApp = new \Symfony\Component\Console\Application();
$singleDashApp->setAutoExit(false);
$singleDashCmd = new class extends \Symfony\Component\Console\Command\Command {
    public function __construct()
    {
        parent::__construct('runtime:single-dash');
    }

    protected function configure(): void
    {
        $this
            ->addOption('-dash', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE)
            ->addOption('---triple', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE)
            ->addOption('--known', 'k', \Symfony\Component\Console\Input\InputOption::VALUE_NONE)
            ->addOption('color', null, \Symfony\Component\Console\Input\InputOption::VALUE_NEGATABLE);
    }

    protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
    {
        check($input->getOption('-dash') === false, 'Exact single-dash lookup succeeds at runtime');
        check($input->getOption('-triple') === false, 'Triple-dash normalized to single-dash succeeds at runtime');
        check($input->getOption('known') === false, 'Double-dash normalized option succeeds at runtime');
        check($input->getOption('no-color') === null, 'Negated form of VALUE_NEGATABLE option succeeds at runtime');
        check($input->hasOption('-dash') === true, 'hasOption(-dash) returns true');
        check($input->hasOption('dash') === false, 'hasOption(dash) returns false without throwing');
        check($input->hasOption('--known') === false, 'hasOption(--known) returns false without throwing');
        check($input->hasOption('known') === true, 'hasOption(known) returns true');
        check($input->hasOption('unknown') === false, 'hasOption(unknown) returns false without throwing');
        check($input->hasOption('') === false, 'hasOption("") returns false without throwing');

        foreach (['dash', 'triple', '---triple', '--known', '', '--'] as $missingOpt) {
            try {
                $input->getOption($missingOpt);
                throw new RuntimeException("Option lookup {$missingOpt} must throw at runtime");
            } catch (\Symfony\Component\Console\Exception\InvalidArgumentException) {
            }
        }

        return \Symfony\Component\Console\Command\Command::SUCCESS;
    }
};
$singleDashApp->add($singleDashCmd);
check(
    $singleDashApp->run(new \Symfony\Component\Console\Input\ArrayInput(['command' => 'runtime:single-dash']), $bufferedOut) === \Symfony\Component\Console\Command\Command::SUCCESS,
    'Single-dash declaration and exact lookup runtime contract passes',
);

$hasSilentOption = defined(\Symfony\Component\Console\Output\OutputInterface::class . '::VERBOSITY_SILENT');
$plainDefaultsApp = new \Symfony\Component\Console\Application();
$plainDefaultsApp->setAutoExit(false);
$plainDefaultsCmd = new class($hasSilentOption) extends \Symfony\Component\Console\Command\Command {
    public function __construct(private readonly bool $expectSilent)
    {
        parent::__construct('runtime:plain-defaults');
    }

    protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
    {
        foreach (['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction'] as $baseOpt) {
            check($input->hasOption($baseOpt) === true, "Plain Application defines {$baseOpt}");
            $input->getOption($baseOpt);
        }
        foreach (['env', 'no-debug', 'profile'] as $fwOpt) {
            try {
                $input->getOption($fwOpt);
                throw new RuntimeException("Plain Application must not define {$fwOpt}");
            } catch (\Symfony\Component\Console\Exception\InvalidArgumentException) {
            }
        }
        if ($this->expectSilent) {
            check($input->getOption('silent') === false, 'Symfony 7.2+ Application defines silent');
        } else {
            try {
                $input->getOption('silent');
                throw new RuntimeException('Symfony 6.4 Application must not define silent');
            } catch (\Symfony\Component\Console\Exception\InvalidArgumentException) {
            }
        }

        return \Symfony\Component\Console\Command\Command::SUCCESS;
    }
};
$plainDefaultsApp->add($plainDefaultsCmd);
check(
    $plainDefaultsApp->run(new \Symfony\Component\Console\Input\ArrayInput(['command' => 'runtime:plain-defaults']), $bufferedOut) === \Symfony\Component\Console\Command\Command::SUCCESS,
    'Plain Application default option runtime contract passes',
);

$localSilentApp = new \Symfony\Component\Console\Application();
$localSilentApp->setAutoExit(false);
$localSilentCmd = new class extends \Symfony\Component\Console\Command\Command {
    public function __construct()
    {
        parent::__construct('runtime:local-silent');
    }

    protected function configure(): void
    {
        $this->addOption('silent', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE);
    }

    protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
    {
        check($input->getOption('silent') === false, 'Command-local silent option is always readable');

        return \Symfony\Component\Console\Command\Command::SUCCESS;
    }
};
$localSilentApp->add($localSilentCmd);
check(
    $localSilentApp->run(new \Symfony\Component\Console\Input\ArrayInput(['command' => 'runtime:local-silent']), $bufferedOut) === \Symfony\Component\Console\Command\Command::SUCCESS,
    'Command-local silent option runtime contract passes',
);

$stubKernel = new class implements \Symfony\Component\HttpKernel\KernelInterface {
    public function registerBundles(): iterable { return []; }
    public function registerContainerConfiguration(\Symfony\Component\Config\Loader\LoaderInterface $loader): void {}
    public function boot(): void {}
    public function shutdown(): void {}
    public function getBundles(): array { return []; }
    public function getBundle(string $name): \Symfony\Component\HttpKernel\Bundle\BundleInterface { throw new RuntimeException('unused'); }
    public function locateResource(string $name): string { return ''; }
    public function getEnvironment(): string { return 'test'; }
    public function isDebug(): bool { return false; }
    public function getProjectDir(): string { return __DIR__; }
    public function getContainer(): \Symfony\Component\DependencyInjection\ContainerInterface { return new Container(); }
    public function getStartTime(): float { return 0.0; }
    public function getCacheDir(): string { return sys_get_temp_dir(); }
    public function getBuildDir(): string { return sys_get_temp_dir(); }
    public function getLogDir(): string { return sys_get_temp_dir(); }
    public function getCharset(): string { return 'UTF-8'; }
    public function handle(\Symfony\Component\HttpFoundation\Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): \Symfony\Component\HttpFoundation\Response { return new \Symfony\Component\HttpFoundation\Response(); }
};
$fwApp = new \Symfony\Bundle\FrameworkBundle\Console\Application($stubKernel);
$fwDef = $fwApp->getDefinition();
check($fwDef->hasOption('env') && $fwDef->hasOption('no-debug') && $fwDef->hasOption('profile'), 'FrameworkBundle Application defines env, no-debug, and profile');
check($fwDef->hasOption('silent') === $hasSilentOption, 'FrameworkBundle Application silent option matches Console version');

require_once __DIR__ . '/fixtures/TreeBuilderReturnPaths.php';
require_once __DIR__ . '/fixtures/TreeBuilderReturnPathsNegative.php';
check((new \SymfonyFixture\StraightLineArrayConfig())->getConfigTreeBuilder()->getRootNode() instanceof \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition, 'Straight-line TreeBuilder returns array root');
check((new \SymfonyFixture\BothBranchesArrayConfig(true))->getConfigTreeBuilder()->getRootNode() instanceof \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition, 'Both-branches true returns array root');
check((new \SymfonyFixture\BothBranchesArrayConfig(false))->getConfigTreeBuilder()->getRootNode() instanceof \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition, 'Both-branches false returns array root');
check((new \SymfonyFixture\ConditionalScalarToArrayConfig(true))->getConfigTreeBuilder()->getRootNode() instanceof \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition, 'Conditional scalar-to-array true branch returns array root');
check((new \SymfonyFixture\ConditionalScalarToArrayConfig(false))->getConfigTreeBuilder()->getRootNode() instanceof \Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition, 'Conditional scalar-to-array false branch returns scalar root');
check((new \SymfonyFixture\LoopConditionalRebindConfig([]))->getConfigTreeBuilder()->getRootNode() instanceof \Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition, 'Zero-iteration loop returns scalar root');
check((new \SymfonyFixture\LoopConditionalRebindConfig(['x']))->getConfigTreeBuilder()->getRootNode() instanceof \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition, 'One-iteration loop returns array root');
check((new \SymfonyFixture\TryCatchIncompleteRebindConfig(true))->getConfigTreeBuilder()->getRootNode() instanceof \Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition, 'Try-catch throwing branch returns scalar root');
check((new \SymfonyFixture\TryCatchIncompleteRebindConfig(false))->getConfigTreeBuilder()->getRootNode() instanceof \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition, 'Try-catch normal branch returns array root');
check((new \SymfonyFixture\TryNestedIfThrowConfig(true))->getConfigTreeBuilder()->getRootNode() instanceof \Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition, 'Try nested if throw branch returns scalar root');
check((new \SymfonyFixture\TryNestedIfReturnFinallyConfig(true))->getConfigTreeBuilder()->getRootNode() instanceof \Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition, 'Try nested if return finally returns scalar root');
check((new \SymfonyFixture\TryBareBlockThrowConfig())->getConfigTreeBuilder()->getRootNode() instanceof \Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition, 'Try bare block throw returns scalar root');
check((new \SymfonyFixture\TwoHopWhileLoopConfig(2))->getConfigTreeBuilder()->getRootNode() instanceof \Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition, 'Two-hop while loop returns scalar root after two iterations');
check((new \SymfonyFixture\TwoHopForLoopConfig(2))->getConfigTreeBuilder()->getRootNode() instanceof \Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition, 'Two-hop for loop returns scalar root after two iterations');
check((new \SymfonyFixture\UnpackedArgsTreeBuilderConfig(['root', 'scalar']))->getConfigTreeBuilder()->getRootNode() instanceof \Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition, 'Unpacked TreeBuilder args can return scalar root');
check((new \SymfonyFixture\NestedAssignmentInCallArgConfig())->getConfigTreeBuilder()->getRootNode() instanceof \Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition, 'Nested assignment in call argument returns scalar root');
check((new \SymfonyFixture\DestructuringInIfConditionConfig(1))->getConfigTreeBuilder()->getRootNode() instanceof \Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition, 'Destructuring in if condition returns scalar root');
check((new \SymfonyFixture\GroupUseArrayConfig())->getConfigTreeBuilder()->getRootNode() instanceof \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition, 'Group use TreeBuilder returns array root');
check((new \SymfonyFixture\ArrowDefaultWithDoubleArrowArrayConfig())->getConfigTreeBuilder()->getRootNode() instanceof \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition, 'Arrow function with double arrow default does not mutate outer TreeBuilder');
check((new \SymfonyFixture\AnonymousClassPropertyArrayConfig())->getConfigTreeBuilder()->getRootNode() instanceof \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition, 'Anonymous class property does not mutate outer TreeBuilder');

$parseIntMethod = new ReflectionMethod(\AlfredBez\MagoSymfony\Analyzer\Providers\ConsoleCommandDefinition::class, 'parseIntegerLiteral');
check($parseIntMethod->invoke(null, '010') === 8, 'Legacy octal literal 010 parses as 8');
check($parseIntMethod->invoke(null, '040') === 32, 'Legacy octal literal 040 parses as 32');
check($parseIntMethod->invoke(null, '0o10') === 8, 'PHP 8.1 octal literal 0o10 parses as 8');
check($parseIntMethod->invoke(null, '0O40') === 32, 'PHP 8.1 octal literal 0O40 parses as 32');
check($parseIntMethod->invoke(null, '08') === null, 'Invalid legacy octal literal 08 returns null');

fwrite(STDOUT, "Symfony runtime contracts PASS\n\n");

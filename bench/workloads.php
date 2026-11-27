<?php

declare(strict_types=1);

namespace SymfonyBench;

use AlfredBez\MagoSymfony\Analyzer\Providers\ConfigEndReturnTypeProvider;
use AlfredBez\MagoSymfony\Analyzer\Providers\ContainerGetReturnTypeProvider;
use AlfredBez\MagoSymfony\Analyzer\Providers\RootNodeReturnTypeProvider;
use AlfredBez\MagoSymfony\Analyzer\Providers\ServiceClassMap;
use AlfredBez\MagoSymfony\Analyzer\Providers\TreeBuilderRootNodeHook;
use Mago\Sdk\Analyzer\AfterFileAnalysisContext;
use Mago\Sdk\Analyzer\Argument;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\FileAnalysis;
use Mago\Sdk\Analyzer\Invocation;
use Mago\Sdk\Analyzer\InvocationKind;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\Metadata\ClassLikeMetadata;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\ReferenceSummary;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\TypeComparator;
use Mago\Sdk\Internal\Analyzer\MetadataCache;
use Mago\Sdk\Internal\Analyzer\NodeAnalysisData;
use Mago\Sdk\Internal\Analyzer\Protocol;
use Mago\Sdk\Internal\HostClient;
use Mago\Sdk\Internal\Io\ResourceWriter;
use Mago\Sdk\Internal\Protocol\FrameCodec;
use Mago\Sdk\Internal\SignalCancellationToken;
use Mago\Sdk\Internal\Syntax\NodeStore;
use Mago\Sdk\Internal\Syntax\ResolvedNameStore;
use Mago\Sdk\Internal\Syntax\TriviaStore;
use Mago\Sdk\PHPVersion;
use Mago\Sdk\SourceLocation;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use ReflectionProperty;

require_once dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Builds deterministic in-process provider and hook workloads with a pre-seeded SDK 1.60.2
 * metadata cache so repeated invocations measure extension logic without IPC jitter.
 *
 * @return array<string, array{fn: \Closure(): mixed, scale: int}>
 */
function providerWorkloads(): array
{
    $ns = 'Symfony\\Component\\Config\\Definition\\Builder\\';
    $treeBuilder = $ns . 'TreeBuilder';
    $arrayNode = $ns . 'ArrayNodeDefinition';
    $scalarNode = $ns . 'ScalarNodeDefinition';
    $booleanNode = $ns . 'BooleanNodeDefinition';
    $integerNode = $ns . 'IntegerNodeDefinition';
    $floatNode = $ns . 'FloatNodeDefinition';
    $enumNode = $ns . 'EnumNodeDefinition';
    $variableNode = $ns . 'VariableNodeDefinition';
    $nodeBuilder = $ns . 'NodeBuilder';
    $nodeDefinition = $ns . 'NodeDefinition';
    $exprBuilder = $ns . 'ExprBuilder';
    $sfContainer = 'Symfony\\Component\\DependencyInjection\\Container';
    $sfContainerInterface = 'Symfony\\Component\\DependencyInjection\\ContainerInterface';
    $sfContainerBuilder = 'Symfony\\Component\\DependencyInjection\\ContainerBuilder';

    $cache = new MetadataCache(1);
    $classLikeBucket = (Protocol::GET_CLASS_LIKES << 4) | Protocol::ANY_CLASS_LIKE;
    $benchLocation = new SourceLocation('bench/workloads.php', new Span(0, 1));
    $seedClassLike = static function (string $originalName, ClassLikeKind $kind) use ($cache, $classLikeBucket, $benchLocation): void {
        $lower = strtolower($originalName);
        $cache->existence[Protocol::EXISTS_CLASS_OR_INTERFACE][$lower] = true;
        $cache->values[$classLikeBucket][$lower] = new ClassLikeMetadata(
            name: $lower,
            originalName: $originalName,
            kind: $kind,
            location: $benchLocation,
            nameLocation: $benchLocation,
            flags: new MetadataFlags(MetadataFlags::USER_DEFINED),
            directParentClass: null,
            directParentInterfaces: [],
            parentInterfaces: [],
            parentClasses: [],
            requiredExtends: [],
            requiredImplements: [],
            usedTraits: [],
            unresolvedHierarchyDependencies: [],
            methods: [],
            pseudoMethods: [],
            staticPseudoMethods: [],
            properties: [],
            magicProperties: [],
            constants: [],
            enumCases: [],
            children: null,
            permittedInheritors: null,
            templates: [],
            attributes: [],
            typeAliases: [],
            mixins: [],
            enumType: null,
            sealedMethods: null,
            sealedProperties: null,
            availableVersions: [],
        );
    };

    for ($i = 0; $i < 16; ++$i) {
        $seedClassLike(sprintf('SymfonyBench\\BenchService%02d', $i), ClassLikeKind::Class_);
    }
    foreach ([
        ConcreteMailer::class => ClassLikeKind::Class_,
        ConcreteLogger::class => ClassLikeKind::Class_,
        MailerInterface::class => ClassLikeKind::Interface,
        LoggerInterface::class => ClassLikeKind::Interface,
    ] as $class => $kind) {
        $seedClassLike($class, $kind);
    }
    $cache->existence[Protocol::EXISTS_CLASS_OR_INTERFACE]['app.unknown.service'] = false;
    $cache->values[$classLikeBucket]['app.unknown.service'] = null;

    foreach ([
        $arrayNode,
        $scalarNode,
        $booleanNode,
        $integerNode,
        $floatNode,
        $enumNode,
        $variableNode,
    ] as $nodeClass) {
        $cache->existence[Protocol::EXISTS_CLASS][strtolower($nodeClass)] = true;
    }

    $methodBucket = 256 | Protocol::EXISTS_METHOD;
    foreach (['isRequired', 'cannotBeEmpty', 'defaultValue', 'info', 'end'] as $method) {
        $cache->existence[$methodBucket][strtolower($scalarNode) . "\0" . strtolower($method)] = true;
    }

    $cache->relations[Protocol::ALL_ANCESTORS][strtolower(BenchNodeBuilder::class)] = [$nodeBuilder];
    $cache->relations[Protocol::ALL_ANCESTORS][strtolower($arrayNode)] = [$nodeDefinition];
    $cache->relations[Protocol::ALL_ANCESTORS][strtolower($nodeBuilder)] = [];

    $containerType = Type::namedObject($sfContainer);
    $builderType = Type::namedObject($sfContainerBuilder);
    $flag0 = Type::literalInt(0);
    $flag1 = Type::literalInt(1);
    $flag2 = Type::literalInt(2);
    $flag01 = Type::union($flag0, $flag1);
    $throwingBuilder = Type::union($flag1, $flag0);

    $seedContainedBy = static function (Type $left, Type $right, bool $result) use ($cache): void {
        $key = pack('C', Protocol::TYPE_COMPARISON_CONTAINED_BY) . $left->encode() . $right->encode();
        $cache->typeComparisons[$key] = $result;
    };

    $seedContainedBy($containerType, $builderType, false);
    $seedContainedBy($builderType, $builderType, true);
    $seedContainedBy($flag1, $flag1, true);
    $seedContainedBy($flag2, $flag1, false);
    $seedContainedBy($flag0, $flag1, false);
    $seedContainedBy($flag0, $throwingBuilder, true);
    $seedContainedBy($flag01, $throwingBuilder, true);
    $seedContainedBy(Type::int(), $flag1, false);

    $host = new HostClient(new FrameCodec(), new ResourceWriter(fopen('php://memory', 'w+')));
    $cancellation = new SignalCancellationToken();
    $codebase = new Codebase($host, 1, $cancellation, $cache);
    $types = new TypeComparator($host, 1, $cancellation, $cache);
    $php = PHPVersion::fromParts(8, 4);
    $span = new Span(0, 1);

    $invocation = static function (string $name, string $class, Type $receiver, array $args = []) use ($span): Invocation {
        return new Invocation(InvocationKind::InstanceMethod, $name, $class, $receiver, $span, $args);
    };
    $argument = static fn (Type $type, ?string $name = null): Argument => new Argument($name, false, false, $span, 'fixture', $type);
    $context = static fn (Invocation $call): ReturnTypeProviderContext => new ReturnTypeProviderContext($php, $codebase, $call, $types, $cancellation);

    $mapPath = __DIR__ . '/container-map.json';
    putenv(ServiceClassMap::ENVIRONMENT_VARIABLE . '=' . $mapPath);
    $serviceMap = ServiceClassMap::fromEnvironment();

    $containerProvider = new ContainerGetReturnTypeProvider($serviceMap);
    $rootNodeProvider = new RootNodeReturnTypeProvider();
    $configProvider = new ConfigEndReturnTypeProvider();
    $hook = new TreeBuilderRootNodeHook();

    $ctxClassId = $context($invocation('get', $sfContainerInterface, $containerType, [$argument(Type::literalString(BenchService00::class))]));
    $ctxMapId = $context($invocation('get', $sfContainerInterface, $containerType, [$argument(Type::literalString('app.service.042'))]));
    $ctxFlagThrow = $context($invocation('get', $sfContainer, $containerType, [$argument(Type::literalString('app.service.042')), $argument($flag1)]));
    $ctxFlagNull = $context($invocation('get', $sfContainer, $containerType, [$argument(Type::literalString('app.service.042')), $argument($flag2)]));
    $ctxFlagBuilder0 = $context($invocation('get', $sfContainerBuilder, $builderType, [$argument(Type::literalString('app.service.042')), $argument($flag0)]));
    $ctxFlagBuilderUnion = $context($invocation('get', $sfContainerBuilder, $builderType, [$argument(Type::literalString('app.service.042')), $argument($flag01)]));
    $ctxUnknown = $context($invocation('get', $sfContainerInterface, $containerType, [$argument(Type::literalString('app.unknown.service'))]));

    $treeType = Type::namedObject($treeBuilder);
    $ctxRootNode = $context($invocation('getRootNode', $treeBuilder, $treeType));
    $rootType = $rootNodeProvider->getReturnType($ctxRootNode);
    if ($rootType === null) {
        throw new \RuntimeException('RootNodeReturnTypeProvider did not return a type.');
    }

    $ctxChildren = $context($invocation('children', $arrayNode, $rootType));
    $builderWithParent = $configProvider->getReturnType($ctxChildren);
    if ($builderWithParent === null) {
        throw new \RuntimeException('ConfigEndReturnTypeProvider::children did not return a type.');
    }

    $ctxScalarNode = $context($invocation('scalarNode', $nodeBuilder, $builderWithParent, [$argument(Type::literalString('locale'))]));
    $scalarWithParent = $configProvider->getReturnType($ctxScalarNode);
    if ($scalarWithParent === null) {
        throw new \RuntimeException('ConfigEndReturnTypeProvider::scalarNode did not return a type.');
    }

    $ctxNodeLiteral = $context($invocation('node', $nodeBuilder, $builderWithParent, [$argument(Type::literalString('retries')), $argument(Type::literalString('integer'))]));
    $ctxPrototype = $context($invocation('prototype', $arrayNode, $rootType, [$argument(Type::literalString('array'))]));
    $ctxProtoShortcut = $context($invocation('scalarPrototype', $arrayNode, $rootType));
    $ctxValidate = $context($invocation('validate', $nodeDefinition, $scalarWithParent));
    $exprWithParent = $configProvider->getReturnType($ctxValidate);
    if ($exprWithParent === null) {
        throw new \RuntimeException('ConfigEndReturnTypeProvider::validate did not return a type.');
    }

    $ctxEndTracked = $context($invocation('end', $exprBuilder, $exprWithParent));
    $ctxEndUntrackedBuilder = $context($invocation('end', BenchNodeBuilder::class, Type::namedObject(BenchNodeBuilder::class)));
    $ctxEndUntrackedNode = $context($invocation('end', $arrayNode, Type::namedObject($arrayNode)));

    [$hookScalarContext, $hookArrayContext] = buildHookContexts($php, $codebase, $types, $host, $cancellation);

    // Sanity check all providers return expected shapes before timing.
    if ($containerProvider->getReturnType($ctxClassId) === null) {
        throw new \RuntimeException('ContainerGetReturnTypeProvider failed on class id.');
    }
    if ($containerProvider->getReturnType($ctxMapId) === null) {
        throw new \RuntimeException('ContainerGetReturnTypeProvider failed on map id.');
    }
    if ($containerProvider->getReturnType($ctxUnknown) !== null) {
        throw new \RuntimeException('ContainerGetReturnTypeProvider unexpectedly resolved unknown id.');
    }
    $hook->analyze($hookScalarContext);
    if ($hookScalarContext->takeReportedIssues() !== []) {
        throw new \RuntimeException('TreeBuilderRootNodeHook reported unexpected issues on valid scalar workload.');
    }
    $hook->analyze($hookArrayContext);
    if ($hookArrayContext->takeReportedIssues() !== []) {
        throw new \RuntimeException('TreeBuilderRootNodeHook reported unexpected issues on valid array workload.');
    }

    return [
        'service_map_load' => [
            'fn' => static fn (): ServiceClassMap => ServiceClassMap::fromEnvironment(),
            'scale' => 20,
        ],
        'container_class_id' => [
            'fn' => static fn (): ?string => $containerProvider->getReturnType($ctxClassId)?->encode(),
            'scale' => 1,
        ],
        'container_map_id' => [
            'fn' => static fn (): ?string => $containerProvider->getReturnType($ctxMapId)?->encode(),
            'scale' => 1,
        ],
        'container_flag_throw' => [
            'fn' => static fn (): ?string => $containerProvider->getReturnType($ctxFlagThrow)?->encode(),
            'scale' => 1,
        ],
        'container_flag_null' => [
            'fn' => static fn (): ?string => $containerProvider->getReturnType($ctxFlagNull)?->encode(),
            'scale' => 1,
        ],
        'container_flag_builder_0' => [
            'fn' => static fn (): ?string => $containerProvider->getReturnType($ctxFlagBuilder0)?->encode(),
            'scale' => 1,
        ],
        'container_flag_union' => [
            'fn' => static fn (): ?string => $containerProvider->getReturnType($ctxFlagBuilderUnion)?->encode(),
            'scale' => 1,
        ],
        'container_unknown' => [
            'fn' => static fn (): ?string => $containerProvider->getReturnType($ctxUnknown)?->encode(),
            'scale' => 1,
        ],
        'root_node' => [
            'fn' => static fn (): ?string => $rootNodeProvider->getReturnType($ctxRootNode)?->encode(),
            'scale' => 1,
        ],
        'config_children' => [
            'fn' => static fn (): ?string => $configProvider->getReturnType($ctxChildren)?->encode(),
            'scale' => 1,
        ],
        'config_scalar_node' => [
            'fn' => static fn (): ?string => $configProvider->getReturnType($ctxScalarNode)?->encode(),
            'scale' => 1,
        ],
        'config_node_literal' => [
            'fn' => static fn (): ?string => $configProvider->getReturnType($ctxNodeLiteral)?->encode(),
            'scale' => 1,
        ],
        'config_prototype' => [
            'fn' => static fn (): ?string => $configProvider->getReturnType($ctxPrototype)?->encode(),
            'scale' => 1,
        ],
        'config_proto_shortcut' => [
            'fn' => static fn (): ?string => $configProvider->getReturnType($ctxProtoShortcut)?->encode(),
            'scale' => 1,
        ],
        'config_validate' => [
            'fn' => static fn (): ?string => $configProvider->getReturnType($ctxValidate)?->encode(),
            'scale' => 1,
        ],
        'config_end_tracked' => [
            'fn' => static fn (): ?string => $configProvider->getReturnType($ctxEndTracked)?->encode(),
            'scale' => 1,
        ],
        'config_end_builder' => [
            'fn' => static fn (): ?string => $configProvider->getReturnType($ctxEndUntrackedBuilder)?->encode(),
            'scale' => 1,
        ],
        'config_end_node' => [
            'fn' => static fn (): ?string => $configProvider->getReturnType($ctxEndUntrackedNode)?->encode(),
            'scale' => 1,
        ],
        'config_chain_full' => [
            'fn' => static function () use (
                $rootNodeProvider,
                $configProvider,
                $context,
                $invocation,
                $argument,
                $treeBuilder,
                $treeType,
                $arrayNode,
                $nodeBuilder,
                $nodeDefinition,
                $exprBuilder,
            ): ?string {
                $t1 = $rootNodeProvider->getReturnType($context($invocation('getRootNode', $treeBuilder, $treeType)));
                $t1?->encode();
                $t2 = $configProvider->getReturnType($context($invocation('children', $arrayNode, $t1)));
                $t2?->encode();
                $t3 = $configProvider->getReturnType($context($invocation('arrayNode', $nodeBuilder, $t2, [$argument(Type::literalString('db'))])));
                $t3?->encode();
                $t4 = $configProvider->getReturnType($context($invocation('children', $arrayNode, $t3)));
                $t4?->encode();
                $t5 = $configProvider->getReturnType($context($invocation('scalarNode', $nodeBuilder, $t4, [$argument(Type::literalString('dsn'))])));
                $t5?->encode();
                $t6 = $configProvider->getReturnType($context($invocation('validate', $nodeDefinition, $t5)));
                $t6?->encode();
                $t7 = $configProvider->getReturnType($context($invocation('end', $exprBuilder, $t6)));
                $t7?->encode();
                $t8 = $configProvider->getReturnType($context($invocation('end', $nodeDefinition, $t7)));
                $t8?->encode();
                $t9 = $configProvider->getReturnType($context($invocation('end', $nodeBuilder, $t8)));
                $t9?->encode();
                $t10 = $configProvider->getReturnType($context($invocation('end', $nodeDefinition, $t9)));
                $t10?->encode();

                return $configProvider->getReturnType($context($invocation('end', $nodeBuilder, $t10)))?->encode();
            },
            'scale' => 10,
        ],
        'hook_root_scalar' => [
            'fn' => static function () use ($hook, $hookScalarContext): void {
                $hook->analyze($hookScalarContext);
            },
            'scale' => 100,
        ],
        'hook_root_array' => [
            'fn' => static function () use ($hook, $hookArrayContext): void {
                $hook->analyze($hookArrayContext);
            },
            'scale' => 100,
        ],
    ];
}

/**
 * Constructs realistic packed AST snapshots matching Mago's NodeStore binary format so
 * TreeBuilderRootNodeHook can be benchmarked in-process without IPC.
 *
 * @return array{NodeAnalysisContext, NodeAnalysisContext}
 */
function buildHookContexts(
    PHPVersion $php,
    Codebase $codebase,
    TypeComparator $types,
    HostClient $host,
    SignalCancellationToken $cancellation,
): array {
    $scalar = buildSyntheticHookContext($php, $codebase, $types, $host, $cancellation, 'scalar');
    $array = buildSyntheticHookContext($php, $codebase, $types, $host, $cancellation, 'array');

    return [$scalar, $array];
}

function buildSyntheticHookContext(
    PHPVersion $php,
    Codebase $codebase,
    TypeComparator $types,
    HostClient $host,
    SignalCancellationToken $cancellation,
    string $rootType,
): NodeAnalysisContext {
    $builder = new SyntheticAstBuilder();
    $code = '';

    $append = static function (string $chunk) use (&$code): array {
        $start = strlen($code);
        $code .= $chunk;

        return [$start, strlen($code)];
    };

    $append("<?php\nfinal class SyntheticConfig {\n    public function configure(): void {\n");

    $program = $builder->addNode(NodeKind::Program, 0, 0, null);
    $method = $builder->addNode(NodeKind::Method, 0, 0, $program);

    $resolvedNames = [];

    // 25 preceding filler assignments and method calls inside the method body.
    for ($i = 0; $i < 25; ++$i) {
        $stmtStart = strlen($code);
        $append('        ');
        [$lhsStart, $lhsEnd] = $append("\$pre{$i}");
        $append(' = ');
        [$recvStart, $recvEnd] = $append('$svc');
        $append('->');
        [$memStart, $memEnd] = $append('step');
        [$argListStart, $argListEnd] = $append('()');
        $append(";\n");

        $assign = $builder->addNode(NodeKind::Assignment, $lhsStart, $argListEnd, $method);
        $lhsVar = $builder->addNode(NodeKind::Variable, $lhsStart, $lhsEnd, $assign);
        $builder->addNode(NodeKind::DirectVariable, $lhsStart, $lhsEnd, $lhsVar);

        $rhsCall = $builder->addNode(NodeKind::MethodCall, $recvStart, $argListEnd, $assign);
        $recvVar = $builder->addNode(NodeKind::Variable, $recvStart, $recvEnd, $rhsCall);
        $builder->addNode(NodeKind::DirectVariable, $recvStart, $recvEnd, $recvVar);
        $memNode = $builder->addNode(NodeKind::ClassLikeMemberSelector, $memStart, $memEnd, $rhsCall);
        $builder->addNode(NodeKind::LocalIdentifier, $memStart, $memEnd, $memNode);
        $builder->addNode(NodeKind::ArgumentList, $argListStart, $argListEnd, $rhsCall);
    }

    // $tree = new TreeBuilder('bench', '<rootType>');
    $append('        ');
    [$tbVarStart, $tbVarEnd] = $append('$tree');
    $append(' = ');
    [$newStart, $newKwEnd] = $append('new');
    $append(' ');
    [$tbIdentStart, $tbIdentEnd] = $append('TreeBuilder');
    [$tbArgsStart, $tbArgsOpenEnd] = $append('(');
    [$arg0Start, $arg0End] = $append("'bench'");
    $append(', ');
    [$arg1Start, $arg1End] = $append("'{$rootType}'");
    [$tbArgsCloseStart, $tbArgsEnd] = $append(')');
    $append(";\n");

    $resolvedNames[] = [$tbIdentStart, $tbIdentEnd, 'Symfony\\Component\\Config\\Definition\\Builder\\TreeBuilder'];

    $tbAssign = $builder->addNode(NodeKind::Assignment, $tbVarStart, $tbArgsEnd, $method);
    $tbLhs = $builder->addNode(NodeKind::Variable, $tbVarStart, $tbVarEnd, $tbAssign);
    $builder->addNode(NodeKind::DirectVariable, $tbVarStart, $tbVarEnd, $tbLhs);

    $inst = $builder->addNode(NodeKind::Instantiation, $newStart, $tbArgsEnd, $tbAssign);
    $builder->addNode(NodeKind::Keyword, $newStart, $newKwEnd, $inst);
    $builder->addNode(NodeKind::LocalIdentifier, $tbIdentStart, $tbIdentEnd, $inst);
    $tbArgList = $builder->addNode(NodeKind::ArgumentList, $tbArgsStart, $tbArgsEnd, $inst);

    $a0 = $builder->addNode(NodeKind::Argument, $arg0Start, $arg0End, $tbArgList);
    $p0 = $builder->addNode(NodeKind::PositionalArgument, $arg0Start, $arg0End, $a0);
    $l0 = $builder->addNode(NodeKind::Literal, $arg0Start, $arg0End, $p0);
    $builder->addNode(NodeKind::LiteralString, $arg0Start, $arg0End, $l0);

    $a1 = $builder->addNode(NodeKind::Argument, $arg1Start, $arg1End, $tbArgList);
    $p1 = $builder->addNode(NodeKind::PositionalArgument, $arg1Start, $arg1End, $a1);
    $l1 = $builder->addNode(NodeKind::Literal, $arg1Start, $arg1End, $p1);
    $builder->addNode(NodeKind::LiteralString, $arg1Start, $arg1End, $l1);

    // $root = $tree->getRootNode();
    $append('        ');
    [$rootLhsStart, $rootLhsEnd] = $append('$root');
    $append(' = ');
    [$rootRecvStart, $rootRecvEnd] = $append('$tree');
    $append('->');
    [$getRootMemStart, $getRootMemEnd] = $append('getRootNode');
    [$getRootArgsStart, $getRootArgsEnd] = $append('()');
    $append(";\n");

    $rootAssign = $builder->addNode(NodeKind::Assignment, $rootLhsStart, $getRootArgsEnd, $method);
    $rootLhs = $builder->addNode(NodeKind::Variable, $rootLhsStart, $rootLhsEnd, $rootAssign);
    $builder->addNode(NodeKind::DirectVariable, $rootLhsStart, $rootLhsEnd, $rootLhs);

    $getRootCallId = $builder->addNode(NodeKind::MethodCall, $rootRecvStart, $getRootArgsEnd, $rootAssign);
    $rootRecv = $builder->addNode(NodeKind::Variable, $rootRecvStart, $rootRecvEnd, $getRootCallId);
    $builder->addNode(NodeKind::DirectVariable, $rootRecvStart, $rootRecvEnd, $rootRecv);
    $getRootMem = $builder->addNode(NodeKind::ClassLikeMemberSelector, $getRootMemStart, $getRootMemEnd, $getRootCallId);
    $builder->addNode(NodeKind::LocalIdentifier, $getRootMemStart, $getRootMemEnd, $getRootMem);
    $builder->addNode(NodeKind::ArgumentList, $getRootArgsStart, $getRootArgsEnd, $getRootCallId);

    // 2 nested closures with shadowed $root and 10 statements each (tests nested scope pruning).
    for ($c = 0; $c < 2; ++$c) {
        $closureStart = strlen($code);
        $append("        \$fn{$c} = static function () {\n");
        $closure = $builder->addNode(NodeKind::Closure, $closureStart, 0, $method);
        for ($j = 0; $j < 10; ++$j) {
            $append('            ');
            [$cLhsStart, $cLhsEnd] = $append('$root');
            $append(' = ');
            [$cRecvStart, $cRecvEnd] = $append('$inner');
            $append('->');
            [$cMemStart, $cMemEnd] = $append('children');
            [$cArgsStart, $cArgsEnd] = $append('()');
            $append(";\n");

            $cAssign = $builder->addNode(NodeKind::Assignment, $cLhsStart, $cArgsEnd, $closure);
            $cVar = $builder->addNode(NodeKind::Variable, $cLhsStart, $cLhsEnd, $cAssign);
            $builder->addNode(NodeKind::DirectVariable, $cLhsStart, $cLhsEnd, $cVar);

            $cCall = $builder->addNode(NodeKind::MethodCall, $cRecvStart, $cArgsEnd, $cAssign);
            $cRecv = $builder->addNode(NodeKind::Variable, $cRecvStart, $cRecvEnd, $cCall);
            $builder->addNode(NodeKind::DirectVariable, $cRecvStart, $cRecvEnd, $cRecv);
            $cMem = $builder->addNode(NodeKind::ClassLikeMemberSelector, $cMemStart, $cMemEnd, $cCall);
            $builder->addNode(NodeKind::LocalIdentifier, $cMemStart, $cMemEnd, $cMem);
            $builder->addNode(NodeKind::ArgumentList, $cArgsStart, $cArgsEnd, $cCall);
        }
        $append("        };\n");
        $builder->setEnd($closure, strlen($code));
    }

    // 25 trailing filler statements in the same scope after $root = $tree->getRootNode().
    for ($i = 0; $i < 25; ++$i) {
        $append('        ');
        [$lhsStart, $lhsEnd] = $append("\$post{$i}");
        $append(' = ');
        [$recvStart, $recvEnd] = $append('$svc');
        $append('->');
        [$memStart, $memEnd] = $append('work');
        [$argListStart, $argListEnd] = $append('()');
        $append(";\n");

        $assign = $builder->addNode(NodeKind::Assignment, $lhsStart, $argListEnd, $method);
        $lhsVar = $builder->addNode(NodeKind::Variable, $lhsStart, $lhsEnd, $assign);
        $builder->addNode(NodeKind::DirectVariable, $lhsStart, $lhsEnd, $lhsVar);

        $rhsCall = $builder->addNode(NodeKind::MethodCall, $recvStart, $argListEnd, $assign);
        $recvVar = $builder->addNode(NodeKind::Variable, $recvStart, $recvEnd, $rhsCall);
        $builder->addNode(NodeKind::DirectVariable, $recvStart, $recvEnd, $recvVar);
        $memNode = $builder->addNode(NodeKind::ClassLikeMemberSelector, $memStart, $memEnd, $rhsCall);
        $builder->addNode(NodeKind::LocalIdentifier, $memStart, $memEnd, $memNode);
        $builder->addNode(NodeKind::ArgumentList, $argListStart, $argListEnd, $rhsCall);
    }

    // $root->isRequired()->cannotBeEmpty()->end();
    $append('        ');
    [$rUseStart, $rUseEnd] = $append('$root');
    $append('->');
    [$m1Start, $m1End] = $append('isRequired');
    [$a1Start, $a1End] = $append('()');
    $append('->');
    [$m2Start, $m2End] = $append('cannotBeEmpty');
    [$a2Start, $a2End] = $append('()');
    $append('->');
    [$m3Start, $m3End] = $append('end');
    [$a3Start, $a3End] = $append('()');
    $append(";\n    }\n}\n");

    $call3 = $builder->addNode(NodeKind::MethodCall, $rUseStart, $a3End, $method);
    $call2 = $builder->addNode(NodeKind::MethodCall, $rUseStart, $a2End, $call3);
    $call1 = $builder->addNode(NodeKind::MethodCall, $rUseStart, $a1End, $call2);

    $rUseVar = $builder->addNode(NodeKind::Variable, $rUseStart, $rUseEnd, $call1);
    $builder->addNode(NodeKind::DirectVariable, $rUseStart, $rUseEnd, $rUseVar);
    $m1Node = $builder->addNode(NodeKind::ClassLikeMemberSelector, $m1Start, $m1End, $call1);
    $builder->addNode(NodeKind::LocalIdentifier, $m1Start, $m1End, $m1Node);
    $builder->addNode(NodeKind::ArgumentList, $a1Start, $a1End, $call1);

    $m2Node = $builder->addNode(NodeKind::ClassLikeMemberSelector, $m2Start, $m2End, $call2);
    $builder->addNode(NodeKind::LocalIdentifier, $m2Start, $m2End, $m2Node);
    $builder->addNode(NodeKind::ArgumentList, $a2Start, $a2End, $call2);

    $m3Node = $builder->addNode(NodeKind::ClassLikeMemberSelector, $m3Start, $m3End, $call3);
    $builder->addNode(NodeKind::LocalIdentifier, $m3Start, $m3End, $m3Node);
    $builder->addNode(NodeKind::ArgumentList, $a3Start, $a3End, $call3);

    $totalLen = strlen($code);
    $builder->setEnd($method, $totalLen);
    $builder->setEnd($program, $totalLen);

    $startsBin = '';
    $recordsBin = '';
    $bytesBin = '';
    foreach ($resolvedNames as [$s, $e, $fqcn]) {
        $offset = strlen($bytesBin);
        $bytesBin .= $fqcn;
        $startsBin .= pack('N', $s);
        $recordsBin .= pack('N3C', $e, $offset, strlen($fqcn), 0);
    }

    [$kinds, $nodeStore] = $builder->build();
    $sourceFile = new SourceFile(
        phpVersion: $php,
        path: 'bench/fixtures/SyntheticHook.php',
        contents: $code,
        targetNodeIds: [$getRootCallId],
        nodes: $nodeStore,
        resolvedNames: new ResolvedNameStore($startsBin, $recordsBin, $bytesBin, count($resolvedNames)),
        trivia: new TriviaStore('', 0),
        literalStrings: null,
    );

    $fileAnalysis = new FileAnalysis(
        host: $host,
        requestId: 1,
        generation: 1,
        cancellation: $cancellation,
        phpVersion: $php,
        nodeKinds: $kinds,
        nodeSourceFile: $sourceFile,
        nodeAnalysisData: [new NodeAnalysisData(null, null, [], null, [0])],
        hasLocalExpressionTypes: false,
        expressionTypeRecords: '',
        encodedExpressionTypes: '',
        file: 'bench/fixtures/SyntheticHook.php',
        size: $totalLen,
        expressionCount: 0,
        inferredReturnCount: 0,
        inferredYieldKeyCount: 0,
        inferredYieldValueCount: 0,
        references: new ReferenceSummary(0, 0, 0),
    );
    (new ReflectionProperty(FileAnalysis::class, 'sourceFile'))->setValue($fileAnalysis, $sourceFile);

    $afterFile = new AfterFileAnalysisContext($php, $codebase, $types, $cancellation, $fileAnalysis);
    $targetNode = $sourceFile->getNode($getRootCallId);

    return new NodeAnalysisContext($afterFile, $sourceFile, $targetNode, new NodeAnalysisData(null, null, [], null, [0]));
}

final class SyntheticAstBuilder
{
    private const NO_NODE = 4_294_967_295;

    /** @var list<NodeKind> */
    private array $kinds = [];

    /** @var array<string, int> */
    private array $kindIndices = [];

    /** @var list<array{kind: int, start: int, end: int, parent: int, firstChild: int, nextSibling: int, lastChild: int}> */
    private array $nodes = [];

    public function addNode(NodeKind $kind, int $start, int $end, ?int $parent): int
    {
        $kindKey = $kind->value;
        if (!isset($this->kindIndices[$kindKey])) {
            $this->kindIndices[$kindKey] = count($this->kinds);
            $this->kinds[] = $kind;
        }

        $id = count($this->nodes);
        $this->nodes[] = [
            'kind' => $this->kindIndices[$kindKey],
            'start' => $start,
            'end' => $end,
            'parent' => $parent ?? self::NO_NODE,
            'firstChild' => self::NO_NODE,
            'nextSibling' => self::NO_NODE,
            'lastChild' => self::NO_NODE,
        ];

        if ($parent !== null) {
            $lastChild = $this->nodes[$parent]['lastChild'];
            if ($lastChild === self::NO_NODE) {
                $this->nodes[$parent]['firstChild'] = $id;
            } else {
                $this->nodes[$lastChild]['nextSibling'] = $id;
            }
            $this->nodes[$parent]['lastChild'] = $id;
        }

        return $id;
    }

    public function setEnd(int $id, int $end): void
    {
        $this->nodes[$id]['end'] = $end;
    }

    /** @return array{list<NodeKind>, NodeStore} */
    public function build(): array
    {
        $records = '';
        foreach ($this->nodes as $n) {
            $records .= pack(
                'CN5',
                $n['kind'],
                $n['start'],
                $n['end'],
                $n['parent'],
                $n['firstChild'],
                $n['nextSibling'],
            );
        }

        return [$this->kinds, new NodeStore($this->kinds, $records, count($this->nodes))];
    }
}

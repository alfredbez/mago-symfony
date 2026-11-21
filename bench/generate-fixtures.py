import json
from pathlib import Path


BENCH_DIR = Path(__file__).resolve().parent
FIXTURES_DIR = BENCH_DIR / "fixtures"
SERVICE_COUNT = 16


def generate_support() -> None:
    lines = [
        "<?php",
        "",
        "declare(strict_types=1);",
        "",
        "namespace SymfonyBench;",
        "",
        "use Symfony\\Component\\Config\\Definition\\Builder\\NodeBuilder;",
        "",
        "interface MailerInterface",
        "{",
        "    public function send(string $to, string $subject): bool;",
        "}",
        "",
        "final class ConcreteMailer implements MailerInterface",
        "{",
        "    public function send(string $to, string $subject): bool",
        "    {",
        "        return $to !== '' && $subject !== '';",
        "    }",
        "",
        "    public function queuedCount(): int",
        "    {",
        "        return 0;",
        "    }",
        "}",
        "",
        "interface LoggerInterface",
        "{",
        "    public function info(string $message): void;",
        "}",
        "",
        "final class ConcreteLogger implements LoggerInterface",
        "{",
        "    public function info(string $message): void",
        "    {",
        "    }",
        "}",
        "",
        "final class BenchNodeBuilder extends NodeBuilder",
        "{",
        "}",
        "",
    ]

    for idx in range(SERVICE_COUNT):
        lines.extend([
            f"final class BenchService{idx:02d}",
            "{",
            f"    public function handle{idx:02d}(string $input): string",
            "    {",
            "        return $input;",
            "    }",
            "",
            "    public function count(): int",
            "    {",
            f"        return {idx};",
            "    }",
            "}",
            "",
        ])

    (FIXTURES_DIR / "Support.php").write_text("\n".join(lines))


def generate_container_map() -> None:
    entries: dict[str, str] = {
        "app.mailer": "SymfonyBench\\ConcreteMailer",
        "app.logger": "SymfonyBench\\ConcreteLogger",
        "SymfonyBench\\MailerInterface": "SymfonyBench\\ConcreteMailer",
        "SymfonyBench\\LoggerInterface": "SymfonyBench\\ConcreteLogger",
    }

    for idx in range(200):
        svc = idx % SERVICE_COUNT
        entries[f"app.service.{idx:03d}"] = f"SymfonyBench\\BenchService{svc:02d}"

    for idx in range(100, 140):
        svc = idx % SERVICE_COUNT
        entries[str(idx)] = f"SymfonyBench\\BenchService{svc:02d}"

    for idx in range(12):
        entries[f"legacy.alias.{idx:02d}"] = "SymfonyBench\\ConcreteMailer"

    (BENCH_DIR / "container-map.json").write_text(
        json.dumps(entries, indent=4) + "\n"
    )


def generate_container_workloads() -> None:
    for file_idx in range(4):
        methods: list[str] = []
        for method_idx in range(16):
            svc_a = (file_idx * 16 + method_idx) % SERVICE_COUNT
            svc_b = (svc_a + 3) % SERVICE_COUNT
            map_id = (file_idx * 16 + method_idx) % 200
            map_svc = map_id % SERVICE_COUNT
            num_id = 100 + ((file_idx * 16 + method_idx) % 40)
            num_svc = num_id % SERVICE_COUNT
            methods.append(f"""    /** @param 0|1 $builderBehavior */
    public function scenario{method_idx}(
        PsrContainer $psr,
        SfContainerInterface $contract,
        Container $container,
        ContainerBuilder $builder,
        ServiceLocator $locator,
        ServiceProviderInterface $provider,
        int $builderBehavior,
    ): int {{
        $a = $psr->get(BenchService{svc_a:02d}::class);
        $b = $container->get(BenchService{svc_b:02d}::class);
        $mapped = $contract->get('app.service.{map_id:03d}');
        $numeric = $locator->get('{num_id}');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.{map_id:03d}', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.{map_id:03d}', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService{svc_a:02d}::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService{svc_a:02d}::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.{map_id:03d}', Container::NULL_ON_INVALID_REFERENCE);
        $nullableIgnore = $container->get(BenchService{svc_b:02d}::class, Container::IGNORE_ON_INVALID_REFERENCE);
        $nullableUninit = $container->get('app.mailer', Container::IGNORE_ON_UNINITIALIZED_REFERENCE);

        $logger->info($a->handle{svc_a:02d}('ok'));
        $mailer->send('dev@example.com', $b->handle{svc_b:02d}('ok'));
        $override->queuedCount();
        $named->send('ops@example.com', 'alert');

        return $a->count()
            + $b->count()
            + $mapped->count()
            + $numeric->count()
            + $thrown->count()
            + $builderRuntime->count()
            + $builderUnion->count()
            + ($nullableRuntime?->count() ?? 0)
            + ($nullableNull?->count() ?? 0)
            + ($nullableIgnore?->count() ?? 0)
            + ($nullableUninit?->queuedCount() ?? 0)
            + {map_svc}
            + {num_svc};
    }}
""")

        content = "\n".join([
            "<?php",
            "",
            "declare(strict_types=1);",
            "",
            "namespace SymfonyBench;",
            "",
            "use Psr\\Container\\ContainerInterface as PsrContainer;",
            "use Symfony\\Component\\DependencyInjection\\Container;",
            "use Symfony\\Component\\DependencyInjection\\ContainerBuilder;",
            "use Symfony\\Component\\DependencyInjection\\ContainerInterface as SfContainerInterface;",
            "use Symfony\\Component\\DependencyInjection\\ServiceLocator;",
            "use Symfony\\Contracts\\Service\\ServiceProviderInterface;",
            "",
            f"final class ContainerWorkload{file_idx}",
            "{",
            "\n".join(methods).rstrip(),
            "}",
            "",
        ])
        (FIXTURES_DIR / f"ContainerWorkload{file_idx}.php").write_text(content)


def generate_config_workloads() -> None:
    for file_idx in range(4):
        methods: list[str] = []
        for method_idx in range(12):
            name = f"bench_{file_idx}_{method_idx}"
            methods.append(f"""    public function buildTree{method_idx}(): TreeBuilder
    {{
        $treeBuilder = new TreeBuilder('{name}');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('locale')->isRequired()->defaultValue('en')->end()
                ->booleanNode('debug')->defaultFalse()->end()
                ->integerNode('ttl')->min(0)->defaultValue(60)->end()
                ->floatNode('ratio')->max(1.0)->defaultValue(0.5)->end()
                ->enumNode('mode')->values(['sync', 'async', 'batch'])->defaultValue('sync')->end()
                ->variableNode('extra')->defaultNull()->end()
                ->node('retries', 'integer')->min(0)->defaultValue(3)->end()
                ->node(type: 'scalar', name: 'channel')->cannotBeEmpty()->end()
                ->arrayNode('connections')
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifString()
                            ->then(static fn (string $v): array => ['dsn' => $v])
                        ->end()
                        ->children()
                            ->scalarNode('dsn')->isRequired()->cannotBeEmpty()->end()
                            ->integerNode('pool_size')->min(1)->defaultValue(10)->end()
                            ->booleanNode('tls')->defaultTrue()->end()
                            ->arrayNode('options')
                                ->children()
                                    ->scalarNode('charset')->defaultValue('utf8mb4')->end()
                                    ->floatNode('timeout')->max(30.0)->defaultValue(5.0)->end()
                                ->end()
                            ->end()
                        ->end()
                        ->validate()
                            ->ifEmpty()
                            ->thenInvalid('Empty connection')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('tags')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->arrayNode('flags')
                    ->booleanPrototype()->defaultTrue()->end()
                ->end()
                ->arrayNode('weights')
                    ->floatPrototype()->max(10.0)->end()
                ->end()
                ->arrayNode('matrix')
                    ->arrayPrototype()
                        ->children()
                            ->integerNode('weight')->min(0)->end()
                            ->variableNode('payload')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }}

    public function standaloneAndPrototypes{method_idx}(BenchNodeBuilder $builder): ?ArrayNodeDefinition
    {{
        $node = new ArrayNodeDefinition('{name}_standalone');
        $node
            ->prototype('integer')->min(0)->end()
            ->prototype('enum')->values(['a', 'b'])->end()
            ->variablePrototype()->defaultNull()->end()
            ->validate()
                ->ifNull()
                ->thenEmptyArray()
            ->end();

        return $builder->end();
    }}
""")

        content = "\n".join([
            "<?php",
            "",
            "declare(strict_types=1);",
            "",
            "namespace SymfonyBench;",
            "",
            "use Symfony\\Component\\Config\\Definition\\Builder\\ArrayNodeDefinition;",
            "use Symfony\\Component\\Config\\Definition\\Builder\\TreeBuilder;",
            "",
            f"final class ConfigWorkload{file_idx}",
            "{",
            "\n".join(methods).rstrip(),
            "}",
            "",
        ])
        (FIXTURES_DIR / f"ConfigWorkload{file_idx}.php").write_text(content)


def generate_hook_workloads() -> None:
    for file_idx in range(2):
        methods: list[str] = []
        for method_idx in range(10):
            root_types = ["scalar", "boolean", "integer", "float", "enum"]
            root_type = root_types[(file_idx * 10 + method_idx) % len(root_types)]
            extra_method = {
                "scalar": "cannotBeEmpty()",
                "boolean": "defaultTrue()",
                "integer": "defaultValue(1)",
                "float": "defaultValue(1.5)",
                "enum": "defaultValue('a')",
            }[root_type]

            filler_before = "\n".join(
                f"        $pre{i} = $service->handle00('pre_{i}');"
                for i in range(12)
            )
            filler_after = "\n".join(
                f"        $post{i} = $service->handle00('post_{i}');"
                for i in range(12)
            )

            methods.append(f"""    public function hookScenario{method_idx}(BenchService00 $service): TreeBuilder
    {{
{filler_before}
        $tree = new TreeBuilder('hook_{file_idx}_{method_idx}', '{root_type}');
        $nested = static function (): TreeBuilder {{
            $tree = new TreeBuilder('inner_{file_idx}_{method_idx}');
            $tree->getRootNode()->children()->scalarNode('x')->end()->end();
            return $tree;
        }};
        $nested();
        $root = $tree->getRootNode();
        $shadowed = static function (ArrayNodeDefinition $root): void {{
            $root->children()->scalarNode('y')->end()->end();
        }};
        $shadowed(new ArrayNodeDefinition('shadow_{method_idx}'));
{filler_after}
        $root->isRequired()->{extra_method}->info($pre0 . $post0)->end();

        (new TreeBuilder('inline_{file_idx}_{method_idx}', '{root_type}'))
            ->getRootNode()
            ->isRequired()
            ->{extra_method}
            ->end();

        $arrayTree = new TreeBuilder('array_{file_idx}_{method_idx}');
        $arrayTree->getRootNode()->children()->scalarNode('k')->defaultValue($pre11 . $post11)->end()->end();

        return $arrayTree;
    }}
""")

        content = "\n".join([
            "<?php",
            "",
            "declare(strict_types=1);",
            "",
            "namespace SymfonyBench;",
            "",
            "use Symfony\\Component\\Config\\Definition\\Builder\\ArrayNodeDefinition;",
            "use Symfony\\Component\\Config\\Definition\\Builder\\TreeBuilder;",
            "",
            f"final class TreeBuilderHookWorkload{file_idx}",
            "{",
            "\n".join(methods).rstrip(),
            "}",
            "",
        ])
        (FIXTURES_DIR / f"TreeBuilderHookWorkload{file_idx}.php").write_text(content)


def main() -> None:
    FIXTURES_DIR.mkdir(parents=True, exist_ok=True)
    generate_support()
    generate_container_map()
    generate_container_workloads()
    generate_config_workloads()
    generate_hook_workloads()


if __name__ == "__main__":
    main()

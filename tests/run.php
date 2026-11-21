<?php

declare(strict_types=1);

chdir(dirname(__DIR__));

require __DIR__ . '/../vendor/autoload.php';
$isSymfony7 = class_exists(\Symfony\Component\Config\Definition\Builder\StringNodeDefinition::class);

putenv('MAGO_SYMFONY_CONTAINER_MAP=' . __DIR__ . '/container-map.json');

$failures = 0;

$version = trim(run([PHP_BINARY, 'vendor/bin/mago', '--version'])['stdout']);
fwrite(STDOUT, $version . "\n\n");
if ($version !== 'mago 1.60.2') {
    fwrite(STDERR, "The fixture expectations require Mago 1.60.2\n");
    exit(1);
}

$validated = run([PHP_BINARY, 'vendor/bin/mago', 'extension', 'validate']);
fwrite(STDOUT, "extension validate (exit {$validated['exit']}):\n");
fwrite(STDOUT, trim($validated['stdout'] . $validated['stderr']) . "\n\n");
if ($validated['exit'] !== 0) {
    fwrite(STDERR, "extension validate failed\n");
    exit(1);
}

$runtime = run([PHP_BINARY, 'tests/runtime.php']);
fwrite(STDOUT, $runtime['stdout'] . $runtime['stderr']);
if ($runtime['exit'] !== 0) {
    exit(1);
}

$with = analyze(false);
$without = analyze(true);

fwrite(STDOUT, "with extension (" . count($with) . " issues):\n");
printIssues($with);
fwrite(STDOUT, "\n--no-extensions (" . count($without) . " issues):\n");
printIssues($without);
fwrite(STDOUT, "\n");

// The extension must resolve the fluent Config chains and container lookups,
// and keep the real errors.
$expected = [
    false => [
        'tests/fixtures/TreeBuilderScopes.php' => [],
        'tests/fixtures/TreeBuilderScopesNegative.php' => [
            'alfredbez/symfony/invalid-root-node-method' => 2,
        ],
        'tests/fixtures/ConfigNodes.php' => [],
        'tests/fixtures/ConfigNodesNegative.php' => $isSymfony7
            ? [
                'method-access-on-null' => 2,
                'non-existent-method' => 8,
            ]
            : [
                'non-existent-method' => 8,
                'possible-method-access-on-null' => 2,
            ],
        'tests/fixtures/Configuration.php' => [],
        'tests/fixtures/ConfigurationNegative.php' => $isSymfony7
            ? [
                'alfredbez/symfony/invalid-root-node-method' => 2,
                'method-access-on-null' => 1,
                'non-existent-method' => 3,
            ]
            : [
                'alfredbez/symfony/invalid-root-node-method' => 2,
                'non-existent-method' => 7,
                'possible-method-access-on-null' => 1,
            ],
        'tests/fixtures/CustomNodeBuilderConfig.php' => [],
        'tests/fixtures/CustomNodeBuilderConfigNegative.php' => $isSymfony7
            ? [
                'method-access-on-null' => 1,
                'non-existent-method' => 5,
            ]
            : [
                'non-existent-method' => 5,
                'possible-method-access-on-null' => 1,
            ],
        'tests/fixtures/ContainerUsage.php' => [],
        'tests/fixtures/ContainerFlags.php' => [],
        'tests/fixtures/ContainerFlagsNegative.php' => [
            'non-existent-method' => 1,
            'possible-method-access-on-null' => 8,
        ],
        'tests/fixtures/ContainerMap.php' => [],
        'tests/fixtures/ContainerMapNegative.php' => [
            'mixed-method-access' => 2,
            'non-existent-method' => 4,
        ],
        // unknown service id stays mixed, unknown method on a service still reports,
        // nullable invalidBehavior flags preserve possible-method-access-on-null
        'tests/fixtures/ContainerNegative.php' => [
            'mixed-method-access' => 1,
            'non-existent-method' => 1,
            'possible-method-access-on-null' => 4,
        ],
    ],
    true => [
        'tests/fixtures/TreeBuilderScopes.php' => [
            'non-existent-method' => $isSymfony7 ? 8 : 1,
        ],
        'tests/fixtures/TreeBuilderScopesNegative.php' => $isSymfony7
            ? [
                'invalid-return-statement' => 1,
                'non-existent-method' => 16,
            ]
            : [
                'non-existent-method' => 2,
            ],
        'tests/fixtures/ConfigNodes.php' => $isSymfony7
            ? [
                'non-existent-method' => 6,
            ]
            : [
                'mixed-method-access' => 23,
                'mixed-return-statement' => 2,
                'non-existent-method' => 34,
                'possible-method-access-on-null' => 8,
            ],
        'tests/fixtures/ConfigNodesNegative.php' => $isSymfony7
            ? [
                'method-access-on-null' => 1,
                'non-existent-method' => 8,
            ]
            : [
                'non-existent-method' => 16,
                'possible-method-access-on-null' => 2,
            ],
        // TreeBuilder::getRootNode() returns a union without the extension,
        // and NodeParentInterface is empty on Symfony 6.4.
        'tests/fixtures/Configuration.php' => $isSymfony7
            ? [
                'invalid-return-statement' => 1,
                'non-existent-method' => 16,
            ]
            : [
                'less-specific-return-statement' => 1,
                'mixed-method-access' => 2,
                'non-existent-method' => 26,
                'possible-method-access-on-null' => 9,
            ],
        'tests/fixtures/ConfigurationNegative.php' => $isSymfony7
            ? [
                'method-access-on-null' => 1,
                'non-existent-method' => 27,
            ]
            : [
                'non-existent-method' => 21,
                'possible-method-access-on-null' => 6,
            ],
        'tests/fixtures/CustomNodeBuilderConfig.php' => $isSymfony7
            ? [
                'mixed-method-access' => 31,
                'mixed-return-statement' => 2,
                'non-existent-method' => 9,
            ]
            : [
                'mixed-method-access' => 31,
                'mixed-return-statement' => 2,
                'non-existent-method' => 10,
                'possible-method-access-on-null' => 2,
            ],
        'tests/fixtures/CustomNodeBuilderConfigNegative.php' => $isSymfony7
            ? [
                'non-existent-method' => 12,
            ]
            : [
                'non-existent-method' => 15,
                'possible-method-access-on-null' => 3,
            ],
        'tests/fixtures/ContainerFlags.php' => [
            'ambiguous-object-method-access' => 8,
            'possible-method-access-on-null' => 3,
        ],
        'tests/fixtures/ContainerFlagsNegative.php' => [
            'ambiguous-object-method-access' => 9,
            'possible-method-access-on-null' => 8,
        ],
        'tests/fixtures/ContainerMap.php' => [
            'mixed-method-access' => 4,
            'mixed-return-statement' => 1,
        ],
        'tests/fixtures/ContainerMapNegative.php' => [
            'mixed-method-access' => 6,
        ],
        // ContainerInterface::get() is mixed or ?object without the extension.
        'tests/fixtures/ContainerUsage.php' => [
            'ambiguous-object-method-access' => 6,
            'mixed-assignment' => 2,
            'mixed-method-access' => 5,
            'mixed-return-statement' => 1,
            'possible-method-access-on-null' => 2,
            'redundant-nullsafe-operator' => 1,
        ],
        'tests/fixtures/ContainerNegative.php' => [
            'ambiguous-object-method-access' => 4,
            'mixed-method-access' => 2,
            'possible-method-access-on-null' => 4,
        ],
    ],
];

foreach ([false => 'with extension', true => '--no-extensions'] as $disabled => $label) {
    $failures += assertHistograms($label, $disabled ? $without : $with, $expected[$disabled]);
}

$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/ContainerNegative.php',
    'non-existent-method',
    'missingMethod',
    1,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/Configuration.php',
    'non-existent-method',
    'end',
    0,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/ConfigurationNegative.php',
    'alfredbez/symfony/invalid-root-node-method',
    'ScalarNodeDefinition',
    2,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/ConfigurationNegative.php',
    'non-existent-method',
    'TreeBuilder',
    1,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/ConfigurationNegative.php',
    'non-existent-method',
    'ScalarNodeDefinition',
    1,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/ConfigurationNegative.php',
    'non-existent-method',
    'missingOnArrayNode',
    1,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/CustomNodeBuilderConfigNegative.php',
    'non-existent-method',
    'CustomNodeDefinition',
    1,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/CustomNodeBuilderConfigNegative.php',
    'non-existent-method',
    'CustomScalarNodeDefinition',
    1,
);

$sites = [
    false => [
        'tests/fixtures/TreeBuilderScopes.php' => [],
        'tests/fixtures/TreeBuilderScopesNegative.php' => [
            '19:alfredbez/symfony/invalid-root-node-method',
            '31:alfredbez/symfony/invalid-root-node-method',
        ],
        'tests/fixtures/ConfigNodes.php' => [],
        'tests/fixtures/ConfigNodesNegative.php' => [
            $isSymfony7 ? '13:method-access-on-null' : '13:possible-method-access-on-null',
            $isSymfony7 ? '14:method-access-on-null' : '14:possible-method-access-on-null',
            '20:non-existent-method',
            '21:non-existent-method',
            '22:non-existent-method',
            '23:non-existent-method',
            '24:non-existent-method',
            '25:non-existent-method',
            '26:non-existent-method',
            '27:non-existent-method',
        ],
        'tests/fixtures/ContainerFlags.php' => [],
        'tests/fixtures/ContainerFlagsNegative.php' => [
            '14:possible-method-access-on-null',
            '15:possible-method-access-on-null',
            '16:possible-method-access-on-null',
            '21:possible-method-access-on-null',
            '22:possible-method-access-on-null',
            '23:possible-method-access-on-null',
            '24:possible-method-access-on-null',
            '25:possible-method-access-on-null',
            '30:non-existent-method',
        ],
        'tests/fixtures/ContainerMap.php' => [],
        'tests/fixtures/ContainerMapNegative.php' => [
            '14:non-existent-method', '15:non-existent-method', '20:mixed-method-access',
            '25:non-existent-method', '26:non-existent-method', '27:mixed-method-access',
        ],
    ],
    true => [
        'tests/fixtures/TreeBuilderScopes.php' => $isSymfony7
            ? array_fill(0, 8, '19:non-existent-method')
            : [
                '19:non-existent-method',
            ],
        'tests/fixtures/TreeBuilderScopesNegative.php' => $isSymfony7
            ? [
                '16:invalid-return-statement',
                ...array_fill(0, 8, '19:non-existent-method'),
                ...array_fill(0, 8, '31:non-existent-method'),
            ]
            : [
                '19:non-existent-method',
                '31:non-existent-method',
            ],
        'tests/fixtures/ConfigNodes.php' => $isSymfony7
            ? array_fill(0, 6, '20:non-existent-method')
            : [
                '14:non-existent-method',
                '14:non-existent-method',
                '12:possible-method-access-on-null',
                '14:non-existent-method',
                '14:non-existent-method',
                '15:non-existent-method',
                '15:non-existent-method',
                '12:possible-method-access-on-null',
                '15:non-existent-method',
                '15:non-existent-method',
                '16:non-existent-method',
                '16:non-existent-method',
                '12:possible-method-access-on-null',
                '16:non-existent-method',
                '16:non-existent-method',
                '17:non-existent-method',
                '17:non-existent-method',
                '12:possible-method-access-on-null',
                '17:non-existent-method',
                '17:non-existent-method',
                '18:non-existent-method',
                '18:non-existent-method',
                '12:possible-method-access-on-null',
                '18:non-existent-method',
                '18:non-existent-method',
                '19:non-existent-method',
                '19:non-existent-method',
                '12:possible-method-access-on-null',
                '19:non-existent-method',
                '19:non-existent-method',
                '19:non-existent-method',
                '20:mixed-method-access',
                '20:mixed-method-access',
                '20:mixed-method-access',
                '21:mixed-method-access',
                '21:mixed-method-access',
                '21:mixed-method-access',
                '22:mixed-method-access',
                '12:mixed-return-statement',
                '29:non-existent-method',
                '29:non-existent-method',
                '27:possible-method-access-on-null',
                '29:non-existent-method',
                '29:non-existent-method',
                '30:non-existent-method',
                '30:non-existent-method',
                '27:possible-method-access-on-null',
                '30:non-existent-method',
                '30:non-existent-method',
                '30:non-existent-method',
                '30:mixed-method-access',
                '31:mixed-method-access',
                '31:mixed-method-access',
                '31:mixed-method-access',
                '32:mixed-method-access',
                '32:mixed-method-access',
                '32:mixed-method-access',
                '33:mixed-method-access',
                '33:mixed-method-access',
                '33:mixed-method-access',
                '34:mixed-method-access',
                '34:mixed-method-access',
                '34:mixed-method-access',
                '34:mixed-method-access',
                '34:mixed-method-access',
                '34:mixed-method-access',
                '27:mixed-return-statement',
            ],
        'tests/fixtures/ConfigNodesNegative.php' => $isSymfony7
            ? [
                '13:method-access-on-null',
                '20:non-existent-method',
                '21:non-existent-method',
                '22:non-existent-method',
                '23:non-existent-method',
                '24:non-existent-method',
                '25:non-existent-method',
                '26:non-existent-method',
                '27:non-existent-method',
            ]
            : [
                '14:non-existent-method',
                '14:non-existent-method',
                '14:possible-method-access-on-null',
                '14:non-existent-method',
                '14:non-existent-method',
                '20:non-existent-method',
                '21:non-existent-method',
                '22:non-existent-method',
                '23:non-existent-method',
                '24:non-existent-method',
                '25:non-existent-method',
                '26:non-existent-method',
                '27:non-existent-method',
                '27:non-existent-method',
                '27:possible-method-access-on-null',
                '27:non-existent-method',
                '27:non-existent-method',
                '27:non-existent-method',
            ],
        'tests/fixtures/ContainerFlags.php' => [
            '14:ambiguous-object-method-access',
            '14:possible-method-access-on-null',
            '15:ambiguous-object-method-access',
            '21:ambiguous-object-method-access',
            '21:possible-method-access-on-null',
            '22:ambiguous-object-method-access',
            '22:possible-method-access-on-null',
            '27:ambiguous-object-method-access',
            '28:ambiguous-object-method-access',
            '29:ambiguous-object-method-access',
            '30:ambiguous-object-method-access',
        ],
        'tests/fixtures/ContainerFlagsNegative.php' => [
            '14:ambiguous-object-method-access',
            '14:possible-method-access-on-null',
            '15:ambiguous-object-method-access',
            '16:ambiguous-object-method-access',
            '16:possible-method-access-on-null',
            '21:ambiguous-object-method-access',
            '21:possible-method-access-on-null',
            '22:ambiguous-object-method-access',
            '22:possible-method-access-on-null',
            '23:ambiguous-object-method-access',
            '23:possible-method-access-on-null',
            '24:ambiguous-object-method-access',
            '24:possible-method-access-on-null',
            '25:ambiguous-object-method-access',
            '25:possible-method-access-on-null',
            '30:ambiguous-object-method-access',
            '30:possible-method-access-on-null',
        ],
        'tests/fixtures/ContainerMap.php' => [
            '14:mixed-method-access', '14:mixed-return-statement', '19:mixed-method-access',
            '24:mixed-method-access', '25:mixed-method-access',
        ],
        'tests/fixtures/ContainerMapNegative.php' => [
            '14:mixed-method-access', '15:mixed-method-access', '20:mixed-method-access',
            '25:mixed-method-access', '26:mixed-method-access', '27:mixed-method-access',
        ],
    ],
];
foreach ([false => $with, true => $without] as $disabled => $issues) {
    $failures += assertSites($disabled ? '--no-extensions' : 'with extension', $issues, $sites[$disabled]);
}

putenv('MAGO_SYMFONY_CONTAINER_MAP');
putenv('MAGO_SYMFONY_CONTAINER_XML=' . __DIR__ . '/container.xml');
$xmlWith = analyze(false, 'tests/mago-xml.toml');
$xmlWithout = analyze(true, 'tests/mago-xml.toml');

$xmlExpected = [
    false => [
        'tests/fixtures-xml/ContainerXmlUsage.php' => [],
        'tests/fixtures-xml/SymfonyComponents.php' => [],
        'tests/fixtures-xml/ContainerXmlNegative.php' => [
            'invalid-argument' => 2,
            'mixed-method-access' => 1,
            'non-existent-method' => 3,
            'null-argument' => 1,
            'possibly-invalid-argument' => 1,
            'possibly-null-argument' => 1,
        ],
        'tests/fixtures-xml/ConsoleCommandNegative.php' => [
            'alfredbez/symfony/unknown-console-argument' => 2,
            'alfredbez/symfony/unknown-console-option' => 2,
        ],
        'tests/fixtures-xml/Wave4ContractsNegative.php' => [
            'invalid-return-statement' => 1,
            'missing-constructor' => 1,
            'non-existent-method' => 3,
            'nullable-return-statement' => 1,
        ],
    ],
    true => [
        'tests/fixtures-xml/ContainerXmlUsage.php' => [
            'ambiguous-object-method-access' => 2,
            'invalid-array-access' => 5,
            'less-specific-return-statement' => 1,
            'missing-constructor' => $isSymfony7 ? 1 : 0,
            'mixed-argument' => 4,
            'mixed-array-access' => 2,
            'mixed-assignment' => 1,
            'mixed-method-access' => 5,
            'mixed-operand' => 12,
            'mixed-return-statement' => 1,
            'possible-method-access-on-null' => 1,
            'possibly-invalid-argument' => 7,
            'possibly-invalid-array-access' => 5,
            'possibly-null-argument' => 6,
            'possibly-null-array-access' => 5,
            'possibly-null-operand' => 3,
        ],
        'tests/fixtures-xml/SymfonyComponents.php' => [
            'invalid-iterator' => 3,
            'invalid-return-statement' => 1,
            'missing-constructor' => $isSymfony7 ? 2 : 1,
            'mixed-assignment' => 7,
            'mixed-property-access' => 4,
            'non-existent-method' => 4,
            'nullable-return-statement' => 1,
        ],
        'tests/fixtures-xml/ContainerXmlNegative.php' => [
            'missing-constructor' => $isSymfony7 ? 1 : 0,
            'mixed-method-access' => 4,
            'possibly-invalid-argument' => 4,
            'possibly-null-argument' => 4,
        ],
        'tests/fixtures-xml/ConsoleCommandNegative.php' => [],
        'tests/fixtures-xml/Wave4ContractsNegative.php' => [
            'invalid-iterator' => 1,
            'invalid-return-statement' => 1,
            'missing-constructor' => 1,
            'mixed-assignment' => 3,
            'mixed-method-access' => 2,
            'non-existent-method' => 1,
            'nullable-return-statement' => 1,
        ],
    ],
];
foreach ([false => 'xml with extension', true => 'xml --no-extensions'] as $disabled => $label) {
    $failures += assertHistograms($label, $disabled ? $xmlWithout : $xmlWith, $xmlExpected[$disabled]);
}
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-argument', 'user_name', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-option', 'dryrun', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-argument', 'missing_target', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-option', 'missing_force', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/Wave4ContractsNegative.php', 'non-existent-method', 'FormErrorIterator', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/Wave4ContractsNegative.php', 'non-existent-method', 'nonExistentMethodOnDomainEvent', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/Wave4ContractsNegative.php', 'non-existent-method', 'anotherMissingMethod', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/Wave4ContractsNegative.php', 'missing-constructor', 'UninitializedServiceNegative', 1);

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} assertion(s) failed\n");
    exit(1);
}

fwrite(STDOUT, "PASS\n");
exit(0);

/**
 * @param list<string> $command
 * @return array{exit: int, stdout: string, stderr: string}
 */
function run(array $command): array
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if ($process === false) {
        fwrite(STDERR, 'failed to start ' . implode(' ', $command) . "\n");
        exit(1);
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [
        'exit' => proc_close($process),
        'stdout' => $stdout === false ? '' : $stdout,
        'stderr' => $stderr === false ? '' : $stderr,
    ];
}

/** @return list<array{level: string, code: string, file: string, line: int, message: string}> */
function analyze(bool $noExtensions, ?string $config = null): array
{
    $command = [PHP_BINARY, 'vendor/bin/mago'];
    if ($config !== null) {
        $command[] = '--config';
        $command[] = $config;
    }
    $command[] = 'analyze';
    $command[] = '--reporting-format=json';
    if ($noExtensions) {
        $command[] = '--no-extensions';
    }

    $result = run($command);
    if (trim($result['stderr']) !== '') {
        fwrite(STDERR, ($noExtensions ? '--no-extensions' : 'with extension') . " stderr:\n" . $result['stderr'] . "\n");
    }

    try {
        $decoded = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        fwrite(STDERR, "mago analyze did not return JSON (exit {$result['exit']}): {$exception->getMessage()}\n");
        fwrite(STDERR, $result['stdout'] . "\n");
        exit(1);
    }

    if (!is_array($decoded) || !isset($decoded['issues']) || !is_array($decoded['issues'])) {
        fwrite(STDERR, "mago analyze JSON has no issues array\n");
        exit(1);
    }

    $issues = [];
    foreach ($decoded['issues'] as $issue) {
        $primary = $issue['annotations'][0];
        foreach ($issue['annotations'] as $annotation) {
            if (($annotation['kind'] ?? '') === 'Primary') {
                $primary = $annotation;
                break;
            }
        }

        $issues[] = [
            'level' => (string) $issue['level'],
            'code' => (string) $issue['code'],
            'file' => (string) $primary['span']['file_id']['name'],
            'line' => (int) $primary['span']['start']['line'],
            'message' => (string) $issue['message'],
        ];
    }

    $hasErrors = count(array_filter($issues, static fn (array $issue): bool => $issue['level'] === 'Error')) > 0;
    if ($result['exit'] !== ($hasErrors ? 1 : 0)) {
        fwrite(STDERR, "Unexpected analyzer exit {$result['exit']} for its reported issues\n");
        exit(1);
    }

    return $issues;
}

/** @param list<array{level: string, code: string, file: string, line: int, message: string}> $issues */
function printIssues(array $issues): void
{
    if ($issues === []) {
        fwrite(STDOUT, "  (none)\n");

        return;
    }

    foreach ($issues as $issue) {
        fwrite(STDOUT, sprintf(
            "  %s\t%s\t%s:%d\t%s\n",
            $issue['level'],
            $issue['code'],
            $issue['file'],
            $issue['line'],
            $issue['message'],
        ));
    }
}

/**
 * @param list<array{level: string, code: string, file: string, line: int, message: string}> $issues
 * @param array<string, array<string, int>> $expected
 */
function assertHistograms(string $label, array $issues, array $expected): int
{
    $actual = [];
    foreach ($issues as $issue) {
        $actual[$issue['file']][$issue['code']] = ($actual[$issue['file']][$issue['code']] ?? 0) + 1;
    }

    $failures = 0;
    $files = array_unique([...array_keys($expected), ...array_keys($actual)]);
    sort($files);
    foreach ($files as $file) {
        $expectedCounts = array_filter(
            $expected[$file] ?? [],
            static fn (int $count): bool => $count !== 0,
        );
        $actualCounts = $actual[$file] ?? [];
        ksort($expectedCounts);
        ksort($actualCounts);
        if ($expectedCounts === $actualCounts) {
            fwrite(STDOUT, "ok  {$label}  {$file}  " . formatCounts($actualCounts) . "\n");
            continue;
        }

        $failures++;
        fwrite(STDERR, "FAIL {$label}  {$file}\n");
        fwrite(STDERR, '  expected ' . formatCounts($expectedCounts) . "\n");
        fwrite(STDERR, '  actual   ' . formatCounts($actualCounts) . "\n");
    }

    return $failures;
}

/**
 * @param list<array{level: string, code: string, file: string, line: int, message: string}> $issues
 */
function assertMessageCount(string $label, array $issues, string $file, string $code, string $needle, int $expected): int
{
    $actual = 0;
    foreach ($issues as $issue) {
        if ($issue['file'] === $file && $issue['code'] === $code && str_contains($issue['message'], $needle)) {
            $actual++;
        }
    }

    if ($actual === $expected) {
        fwrite(STDOUT, "ok  {$label}  {$file}  {$code} containing \"{$needle}\" = {$actual}\n");

        return 0;
    }

    fwrite(STDERR, "FAIL {$label}  {$file}  {$code} containing \"{$needle}\"\n");
    fwrite(STDERR, "  expected {$expected}, actual {$actual}\n");

    return 1;
}

/** @param array<string, int> $counts */
function formatCounts(array $counts): string
{
    if ($counts === []) {
        return '(none)';
    }

    $parts = [];
    foreach ($counts as $code => $count) {
        $parts[] = "{$code}={$count}";
    }

    return implode(', ', $parts);
}

/**
 * @param list<array{level: string, code: string, file: string, line: int, message: string}> $issues
 * @param array<string, list<string>> $expected
 */
function assertSites(string $label, array $issues, array $expected): int
{
    $failures = 0;
    foreach ($expected as $file => $sites) {
        $actual = [];
        foreach ($issues as $issue) {
            if ($issue['file'] === $file) {
                $actual[] = $issue['line'] . ':' . $issue['code'];
            }
        }
        sort($actual);
        sort($sites);
        if ($actual !== $sites) {
            fwrite(STDERR, "FAIL {$label} {$file} diagnostic sites\n");
            fwrite(STDERR, '  expected ' . json_encode($sites) . "\n  actual   " . json_encode($actual) . "\n");
            $failures++;
        }
    }

    return $failures;
}

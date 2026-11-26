<?php

declare(strict_types=1);

chdir(dirname(__DIR__));

require __DIR__ . '/../vendor/autoload.php';
$isSymfony7 = class_exists(\Symfony\Component\Config\Definition\Builder\StringNodeDefinition::class);
$arrayNodeHasTemplates = str_contains(
    (string) (new ReflectionClass(\Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition::class))->getDocComment(),
    '@template',
);
$serializerHasTemplates = str_contains(
    (string) (new ReflectionMethod(\Symfony\Component\Serializer\SerializerInterface::class, 'deserialize'))->getDocComment(),
    '@template',
);
$denormalizerHasTemplates = str_contains(
    (string) (new ReflectionMethod(\Symfony\Component\Serializer\Normalizer\DenormalizerInterface::class, 'denormalize'))->getDocComment(),
    '@template',
);

putenv('MAGO_SYMFONY_CONTAINER_MAP=' . __DIR__ . '/container-map.json');

$failures = 0;

$magoBinEnv = getenv('MAGO_BIN');
$magoCommand = ($magoBinEnv !== false && $magoBinEnv !== '')
    ? [$magoBinEnv]
    : [PHP_BINARY, 'vendor/bin/mago'];

$version = trim(run([...$magoCommand, '--version'])['stdout']);
fwrite(STDOUT, $version . "\n\n");
if (preg_match('/^mago (\d+\.\d+\.\d+)/', $version, $versionMatch) !== 1 || version_compare($versionMatch[1], '1.60.2', '<')) {
    fwrite(STDERR, "The fixture expectations require Mago 1.60.2 or newer\n");
    exit(1);
}
$magoVersion = $versionMatch[1];
// Mago 1.61.0 evaluates Symfony ContainerInterface::get()'s @template B constraint and nested conditional @return.
$magoAtLeast161 = version_compare($magoVersion, '1.61.0', '>=');

$validated = run([...$magoCommand, 'extension', 'validate']);
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
            'non-existent-method' => 2,
        ],
        'tests/fixtures/TreeBuilderReturnPaths.php' => [],
        'tests/fixtures/TreeBuilderRootNodeReturn.php' => [],
        'tests/fixtures/TreeBuilderReturnPathsNegative.php' => $isSymfony7
            ? [
                'invalid-return-statement' => 14,
                'possibly-invalid-argument' => 1,
            ]
            : [],
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
                'method-access-on-null' => 1,
                'non-existent-method' => 5,
            ]
            : [
                'non-existent-method' => 9,
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
        'tests/fixtures/ContainerFlagsNegative.php' => $magoAtLeast161
            ? [
                'non-existent-method' => 1,
                'possible-method-access-on-null' => 8,
                'possibly-invalid-argument' => 1,
                'template-constraint-violation' => 1,
            ]
            : [
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
            'non-existent-method' => $isSymfony7 ? 8 : 2,
        ],
        'tests/fixtures/TreeBuilderScopesNegative.php' => $isSymfony7
            ? [
                'invalid-return-statement' => 1,
                'non-existent-method' => 16,
            ]
            : [
                'non-existent-method' => 2,
            ],
        'tests/fixtures/TreeBuilderReturnPaths.php' => $isSymfony7
            ? [
                'invalid-return-statement' => 10,
                'non-existent-method' => 16,
            ]
            : [
                'non-existent-method' => 2,
            ],
        'tests/fixtures/TreeBuilderRootNodeReturn.php' => $isSymfony7
            ? [
                'invalid-return-statement' => 1,
                'less-specific-return-statement' => 3,
                'non-existent-method' => 32,
            ]
            : [
                'less-specific-return-statement' => 3,
                'non-existent-method' => 4,
            ],
        'tests/fixtures/TreeBuilderReturnPathsNegative.php' => $isSymfony7
            ? [
                'invalid-return-statement' => 15,
                'possibly-invalid-argument' => 1,
            ]
            : [],
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
                'non-existent-method' => 31,
            ]
            : [
                'less-specific-return-statement' => 1,
                'mixed-method-access' => 5,
                'non-existent-method' => 30,
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
        'tests/fixtures/ContainerFlags.php' => $magoAtLeast161
            ? [
                'ambiguous-object-method-access' => 8,
                'redundant-nullsafe-operator' => 1,
            ]
            : [
                'ambiguous-object-method-access' => 8,
                'possible-method-access-on-null' => 3,
            ],
        'tests/fixtures/ContainerFlagsNegative.php' => $magoAtLeast161
            ? [
                'ambiguous-object-method-access' => 9,
                'possible-method-access-on-null' => 5,
                'possibly-invalid-argument' => 1,
                'template-constraint-violation' => 1,
            ]
            : [
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
        'tests/fixtures/ContainerUsage.php' => $magoAtLeast161
            ? [
                'ambiguous-object-method-access' => 6,
                'mixed-assignment' => 2,
                'mixed-method-access' => 5,
                'mixed-return-statement' => 1,
                'redundant-nullsafe-operator' => 1,
            ]
            : [
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
    3,
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
            '20:non-existent-method',
            '32:non-existent-method',
        ],
        'tests/fixtures/TreeBuilderReturnPaths.php' => [],
        'tests/fixtures/TreeBuilderRootNodeReturn.php' => [],
        'tests/fixtures/TreeBuilderReturnPathsNegative.php' => $isSymfony7
            ? [
                '21:invalid-return-statement',
                '36:invalid-return-statement',
                '52:invalid-return-statement',
                '65:invalid-return-statement',
                '75:invalid-return-statement',
                '96:invalid-return-statement',
                '115:invalid-return-statement',
                '133:invalid-return-statement',
                '151:invalid-return-statement',
                '171:invalid-return-statement',
                '189:invalid-return-statement',
                '200:possibly-invalid-argument',
                '202:invalid-return-statement',
                '214:invalid-return-statement',
                '234:invalid-return-statement',
            ]
            : [],
        'tests/fixtures/ConfigNodes.php' => [],
        'tests/fixtures/ConfigNodesNegative.php' => [
            $isSymfony7 ? '14:method-access-on-null' : '14:possible-method-access-on-null',
            $isSymfony7 ? '15:method-access-on-null' : '15:possible-method-access-on-null',
            '21:non-existent-method',
            '22:non-existent-method',
            '23:non-existent-method',
            '24:non-existent-method',
            '25:non-existent-method',
            '26:non-existent-method',
            '27:non-existent-method',
            '28:non-existent-method',
        ],
        'tests/fixtures/ContainerFlags.php' => [],
        'tests/fixtures/ContainerFlagsNegative.php' => [
            '15:possible-method-access-on-null',
            '16:possible-method-access-on-null',
            '17:possible-method-access-on-null',
            '22:possible-method-access-on-null',
            '23:possible-method-access-on-null',
            '24:possible-method-access-on-null',
            '25:possible-method-access-on-null',
            '26:possible-method-access-on-null',
            ...($magoAtLeast161 ? ['26:possibly-invalid-argument', '26:template-constraint-violation'] : []),
            '31:non-existent-method',
        ],
        'tests/fixtures/ContainerMap.php' => [],
        'tests/fixtures/ContainerMapNegative.php' => [
            '15:non-existent-method', '16:non-existent-method', '21:mixed-method-access',
            '26:non-existent-method', '27:non-existent-method', '28:mixed-method-access',
        ],
    ],
    true => [
        'tests/fixtures/TreeBuilderScopes.php' => $isSymfony7
            ? array_fill(0, 8, '20:non-existent-method')
            : [
                '20:non-existent-method',
                '40:non-existent-method',
            ],
        'tests/fixtures/TreeBuilderScopesNegative.php' => $isSymfony7
            ? [
                '17:invalid-return-statement',
                ...array_fill(0, 8, '20:non-existent-method'),
                ...array_fill(0, 8, '32:non-existent-method'),
            ]
            : [
                '20:non-existent-method',
                '32:non-existent-method',
            ],
        'tests/fixtures/ConfigNodes.php' => $isSymfony7
            ? array_fill(0, 6, '21:non-existent-method')
            : [
                '15:non-existent-method',
                '15:non-existent-method',
                '13:possible-method-access-on-null',
                '15:non-existent-method',
                '15:non-existent-method',
                '16:non-existent-method',
                '16:non-existent-method',
                '13:possible-method-access-on-null',
                '16:non-existent-method',
                '16:non-existent-method',
                '17:non-existent-method',
                '17:non-existent-method',
                '13:possible-method-access-on-null',
                '17:non-existent-method',
                '17:non-existent-method',
                '18:non-existent-method',
                '18:non-existent-method',
                '13:possible-method-access-on-null',
                '18:non-existent-method',
                '18:non-existent-method',
                '19:non-existent-method',
                '19:non-existent-method',
                '13:possible-method-access-on-null',
                '19:non-existent-method',
                '19:non-existent-method',
                '20:non-existent-method',
                '20:non-existent-method',
                '13:possible-method-access-on-null',
                '20:non-existent-method',
                '20:non-existent-method',
                '20:non-existent-method',
                '21:mixed-method-access',
                '21:mixed-method-access',
                '21:mixed-method-access',
                '22:mixed-method-access',
                '22:mixed-method-access',
                '22:mixed-method-access',
                '23:mixed-method-access',
                '13:mixed-return-statement',
                '30:non-existent-method',
                '30:non-existent-method',
                '28:possible-method-access-on-null',
                '30:non-existent-method',
                '30:non-existent-method',
                '31:non-existent-method',
                '31:non-existent-method',
                '28:possible-method-access-on-null',
                '31:non-existent-method',
                '31:non-existent-method',
                '31:non-existent-method',
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
                '35:mixed-method-access',
                '35:mixed-method-access',
                '35:mixed-method-access',
                '35:mixed-method-access',
                '35:mixed-method-access',
                '35:mixed-method-access',
                '28:mixed-return-statement',
            ],
        'tests/fixtures/ConfigNodesNegative.php' => $isSymfony7
            ? [
                '14:method-access-on-null',
                '21:non-existent-method',
                '22:non-existent-method',
                '23:non-existent-method',
                '24:non-existent-method',
                '25:non-existent-method',
                '26:non-existent-method',
                '27:non-existent-method',
                '28:non-existent-method',
            ]
            : [
                '15:non-existent-method',
                '15:non-existent-method',
                '15:possible-method-access-on-null',
                '15:non-existent-method',
                '15:non-existent-method',
                '21:non-existent-method',
                '22:non-existent-method',
                '23:non-existent-method',
                '24:non-existent-method',
                '25:non-existent-method',
                '26:non-existent-method',
                '27:non-existent-method',
                '28:non-existent-method',
                '28:non-existent-method',
                '28:possible-method-access-on-null',
                '28:non-existent-method',
                '28:non-existent-method',
                '28:non-existent-method',
            ],
        'tests/fixtures/ContainerFlags.php' => $magoAtLeast161
            ? [
                '15:ambiguous-object-method-access',
                '16:ambiguous-object-method-access',
                '22:ambiguous-object-method-access',
                '23:ambiguous-object-method-access',
                '28:ambiguous-object-method-access',
                '28:redundant-nullsafe-operator',
                '29:ambiguous-object-method-access',
                '30:ambiguous-object-method-access',
                '31:ambiguous-object-method-access',
            ]
            : [
                '15:ambiguous-object-method-access',
                '15:possible-method-access-on-null',
                '16:ambiguous-object-method-access',
                '22:ambiguous-object-method-access',
                '22:possible-method-access-on-null',
                '23:ambiguous-object-method-access',
                '23:possible-method-access-on-null',
                '28:ambiguous-object-method-access',
                '29:ambiguous-object-method-access',
                '30:ambiguous-object-method-access',
                '31:ambiguous-object-method-access',
            ],
        'tests/fixtures/ContainerFlagsNegative.php' => $magoAtLeast161
            ? [
                '15:ambiguous-object-method-access',
                '16:ambiguous-object-method-access',
                '17:ambiguous-object-method-access',
                '22:ambiguous-object-method-access',
                '22:possible-method-access-on-null',
                '23:ambiguous-object-method-access',
                '23:possible-method-access-on-null',
                '24:ambiguous-object-method-access',
                '24:possible-method-access-on-null',
                '25:ambiguous-object-method-access',
                '25:possible-method-access-on-null',
                '26:ambiguous-object-method-access',
                '26:possible-method-access-on-null',
                '26:possibly-invalid-argument',
                '26:template-constraint-violation',
                '31:ambiguous-object-method-access',
            ]
            : [
                '15:ambiguous-object-method-access',
                '15:possible-method-access-on-null',
                '16:ambiguous-object-method-access',
                '17:ambiguous-object-method-access',
                '17:possible-method-access-on-null',
                '22:ambiguous-object-method-access',
                '22:possible-method-access-on-null',
                '23:ambiguous-object-method-access',
                '23:possible-method-access-on-null',
                '24:ambiguous-object-method-access',
                '24:possible-method-access-on-null',
                '25:ambiguous-object-method-access',
                '25:possible-method-access-on-null',
                '26:ambiguous-object-method-access',
                '26:possible-method-access-on-null',
                '31:ambiguous-object-method-access',
                '31:possible-method-access-on-null',
            ],
        'tests/fixtures/ContainerMap.php' => [
            '15:mixed-method-access', '15:mixed-return-statement', '20:mixed-method-access',
            '25:mixed-method-access', '26:mixed-method-access',
        ],
        'tests/fixtures/ContainerMapNegative.php' => [
            '15:mixed-method-access', '16:mixed-method-access', '21:mixed-method-access',
            '26:mixed-method-access', '27:mixed-method-access', '28:mixed-method-access',
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
            'alfredbez/symfony/private-service-access' => 2,
            'invalid-argument' => 2,
            'mixed-method-access' => 1,
            'non-existent-method' => 3,
            'null-argument' => 1,
            'possibly-invalid-argument' => 1,
            'possibly-null-argument' => 1,
        ],
        'tests/fixtures-xml/ConsoleCommandNegative.php' => [
            'alfredbez/symfony/unknown-console-argument' => 10,
            'alfredbez/symfony/unknown-console-option' => $isSymfony7 ? 17 : 18,
        ],
        'tests/fixtures-xml/Wave4ContractsNegative.php' => [
            'invalid-return-statement' => 1,
            'missing-constructor' => 1,
            'non-existent-method' => 3,
            'nullable-return-statement' => 1,
        ],
        'tests/fixtures-xml/Wave5Contracts.php' => [],
        'tests/fixtures-xml/Wave5ContractsNegative.php' => $isSymfony7
            ? [
                'alfredbez/symfony/invalid-console-input-definition' => 16,
                'invalid-argument' => 1,
                'invalid-return-statement' => 2,
                'non-existent-method' => 3,
                'nullable-return-statement' => 2,
            ]
            : [
                'alfredbez/symfony/invalid-console-input-definition' => 16,
                'invalid-return-statement' => 2,
                'non-existent-method' => 3,
                'nullable-return-statement' => 2,
            ],
        'tests/fixtures-xml/Wave6ValidatorInitializer.php' => [],
        'tests/fixtures-xml/Wave6ValidatorInitializerNegative.php' => [
            'missing-constructor' => 2,
        ],
        'tests/fixtures-xml/Wave6Contracts.php' => [],
        'tests/fixtures-xml/SerializerClassStringAndPreferredLanguage.php' => [],
        'tests/fixtures-xml/SerializerClassStringAndPreferredLanguageNegative.php' => [
            'ambiguous-object-method-access' => $serializerHasTemplates ? 1 : 0,
            'mixed-method-access' => $serializerHasTemplates ? 0 : 1,
            'non-existent-method' => 2,
            'possibly-null-operand' => 3,
        ],
        'tests/fixtures-xml/Wave6ContractsNegative.php' => $isSymfony7
            ? [
                'invalid-argument' => 1,
                'non-existent-method' => 3,
                'possibly-null-operand' => 8,
                'possibly-null-property-access' => 3,
            ]
            : [
                'non-existent-method' => 3,
                'possibly-null-operand' => 8,
                'possibly-null-property-access' => 3,
            ],
    ],
    true => [
        'tests/fixtures-xml/ContainerXmlUsage.php' => [
            'ambiguous-object-method-access' => $magoAtLeast161 ? 7 : 6,
            'invalid-array-access' => 5,
            'less-specific-return-statement' => 1,
            'missing-constructor' => $isSymfony7 ? 1 : 0,
            'mixed-argument' => 4,
            'mixed-array-access' => 2,
            'mixed-assignment' => 1,
            'mixed-method-access' => $magoAtLeast161 ? 6 : 7,
            'mixed-operand' => 12,
            'mixed-return-statement' => 1,
            'possible-method-access-on-null' => $magoAtLeast161 ? 0 : 2,
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
            'mixed-array-index' => 1,
            'mixed-assignment' => 10 - ($serializerHasTemplates ? 1 : 0) - ($denormalizerHasTemplates ? 1 : 0),
            'mixed-operand' => 2,
            'mixed-property-access' => 4 - ($serializerHasTemplates ? 1 : 0) - ($denormalizerHasTemplates ? 1 : 0),
            'non-existent-method' => 4,
            'nullable-return-statement' => 1,
        ],
        'tests/fixtures-xml/ContainerXmlNegative.php' => [
            'ambiguous-object-method-access' => 2,
            'missing-constructor' => $isSymfony7 ? 1 : 0,
            'mixed-method-access' => 4,
            'possible-method-access-on-null' => $magoAtLeast161 ? 0 : 1,
            'possibly-invalid-argument' => 4,
            'possibly-null-argument' => 4,
        ],
        'tests/fixtures-xml/ConsoleCommandNegative.php' => [],
        'tests/fixtures-xml/Wave4ContractsNegative.php' => [
            'invalid-iterator' => 1,
            'invalid-return-statement' => 1,
            'missing-constructor' => 1,
            'mixed-assignment' => 3 - ($serializerHasTemplates ? 1 : 0),
            'mixed-method-access' => 2 - ($serializerHasTemplates ? 1 : 0),
            'non-existent-method' => 1 + ($serializerHasTemplates ? 1 : 0),
            'nullable-return-statement' => 1,
        ],
        'tests/fixtures-xml/Wave5Contracts.php' => [
            'invalid-iterator' => 1,
            'less-specific-argument' => 4,
            'less-specific-return-statement' => 1,
            'mixed-argument' => 1,
            'mixed-assignment' => 3,
            'mixed-operand' => 1,
            'non-iterable-object-iteration' => 1,
            'possibly-null-operand' => 4,
        ],
        'tests/fixtures-xml/Wave5ContractsNegative.php' => $isSymfony7
            ? [
                'invalid-argument' => 1,
                'invalid-iterator' => 1,
                'invalid-return-statement' => 2,
                'mixed-assignment' => 2,
                'mixed-method-access' => 1,
                'non-existent-method' => 2,
                'non-iterable-object-iteration' => 1,
                'nullable-return-statement' => 2,
            ]
            : [
                'invalid-iterator' => 1,
                'invalid-return-statement' => 2,
                'mixed-assignment' => 2,
                'mixed-method-access' => 1,
                'non-existent-method' => 2,
                'non-iterable-object-iteration' => 1,
                'nullable-return-statement' => 2,
            ],
        'tests/fixtures-xml/Wave6ValidatorInitializer.php' => $isSymfony7
            ? [
                'missing-constructor' => 2,
            ]
            : [],
        'tests/fixtures-xml/Wave6ValidatorInitializerNegative.php' => [
            'missing-constructor' => 2,
        ],
        'tests/fixtures-xml/Wave6Contracts.php' => $isSymfony7
            ? [
                'possibly-null-operand' => 3,
            ]
            : [
                'ambiguous-object-method-access' => 2,
                'less-specific-nested-argument-type' => 1,
                'mixed-argument' => 1,
                'mixed-assignment' => 3,
                'mixed-operand' => 5,
                'mixed-return-statement' => 1,
                'non-existent-method' => 3,
                'possibly-null-operand' => 6,
                'possibly-null-property-access' => 3,
            ],
        'tests/fixtures-xml/SerializerClassStringAndPreferredLanguage.php' => [
            'mixed-assignment' => ($serializerHasTemplates ? 0 : 2) + ($denormalizerHasTemplates ? 0 : 2),
            'mixed-operand' => ($serializerHasTemplates ? 0 : 2) + ($denormalizerHasTemplates ? 0 : 2),
            'mixed-property-access' => ($serializerHasTemplates ? 0 : 2) + ($denormalizerHasTemplates ? 0 : 2),
            'possibly-null-operand' => 2,
        ],
        'tests/fixtures-xml/SerializerClassStringAndPreferredLanguageNegative.php' => [
            'ambiguous-object-method-access' => $serializerHasTemplates ? 1 : 0,
            'mixed-method-access' => ($serializerHasTemplates ? 0 : 2) + ($denormalizerHasTemplates ? 0 : 1),
            'non-existent-method' => ($serializerHasTemplates ? 1 : 0) + ($denormalizerHasTemplates ? 1 : 0),
            'possibly-null-operand' => 3,
        ],
        'tests/fixtures-xml/Wave6ContractsNegative.php' => $isSymfony7
            ? [
                'invalid-argument' => 1,
                'non-existent-method' => 3,
                'possibly-null-operand' => 8,
                'possibly-null-property-access' => 3,
            ]
            : [
                'ambiguous-object-method-access' => 1,
                'non-existent-method' => 2,
                'possibly-null-operand' => 8,
                'possibly-null-property-access' => 3,
            ],
        'vendor/symfony/validator/ConstraintValidator.php' => $isSymfony7
            ? [
                'uninitialized-property' => 1,
            ]
            : [],
    ],
];
foreach ([false => 'xml with extension', true => 'xml --no-extensions'] as $disabled => $label) {
    $failures += assertHistograms($label, $disabled ? $xmlWithout : $xmlWith, $xmlExpected[$disabled]);
}
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-argument', 'user_name', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-option', 'dryrun', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-argument', 'missing_target', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-option', 'missing_force', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-argument', 'undeclared_arg', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-option', 'undeclared-opt', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-argument', 'missing_after_helper', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-argument', 'missing_after_final_helper', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-argument', 'missing_parent_only', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-argument', 'missing_after_cycle', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-argument', 'missing_after_anon', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-argument', '`--target`', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-argument', '`arg`', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-option', '`--known`', 3);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-option', '`-known`', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-option', '`-k`', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-option', '`--verbose`', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-option', '`-v`', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-option', '`unknown_plain`', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-option', '`dash`', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-option', '`triple`', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-option', '`---triple`', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-option', '``', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-option', '`--`', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-option', '`set-dash`', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ConsoleCommandNegative.php', 'alfredbez/symfony/unknown-console-option', '`silent`', $isSymfony7 ? 0 : 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/Wave5ContractsNegative.php', 'alfredbez/symfony/invalid-console-input-definition', '`arg_mode_zero`', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/Wave5ContractsNegative.php', 'alfredbez/symfony/invalid-console-input-definition', '`arg_mode_negative`', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/Wave5ContractsNegative.php', 'alfredbez/symfony/invalid-console-input-definition', '`arg_mode_high`', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/Wave5ContractsNegative.php', 'alfredbez/symfony/invalid-console-input-definition', '`opt_mode_zero`', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/Wave5ContractsNegative.php', 'alfredbez/symfony/invalid-console-input-definition', '`opt_mode_negative`', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/Wave5ContractsNegative.php', 'alfredbez/symfony/invalid-console-input-definition', '`opt_mode_high`', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/Wave5ContractsNegative.php', 'alfredbez/symfony/invalid-console-input-definition', '`set_bad_arg`', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/Wave5ContractsNegative.php', 'alfredbez/symfony/invalid-console-input-definition', '`set_bad_opt`', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/Wave4ContractsNegative.php', 'non-existent-method', 'FormErrorIterator', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/Wave4ContractsNegative.php', 'non-existent-method', 'nonExistentMethodOnDomainEvent', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/Wave4ContractsNegative.php', 'non-existent-method', 'anotherMissingMethod', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/Wave4ContractsNegative.php', 'missing-constructor', 'UninitializedServiceNegative', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ContainerXmlNegative.php', 'alfredbez/symfony/private-service-access', 'xml.private.greeter', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/ContainerXmlNegative.php', 'alfredbez/symfony/private-service-access', 'xml.alias.chain', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/Wave6ContractsNegative.php', 'non-existent-method', 'Wave6MarkerAttribute', 1);
$failures += assertMessageCount('xml with extension', $xmlWith, 'tests/fixtures-xml/Wave6ContractsNegative.php', 'non-existent-method', 'Wave6DomainException', 1);

putenv('MAGO_SYMFONY_STRICT_CONTAINER_SERVICES=1');
$xmlStrictWith = analyze(false, 'tests/mago-xml.toml');
putenv('MAGO_SYMFONY_STRICT_CONTAINER_SERVICES');
$failures += assertMessageCount('xml strict with extension', $xmlStrictWith, 'tests/fixtures-xml/ContainerXmlNegative.php', 'alfredbez/symfony/unknown-service-id', 'xml.unknown.service', 1);
$failures += assertMessageCount('xml strict with extension', $xmlStrictWith, 'tests/fixtures-xml/ContainerXmlUsage.php', 'alfredbez/symfony/unknown-service-id', '', 0);

putenv('MAGO_SYMFONY_CONTAINER_XML');
putenv('MAGO_SYMFONY_USER_CLASS=SymfonyFixtureSecurity\\AppUser');
$secConfigured = analyze(false, 'tests/mago-security.toml');
putenv('MAGO_SYMFONY_USER_CLASS=SymfonyFixtureSecurity\\NotAUser');
$secInvalidClass = analyze(false, 'tests/mago-security.toml');
putenv('MAGO_SYMFONY_USER_CLASS');
$secUnconfigured = analyze(false, 'tests/mago-security.toml');
$secNoExt = analyze(true, 'tests/mago-security.toml');

$failures += assertHistograms('security configured', $secConfigured, [
    'tests/fixtures-security/SecurityUserUsage.php' => [],
    'tests/fixtures-security/SecurityUserNegative.php' => [
        'non-existent-method' => 1,
        'possible-method-access-on-null' => 1,
    ],
]);
$failures += assertMessageCount('security configured', $secConfigured, 'tests/fixtures-security/SecurityUserNegative.php', 'non-existent-method', 'nonExistentMethodOnAppUser', 1);
$failures += assertMessageCount('security configured', $secConfigured, 'tests/fixtures-security/SecurityUserUsage.php', 'non-existent-method', 'getDisplayName', 0);

$unconfiguredSecurityExpected = [
    'tests/fixtures-security/SecurityUserUsage.php' => [
        'less-specific-nested-argument-type' => 1,
        'non-existent-method' => 3,
    ],
    'tests/fixtures-security/SecurityUserNegative.php' => [
        'non-existent-method' => 2,
        'possible-method-access-on-null' => 1,
    ],
];
$failures += assertHistograms('security invalid user class', $secInvalidClass, $unconfiguredSecurityExpected);
$failures += assertHistograms('security unconfigured', $secUnconfigured, $unconfiguredSecurityExpected);
$failures += assertHistograms('security --no-extensions', $secNoExt, [
    'tests/fixtures-security/SecurityUserUsage.php' => [
        'less-specific-nested-argument-type' => 1,
        'missing-constructor' => $isSymfony7 ? 1 : 0,
        'non-existent-method' => 3,
    ],
    'tests/fixtures-security/SecurityUserNegative.php' => [
        'missing-constructor' => $isSymfony7 ? 1 : 0,
        'non-existent-method' => 2,
        'possible-method-access-on-null' => 1,
    ],
]);

$plainConsoleWith = analyze(false, 'tests/mago-console-plain.toml');
$plainConsoleWithout = analyze(true, 'tests/mago-console-plain.toml');
$failures += assertHistograms('plain console with extension', $plainConsoleWith, [
    'tests/fixtures-console-plain/PlainConsoleDefaults.php' => [],
    'tests/fixtures-console-plain/PlainConsoleDefaultsNegative.php' => [
        'alfredbez/symfony/unknown-console-option' => $isSymfony7 ? 3 : 4,
    ],
]);
$failures += assertHistograms('plain console --no-extensions', $plainConsoleWithout, [
    'tests/fixtures-console-plain/PlainConsoleDefaults.php' => [],
    'tests/fixtures-console-plain/PlainConsoleDefaultsNegative.php' => [],
]);
$failures += assertMessageCount('plain console with extension', $plainConsoleWith, 'tests/fixtures-console-plain/PlainConsoleDefaultsNegative.php', 'alfredbez/symfony/unknown-console-option', '`env`', 1);
$failures += assertMessageCount('plain console with extension', $plainConsoleWith, 'tests/fixtures-console-plain/PlainConsoleDefaultsNegative.php', 'alfredbez/symfony/unknown-console-option', '`no-debug`', 1);
$failures += assertMessageCount('plain console with extension', $plainConsoleWith, 'tests/fixtures-console-plain/PlainConsoleDefaultsNegative.php', 'alfredbez/symfony/unknown-console-option', '`profile`', 1);
$failures += assertMessageCount('plain console with extension', $plainConsoleWith, 'tests/fixtures-console-plain/PlainConsoleDefaultsNegative.php', 'alfredbez/symfony/unknown-console-option', '`silent`', $isSymfony7 ? 0 : 1);

$serializerUntemplatedWith = analyze(false, 'tests/mago-serializer-untemplated.toml');
$serializerUntemplatedWithout = analyze(true, 'tests/mago-serializer-untemplated.toml');
$failures += assertHistograms('serializer untemplated with extension', $serializerUntemplatedWith, [
    'tests/fixtures-serializer-untemplated/SerializerClassStringUsage.php' => [],
    'tests/fixtures-serializer-untemplated/SerializerClassStringNegative.php' => [
        'mixed-method-access' => 1,
        'non-existent-method' => 2,
    ],
]);
$failures += assertHistograms('serializer untemplated --no-extensions', $serializerUntemplatedWithout, [
    'tests/fixtures-serializer-untemplated/SerializerClassStringUsage.php' => [
        'mixed-assignment' => 2,
        'mixed-operand' => 2,
        'mixed-property-access' => 2,
    ],
    'tests/fixtures-serializer-untemplated/SerializerClassStringNegative.php' => [
        'mixed-method-access' => 3,
    ],
]);
$failures += assertMessageCount('serializer untemplated with extension', $serializerUntemplatedWith, 'tests/fixtures-serializer-untemplated/SerializerClassStringNegative.php', 'non-existent-method', 'missingOnDeserialized', 1);
$failures += assertMessageCount('serializer untemplated with extension', $serializerUntemplatedWith, 'tests/fixtures-serializer-untemplated/SerializerClassStringNegative.php', 'non-existent-method', 'missingOnDenormalized', 1);

if ($arrayNodeHasTemplates) {
    $tbReturnWith = analyze(false, 'tests/mago-treebuilder-return.toml');
    $tbReturnWithout = analyze(true, 'tests/mago-treebuilder-return.toml');
    $failures += assertHistograms('treebuilder return negative with extension', $tbReturnWith, [
        'tests/fixtures-treebuilder-return/TreeBuilderRootNodeReturnNegative.php' => [
            'invalid-return-statement' => 7,
            'less-specific-return-statement' => 1,
            'non-existent-method' => 1,
        ],
    ]);
    $failures += assertHistograms('treebuilder return negative --no-extensions', $tbReturnWithout, [
        'tests/fixtures-treebuilder-return/TreeBuilderRootNodeReturnNegative.php' => [
            'less-specific-return-statement' => 8,
            'non-existent-method' => 1,
        ],
    ]);
    $failures += assertSites('treebuilder return negative with extension', $tbReturnWith, [
        'tests/fixtures-treebuilder-return/TreeBuilderRootNodeReturnNegative.php' => [
            '26:invalid-return-statement',
            '45:invalid-return-statement',
            '55:invalid-return-statement',
            '60:invalid-return-statement',
            '65:invalid-return-statement',
            '70:invalid-return-statement',
            '75:invalid-return-statement',
            '85:less-specific-return-statement',
            '90:non-existent-method',
        ],
    ]);
}

$lifecycleWith = analyze(false, 'tests/mago-lifecycle.toml');
$lifecycleWithout = analyze(true, 'tests/mago-lifecycle.toml');
$failures += assertHistograms('lifecycle with extension', $lifecycleWith, [
    'tests/fixtures-lifecycle/LifecycleAndEntryPoints.php' => [],
    'tests/fixtures-lifecycle/LifecycleAndEntryPointsNegative.php' => [
        'uninitialized-property' => 4,
        'unused-method' => 1,
    ],
]);
$failures += assertHistograms('lifecycle --no-extensions', $lifecycleWithout, [
    'tests/fixtures-lifecycle/LifecycleAndEntryPoints.php' => [
        'invalid-return-statement' => $isSymfony7 ? 1 : 0,
        'missing-constructor' => 2,
        'uninitialized-property' => 1,
        'unused-method' => 5,
    ],
    'tests/fixtures-lifecycle/LifecycleAndEntryPointsNegative.php' => [
        'uninitialized-property' => 4,
        'unused-method' => 1,
    ],
]);
$failures += assertMessageCount('lifecycle with extension', $lifecycleWith, 'tests/fixtures-lifecycle/LifecycleAndEntryPointsNegative.php', 'uninitialized-property', '$privateRequired', 1);
$failures += assertMessageCount('lifecycle with extension', $lifecycleWith, 'tests/fixtures-lifecycle/LifecycleAndEntryPointsNegative.php', 'uninitialized-property', '$protectedRequired', 1);
$failures += assertMessageCount('lifecycle with extension', $lifecycleWith, 'tests/fixtures-lifecycle/LifecycleAndEntryPointsNegative.php', 'uninitialized-property', '$readonlyRequired', 1);
$failures += assertMessageCount('lifecycle with extension', $lifecycleWith, 'tests/fixtures-lifecycle/LifecycleAndEntryPointsNegative.php', 'uninitialized-property', '$plainPublic', 1);
$failures += assertMessageCount('lifecycle with extension', $lifecycleWith, 'tests/fixtures-lifecycle/LifecycleAndEntryPointsNegative.php', 'unused-method', 'genuinelyUnusedHelper', 1);

$failures += verifyConfigErrorBehavior($magoCommand);

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
    $magoBinEnv = getenv('MAGO_BIN');
    $command = ($magoBinEnv !== false && $magoBinEnv !== '')
        ? [$magoBinEnv]
        : [PHP_BINARY, 'vendor/bin/mago'];
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

        $start = $primary['span']['start'];
        $line = array_key_exists('column', $start)
            ? (int) $start['line']
            : (int) $start['line'] + 1;

        $issues[] = [
            'level' => (string) $issue['level'],
            'code' => (string) $issue['code'],
            'file' => (string) $primary['span']['file_id']['name'],
            'line' => $line,
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

/**
 * @param list<string> $magoCommand
 */
function verifyConfigErrorBehavior(array $magoCommand): int
{
    $failures = 0;
    $tmpDir = sys_get_temp_dir() . '/mago-symfony-cfg-' . getmypid();
    if (!is_dir($tmpDir)) {
        mkdir($tmpDir, 0777, true);
    }

    $badXml = $tmpDir . '/broken.xml';
    $wrongRootXml = $tmpDir . '/wrong-root.xml';
    $badJson = $tmpDir . '/broken.json';
    $stderrLog = $tmpDir . '/worker-stderr.log';
    $wrapperConfig = $tmpDir . '/security-stderr.toml';
    $xmlWrapperConfig = $tmpDir . '/xml-stderr.toml';
    $wrapperWorker = $tmpDir . '/stderr-wrapper.php';

    file_put_contents($badXml, "<container><services><service id=\"a\"></container>");
    file_put_contents($wrongRootXml, "<not-a-container/>");
    file_put_contents($badJson, "{invalid json");

    try {
        $repoRoot = getcwd();
        $workerBin = $repoRoot . '/bin/mago-symfony-worker';
        $wrapperCode = <<<PHP
<?php
\$cmd = [PHP_BINARY, '-d', 'xdebug.mode=off', '-d', 'pcov.enabled=0', %s];
\$p = proc_open(\$cmd, [0 => STDIN, 1 => STDOUT, 2 => ['pipe', 'w']], \$pipes);
if (\$p === false) {
    exit(1);
}
\$err = (string) stream_get_contents(\$pipes[2]);
fclose(\$pipes[2]);
file_put_contents(%s, \$err);
if (\$err !== '') {
    fwrite(STDERR, \$err);
    fflush(STDERR);
}
exit(proc_close(\$p));
PHP;
        file_put_contents(
            $wrapperWorker,
            sprintf($wrapperCode, var_export($workerBin, true), var_export($stderrLog, true)),
        );

        $xmlToml = (string) file_get_contents($repoRoot . '/tests/mago-xml.toml');
        $xmlToml = str_replace(
            'command = ["php", "worker.php"]',
            'command = [' . json_encode(PHP_BINARY, JSON_UNESCAPED_SLASHES) . ', ' . json_encode($wrapperWorker, JSON_UNESCAPED_SLASHES) . ']',
            $xmlToml,
        );
        file_put_contents($xmlWrapperConfig, $xmlToml);

        $errorCases = [
            [
                'label' => 'missing MAGO_SYMFONY_CONTAINER_XML',
                'env' => ['MAGO_SYMFONY_CONTAINER_XML' => $tmpDir . '/does-not-exist.xml'],
                'needle' => 'MAGO_SYMFONY_CONTAINER_XML points to a missing or unreadable file',
            ],
            [
                'label' => 'malformed MAGO_SYMFONY_CONTAINER_XML',
                'env' => ['MAGO_SYMFONY_CONTAINER_XML' => $badXml],
                'needle' => 'MAGO_SYMFONY_CONTAINER_XML contains malformed XML',
            ],
            [
                'label' => 'wrong-root MAGO_SYMFONY_CONTAINER_XML',
                'env' => ['MAGO_SYMFONY_CONTAINER_XML' => $wrongRootXml],
                'needle' => 'MAGO_SYMFONY_CONTAINER_XML is not a Symfony container XML dump',
            ],
            [
                'label' => 'missing MAGO_SYMFONY_CONTAINER_MAP',
                'env' => ['MAGO_SYMFONY_CONTAINER_MAP' => $tmpDir . '/does-not-exist.json'],
                'needle' => 'MAGO_SYMFONY_CONTAINER_MAP points to a missing or unreadable file',
            ],
            [
                'label' => 'malformed MAGO_SYMFONY_CONTAINER_MAP',
                'env' => ['MAGO_SYMFONY_CONTAINER_MAP' => $badJson],
                'needle' => 'MAGO_SYMFONY_CONTAINER_MAP contains malformed JSON',
            ],
        ];

        foreach ($errorCases as $case) {
            @unlink($stderrLog);
            foreach ($case['env'] as $k => $v) {
                putenv("{$k}={$v}");
            }
            $res = run([...$magoCommand, '--config', $xmlWrapperConfig, '--workspace', $repoRoot, 'analyze', '--reporting-format=json']);
            foreach (array_keys($case['env']) as $k) {
                putenv($k);
            }

            $framingOk = !str_contains($res['stderr'], 'invalid extension frame magic');
            $workerStderr = is_file($stderrLog) ? (string) file_get_contents($stderrLog) : '';
            $hasMsg = str_contains($workerStderr, $case['needle']) || str_contains($res['stderr'], $case['needle']);
            if ($res['exit'] !== 0 && $framingOk && $hasMsg) {
                fwrite(STDOUT, "ok  config error  {$case['label']}\n");
            } else {
                $failures++;
                fwrite(STDERR, "FAIL config error  {$case['label']} (exit={$res['exit']}, framingOk=" . ($framingOk ? 'yes' : 'no') . ")\n");
                fwrite(STDERR, "  expected stderr containing: {$case['needle']}\n");
                fwrite(STDERR, "  actual mago stderr: " . trim($res['stderr']) . "\n");
                fwrite(STDERR, "  actual worker stderr: " . trim($workerStderr) . "\n");
            }
        }

        $secToml = (string) file_get_contents($repoRoot . '/tests/mago-security.toml');
        $secToml = str_replace(
            'command = ["php", "worker.php"]',
            'command = [' . json_encode(PHP_BINARY, JSON_UNESCAPED_SLASHES) . ', ' . json_encode($wrapperWorker, JSON_UNESCAPED_SLASHES) . ']',
            $secToml,
        );
        file_put_contents($wrapperConfig, $secToml);

        $userCases = [
            [
                'label' => 'non-existent MAGO_SYMFONY_USER_CLASS',
                'value' => 'SymfonyFixtureSecurity\\DoesNotExistUser',
                'needle' => 'MAGO_SYMFONY_USER_CLASS `SymfonyFixtureSecurity\\DoesNotExistUser` does not exist',
            ],
            [
                'label' => 'non-UserInterface MAGO_SYMFONY_USER_CLASS',
                'value' => 'SymfonyFixtureSecurity\\NotAUser',
                'needle' => 'MAGO_SYMFONY_USER_CLASS `SymfonyFixtureSecurity\\NotAUser` does not implement Symfony\\Component\\Security\\Core\\User\\UserInterface',
            ],
        ];

        foreach ($userCases as $case) {
            @unlink($stderrLog);
            putenv('MAGO_SYMFONY_USER_CLASS=' . $case['value']);
            $res = run([...$magoCommand, '--config', $wrapperConfig, '--workspace', $repoRoot, 'analyze', '--reporting-format=json']);
            putenv('MAGO_SYMFONY_USER_CLASS');

            $decoded = json_decode($res['stdout'], true);
            $jsonOk = is_array($decoded) && isset($decoded['issues']) && is_array($decoded['issues']);
            $workerStderr = is_file($stderrLog) ? (string) file_get_contents($stderrLog) : '';
            $hasWarn = str_contains($workerStderr, $case['needle']);
            if ($jsonOk && $hasWarn) {
                fwrite(STDOUT, "ok  config warning  {$case['label']}\n");
            } else {
                $failures++;
                fwrite(STDERR, "FAIL config warning  {$case['label']} (jsonOk=" . ($jsonOk ? 'yes' : 'no') . ")\n");
                fwrite(STDERR, "  expected worker stderr containing: {$case['needle']}\n");
                fwrite(STDERR, "  actual worker stderr: " . trim($workerStderr) . "\n");
            }
        }

        // Absent auto-discovery stays silent and exits cleanly.
        @unlink($stderrLog);
        $silentRes = run([...$magoCommand, '--config', $wrapperConfig, '--workspace', $repoRoot, 'analyze', '--reporting-format=json']);
        $silentDecoded = json_decode($silentRes['stdout'], true);
        $silentStderr = is_file($stderrLog) ? trim((string) file_get_contents($stderrLog)) : '';
        if (is_array($silentDecoded) && isset($silentDecoded['issues']) && $silentStderr === '') {
            fwrite(STDOUT, "ok  config silent auto-discovery\n");
        } else {
            $failures++;
            fwrite(STDERR, "FAIL config silent auto-discovery (worker stderr: {$silentStderr})\n");
        }
    } finally {
        @unlink($badXml);
        @unlink($wrongRootXml);
        @unlink($badJson);
        @unlink($stderrLog);
        @unlink($wrapperConfig);
        @unlink($xmlWrapperConfig);
        @unlink($wrapperWorker);
        @rmdir($tmpDir);
    }

    return $failures;
}

<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
try {
    $catalog = json_decode(file_get_contents($argv[1] ?? $root . '/spec/catalog.json'), true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    fwrite(STDERR, "Cannot read catalog: {$error->getMessage()}\n");
    exit(1);
}
if (
    ($catalog['schema_version'] ?? null) !== 1
    || !is_array($catalog['sources'] ?? null)
    || !is_array($catalog['cases'] ?? null)
    || $catalog['sources'] === []
    || $catalog['cases'] === []
) {
    fwrite(STDERR, "Expected schema_version 1, sources, and non-empty cases\n");
    exit(1);
}

foreach ($catalog['sources'] as $key => $source) {
    if (!is_array($source)) {
        $failures[] = "{$key}: source must be an object";
        continue;
    }
    if (!is_string($source['url'] ?? null) || preg_match('~^https://github\.com/[^/]+/[^/]+/blob/[a-f0-9]{40}/.+$~', $source['url']) !== 1) {
        $failures[] = "{$key}: source must use a pinned GitHub commit URL";
    }
    if (!is_string($source['sha256'] ?? null) || preg_match('/^[a-f0-9]{64}$/', $source['sha256']) !== 1) {
        $failures[] = "{$key}: missing content hash";
    }
    if (!in_array($source['kind'] ?? null, ['analyzer-test', 'runtime-contract'], true) || empty($source['scope'])) {
        $failures[] = "{$key}: missing source kind or scope";
    }
}

$ids = [];
foreach ($catalog['cases'] as $case) {
    if (!is_array($case)) {
        $failures[] = 'Case must be an object';
        continue;
    }
    $id = $case['id'] ?? '';
    if (!is_string($id) || preg_match('/^SYMFONY-[CT][0-9]{3}$/', $id) !== 1 || isset($ids[$id])) {
        $failures[] = 'Invalid or duplicate case ID';
        continue;
    }
    $ids[$id] = true;
    foreach (['title', 'fixture', 'semantics', 'analyzer_expectation', 'negative_control', 'versions', 'derivation'] as $field) {
        if (!is_string($case[$field] ?? null) || trim($case[$field]) === '') {
            $failures[] = "{$id}: missing {$field}";
        }
    }
    if (!in_array($case['status'] ?? null, ['candidate', 'runtime-verified', 'red', 'partial', 'covered'], true)) {
        $failures[] = "{$id}: invalid status";
    }
    if (!in_array($case['priority'] ?? null, ['P0', 'P1', 'P2'], true)) {
        $failures[] = "{$id}: invalid priority";
    }
    if (!is_array($case['sources'] ?? null) || $case['sources'] === []) {
        $failures[] = "{$id}: missing sources";
    } else {
        foreach ($case['sources'] as $source) {
            if (!is_string($source) || !isset($catalog['sources'][$source])) {
                $failures[] = "{$id}: unknown source";
            }
        }
    }
    $positive = false;
    $negative = false;
    if (!is_array($case['coverage'] ?? null)) {
        $failures[] = "{$id}: missing coverage";
    } else {
        foreach ($case['coverage'] as $path) {
            if (!is_string($path) || !str_starts_with($path, 'tests/') || !is_file($root . '/' . $path)) {
                $failures[] = "{$id}: missing test file";
                continue;
            }
            if (str_starts_with($path, 'tests/fixtures/')) {
                $negative = $negative || str_contains($path, 'Negative');
                $positive = $positive || !str_contains($path, 'Negative');
            }
        }
    }
    if (($case['status'] ?? '') === 'covered' && (!$positive || !$negative)) {
        $failures[] = "{$id}: covered requires positive and negative fixtures";
    }
    $fixture = $case['fixture'] ?? null;
    if (!is_string($fixture) || !str_starts_with($fixture, '<?php')) {
        $failures[] = "{$id}: fixture must start with a PHP opening tag";
        continue;
    }
    try {
        token_get_all($fixture, TOKEN_PARSE);
    } catch (ParseError $error) {
        $failures[] = "{$id}: invalid PHP fixture: {$error->getMessage()}";
    }
}
foreach ($failures as $failure) {
    fwrite(STDERR, $failure . "\n");
}
fwrite(STDOUT, count($ids) . ' spec cases, ' . count($failures) . " validation failures\n");
exit($failures === [] ? 0 : 1);

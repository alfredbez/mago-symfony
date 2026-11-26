<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\BeforeAnalysisContext;
use Mago\Sdk\Analyzer\BeforeAnalysisHook;
use Mago\Sdk\Span;

use function array_key_exists;
use function array_keys;
use function array_pop;
use function count;
use function dirname;
use function file_get_contents;
use function get_declared_classes;
use function getcwd;
use function is_array;
use function is_file;
use function is_string;
use function ltrim;
use function str_contains;
use function str_starts_with;
use function stripos;
use function strlen;
use function strtolower;
use function substr;
use function token_get_all;

use const T_AS;
use const T_CATCH;
use const T_CLASS;
use const T_COMMENT;
use const T_CONSTANT_ENCAPSED_STRING;
use const T_DO;
use const T_DOC_COMMENT;
use const T_ELSE;
use const T_ELSEIF;
use const T_FN;
use const T_FOR;
use const T_FOREACH;
use const T_FUNCTION;
use const T_IF;
use const T_LIST;
use const T_MATCH;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NEW;
use const T_OBJECT_OPERATOR;
use const T_STRING;
use const T_SWITCH;
use const T_TRY;
use const T_UNSET;
use const T_USE;
use const T_VARIABLE;
use const T_WHILE;
use const T_WHITESPACE;

/**
 * Indexes `TreeBuilder::getRootNode()` calls by exact byte span using PHP's tokenizer
 * during `BeforeAnalysisHook`, avoiding eager CST transport across the codebase.
 *
 * Returns one of:
 * - concrete normalized root type (`'scalar'`, `'enum'`, `'integer'`, ..., `'array'`)
 * - `self::AMBIGUOUS` when a span collides across files with differing root types or
 *   when the TreeBuilder root type is dynamic, conditionally reassigned, or rebound
 * - `null` when the span is unseen (e.g. parameter-passed TreeBuilder or unit tests)
 *
 * @internal
 */
final class TreeBuilderRootNodeIndex implements BeforeAnalysisHook
{
    public const AMBIGUOUS = '__ambiguous__';

    private const LOWER_TREE_BUILDER = 'symfony\\component\\config\\definition\\builder\\treebuilder';

    private const NON_ARRAY_TYPES = [
        'variable' => true,
        'scalar' => true,
        'string' => true,
        'boolean' => true,
        'integer' => true,
        'float' => true,
        'enum' => true,
    ];

    /** @var array<string, string> spanKey => normalized root type or self::AMBIGUOUS */
    private array $rootTypeBySpan = [];

    private bool $hasNonArrayRoot = false;

    public function beforeAnalysis(BeforeAnalysisContext $context): void
    {
        $this->rootTypeBySpan = [];
        $this->hasNonArrayRoot = false;

        $files = $this->collectCandidateFiles($context);
        if ($files === []) {
            return;
        }

        $deferredArrayFiles = [];
        foreach ($files as $file) {
            $resolvedFile = self::resolveReadableFile($file);
            if ($resolvedFile === null) {
                continue;
            }
            $contents = @file_get_contents($resolvedFile);
            if ($contents === false || stripos($contents, 'getRootNode') === false) {
                continue;
            }

            if (stripos($contents, 'TreeBuilder') === false || !self::mightHaveNonArrayRoot($contents)) {
                $deferredArrayFiles[] = $contents;
                continue;
            }

            $entries = self::scanSource($contents, resolveNonArray: true);
            foreach ($entries as [$key, $type]) {
                if ($type !== 'array' && $type !== self::AMBIGUOUS) {
                    $this->hasNonArrayRoot = true;
                }
                $this->recordSpanType($key, $type);
            }
        }

        if ($this->hasNonArrayRoot && $deferredArrayFiles !== []) {
            foreach ($deferredArrayFiles as $contents) {
                foreach (self::scanSource($contents, resolveNonArray: false) as [$key, $type]) {
                    $this->recordSpanType($key, $type);
                }
            }
        }
    }

    public function rootTypeForSpan(Span $span): ?string
    {
        if (!$this->hasNonArrayRoot || ($span->start === 0 && $span->end === 0)) {
            return null;
        }

        $key = $span->start . ':' . $span->end;

        return $this->rootTypeBySpan[$key] ?? null;
    }

    private const STUB_NAMESPACE_PREFIXES = [
        'aerospike\\',
        'amqp\\',
        'bcmath\\',
        'brotli\\',
        'componere\\',
        'crypto\\',
        'dom\\',
        'ds\\',
        'ffi\\',
        'grpc\\',
        'mago\\',
        'mongodb\\',
        'openswoole\\',
        'opentelemetry\\',
        'parallel\\',
        'pdo\\',
        'random\\',
        'rdkafka\\',
        'sodium\\',
        'swoole\\',
        'uri\\',
        'vtiful\\',
    ];

    /**
     * @return list<string>
     */
    private function collectCandidateFiles(BeforeAnalysisContext $context): array
    {
        $internalClasses = [];
        foreach (get_declared_classes() as $declared) {
            $internalClasses[strtolower($declared)] = true;
        }

        $classQuery = [];
        foreach ($context->codebase->getClassNames() as $name) {
            if (!str_contains($name, '\\')) {
                continue;
            }
            $lower = strtolower($name);
            if (isset($internalClasses[$lower]) || self::isStubNamespace($lower)) {
                continue;
            }
            $classQuery[] = $name;
        }

        $files = [];
        if ($classQuery !== []) {
            foreach ($context->codebase->getMultipleClasses($classQuery) as $meta) {
                if ($meta !== null) {
                    $file = $meta->location->file;
                    if (is_string($file) && $file !== '' && !str_contains($file, '/vendor/') && !str_starts_with($file, 'vendor/')) {
                        $files[$file] = true;
                    }
                }
            }
        }

        $fnQuery = [];
        foreach ($context->codebase->getFunctionNames() as $fnName) {
            if (!str_contains($fnName, '\\')) {
                continue;
            }
            if (self::isStubNamespace(strtolower($fnName))) {
                continue;
            }
            $fnQuery[] = $fnName;
        }

        if ($fnQuery !== []) {
            foreach ($context->codebase->getMultipleFunctions($fnQuery) as $fnMeta) {
                if ($fnMeta !== null) {
                    $file = $fnMeta->location->file;
                    if (is_string($file) && $file !== '' && !str_contains($file, '/vendor/') && !str_starts_with($file, 'vendor/')) {
                        $files[$file] = true;
                    }
                }
            }
        }

        return array_keys($files);
    }

    private static function resolveReadableFile(string $file): ?string
    {
        if (is_file($file)) {
            return $file;
        }

        if (str_starts_with($file, '/') || str_starts_with($file, '\\')) {
            return null;
        }

        $dir = getcwd();
        if ($dir === false) {
            return null;
        }

        for ($depth = 0; $depth < 6; ++$depth) {
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
            $candidate = $dir . '/' . $file;
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private static function isStubNamespace(string $lowerName): bool
    {
        foreach (self::STUB_NAMESPACE_PREFIXES as $prefix) {
            if (str_starts_with($lowerName, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function recordSpanType(string $key, string $rootType): void
    {
        if (array_key_exists($key, $this->rootTypeBySpan)) {
            if ($this->rootTypeBySpan[$key] !== $rootType) {
                $this->rootTypeBySpan[$key] = self::AMBIGUOUS;
            }

            return;
        }

        $this->rootTypeBySpan[$key] = $rootType;
    }

    private static function mightHaveNonArrayRoot(string $contents): bool
    {
        foreach (self::NON_ARRAY_TYPES as $type => $_) {
            if (stripos($contents, "'" . $type . "'") !== false || stripos($contents, '"' . $type . '"') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scans one PHP file with `token_get_all()` and returns `[spanKey, rootType]` pairs
     * for every `getRootNode()` call.
     *
     * @return list<array{string, string}>
     */
    private static function scanSource(string $contents, bool $resolveNonArray): array
    {
        $rawTokens = token_get_all($contents);
        /** @var list<array{id: int|string, text: string, start: int, end: int}> $tokens */
        $tokens = [];
        $offset = 0;
        foreach ($rawTokens as $tok) {
            if (is_array($tok)) {
                $len = strlen($tok[1]);
                if ($tok[0] !== T_WHITESPACE && $tok[0] !== T_COMMENT && $tok[0] !== T_DOC_COMMENT) {
                    $tokens[] = ['id' => $tok[0], 'text' => $tok[1], 'start' => $offset, 'end' => $offset + $len];
                }
                $offset += $len;
            } else {
                $len = strlen($tok);
                $tokens[] = ['id' => $tok, 'text' => $tok, 'start' => $offset, 'end' => $offset + $len];
                $offset += $len;
            }
        }

        $total = count($tokens);
        $treeBuilderAliases = self::collectTreeBuilderAliases($tokens);
        $results = [];

        /** @var list<array{braceDepth: int, vars: array<string, string>, branchDepth: int}> $scopes */
        $scopes = [['braceDepth' => 0, 'vars' => [], 'branchDepth' => 0]];
        $braceDepth = 0;
        /** @var list<int> $branchBraceDepths */
        $branchBraceDepths = [];
        $pendingFunctionScope = false;
        $pendingBranch = false;

        for ($i = 0; $i < $total; ++$i) {
            $t = $tokens[$i];
            $id = $t['id'];

            if ($id === T_FUNCTION) {
                $pendingFunctionScope = true;
                continue;
            }

            if ($id === T_FN) {
                // Arrow function: skip its expression body conservatively to avoid leaking bindings.
                $arrow = self::findNextToken($tokens, $i + 1, '=>');
                if ($arrow !== null) {
                    $i = self::skipArrowExpression($tokens, $arrow + 1);
                }
                continue;
            }

            if (
                $id === T_IF
                || $id === T_ELSEIF
                || $id === T_ELSE
                || $id === T_SWITCH
                || $id === T_MATCH
                || $id === T_TRY
                || $id === T_WHILE
                || $id === T_DO
                || $id === T_FOR
            ) {
                $pendingBranch = true;
                continue;
            }

            if ($id === T_FOREACH) {
                $pendingBranch = true;
                if ($resolveNonArray) {
                    $openParen = self::findNextToken($tokens, $i + 1, '(');
                    if ($openParen !== null) {
                        $closeParen = self::findMatchingDelimiter($tokens, $openParen, '(', ')');
                        if ($closeParen !== null) {
                            $asIdx = self::findTokenInRange($tokens, $openParen + 1, $closeParen, T_AS);
                            if ($asIdx !== null) {
                                for ($k = $asIdx + 1; $k < $closeParen; ++$k) {
                                    if ($tokens[$k]['id'] === T_VARIABLE) {
                                        self::killScopeVar($scopes, $tokens[$k]['text']);
                                    }
                                }
                            }
                        }
                    }
                }
                continue;
            }

            if ($id === T_CATCH) {
                $pendingBranch = true;
                if ($resolveNonArray) {
                    $openParen = self::findNextToken($tokens, $i + 1, '(');
                    if ($openParen !== null) {
                        $closeParen = self::findMatchingDelimiter($tokens, $openParen, '(', ')');
                        if ($closeParen !== null) {
                            for ($k = $openParen + 1; $k < $closeParen; ++$k) {
                                if ($tokens[$k]['id'] === T_VARIABLE) {
                                    self::killScopeVar($scopes, $tokens[$k]['text']);
                                }
                            }
                        }
                    }
                }
                continue;
            }

            if ($id === T_UNSET && $resolveNonArray) {
                $openParen = self::findNextToken($tokens, $i + 1, '(');
                if ($openParen !== null) {
                    $closeParen = self::findMatchingDelimiter($tokens, $openParen, '(', ')');
                    if ($closeParen !== null) {
                        for ($k = $openParen + 1; $k < $closeParen; ++$k) {
                            if ($tokens[$k]['id'] === T_VARIABLE) {
                                self::killScopeVar($scopes, $tokens[$k]['text']);
                            }
                        }
                    }
                }
                continue;
            }

            if ($id === T_LIST && $resolveNonArray) {
                $openParen = self::findNextToken($tokens, $i + 1, '(');
                if ($openParen !== null) {
                    $closeParen = self::findMatchingDelimiter($tokens, $openParen, '(', ')');
                    if ($closeParen !== null) {
                        for ($k = $openParen + 1; $k < $closeParen; ++$k) {
                            if ($tokens[$k]['id'] === T_VARIABLE) {
                                self::killScopeVar($scopes, $tokens[$k]['text']);
                            }
                        }
                    }
                }
                continue;
            }

            if ($id === '[' && $resolveNonArray) {
                $closeBracket = self::findMatchingDelimiter($tokens, $i, '[', ']');
                if ($closeBracket !== null && isset($tokens[$closeBracket + 1]) && $tokens[$closeBracket + 1]['id'] === '=') {
                    for ($k = $i + 1; $k < $closeBracket; ++$k) {
                        if ($tokens[$k]['id'] === T_VARIABLE) {
                            self::killScopeVar($scopes, $tokens[$k]['text']);
                        }
                    }
                }
                continue;
            }

            if ($id === '{') {
                ++$braceDepth;
                if ($pendingFunctionScope) {
                    $scopes[] = ['braceDepth' => $braceDepth, 'vars' => [], 'branchDepth' => count($branchBraceDepths)];
                    $pendingFunctionScope = false;
                    $pendingBranch = false;
                } elseif ($pendingBranch) {
                    $branchBraceDepths[] = $braceDepth;
                    $pendingBranch = false;
                }
                continue;
            }

            if ($id === '}') {
                $topScope = $scopes[count($scopes) - 1];
                if (count($scopes) > 1 && $topScope['braceDepth'] === $braceDepth) {
                    array_pop($scopes);
                }
                if ($branchBraceDepths !== [] && $branchBraceDepths[count($branchBraceDepths) - 1] === $braceDepth) {
                    array_pop($branchBraceDepths);
                }
                if ($braceDepth > 0) {
                    --$braceDepth;
                }
                continue;
            }

            if ($id === ';') {
                $pendingFunctionScope = false;
                $pendingBranch = false;
                continue;
            }

            // Variable assignment: `$var = ...`
            if ($resolveNonArray && $id === T_VARIABLE && isset($tokens[$i + 1]) && $tokens[$i + 1]['id'] === '=') {
                $varName = $t['text'];
                $rhsStart = $i + 2;
                $inBranch = count($branchBraceDepths) > $scopes[count($scopes) - 1]['branchDepth'];
                $assignedType = self::parseRhsTreeBuilderType($tokens, $rhsStart, $treeBuilderAliases);
                self::assignScopeVar($scopes, $varName, $assignedType, $inBranch);
                continue;
            }

            // Method call: `->getRootNode()`
            if (
                $id === T_OBJECT_OPERATOR
                && isset($tokens[$i + 1], $tokens[$i + 2])
                && $tokens[$i + 1]['id'] === T_STRING
                && strtolower($tokens[$i + 1]['text']) === 'getrootnode'
                && $tokens[$i + 2]['id'] === '('
            ) {
                $closeCall = self::findMatchingDelimiter($tokens, $i + 2, '(', ')');
                if ($closeCall === null) {
                    continue;
                }

                $receiverStartIdx = self::findReceiverStartIndex($tokens, $i - 1);
                if ($receiverStartIdx === null) {
                    continue;
                }

                $spanKey = $tokens[$receiverStartIdx]['start'] . ':' . $tokens[$closeCall]['end'];
                if (!$resolveNonArray) {
                    $results[] = [$spanKey, 'array'];
                    continue;
                }

                $rootType = self::resolveReceiverRootType($tokens, $receiverStartIdx, $i - 1, $treeBuilderAliases, $scopes);
                if ($rootType !== null) {
                    $results[] = [$spanKey, $rootType];
                }
            }
        }

        return $results;
    }

    /**
     * @param list<array{braceDepth: int, vars: array<string, string>, branchDepth: int}> $scopes
     */
    private static function killScopeVar(array &$scopes, string $varName): void
    {
        $top = count($scopes) - 1;
        unset($scopes[$top]['vars'][$varName]);
    }

    /**
     * @param list<array{braceDepth: int, vars: array<string, string>, branchDepth: int}> $scopes
     */
    private static function assignScopeVar(array &$scopes, string $varName, ?string $newType, bool $inBranch): void
    {
        $top = count($scopes) - 1;
        if ($newType === null) {
            unset($scopes[$top]['vars'][$varName]);

            return;
        }
        if (!$inBranch) {
            $scopes[$top]['vars'][$varName] = $newType;

            return;
        }

        if (!isset($scopes[$top]['vars'][$varName])) {
            $scopes[$top]['vars'][$varName] = self::AMBIGUOUS;
        } elseif ($scopes[$top]['vars'][$varName] !== $newType) {
            $scopes[$top]['vars'][$varName] = self::AMBIGUOUS;
        }
    }

    /**
     * @param list<array{id: int|string, text: string, start: int, end: int}> $tokens
     * @return array<string, true> lowercase class names that resolve to TreeBuilder
     */
    private static function collectTreeBuilderAliases(array $tokens): array
    {
        $aliases = [self::LOWER_TREE_BUILDER => true];
        $total = count($tokens);
        for ($i = 0; $i < $total; ++$i) {
            if ($tokens[$i]['id'] === T_CLASS) {
                break;
            }
            if ($tokens[$i]['id'] !== T_USE) {
                continue;
            }
            $j = $i + 1;
            $fqn = '';
            while ($j < $total && ($tokens[$j]['id'] === T_NAME_QUALIFIED || $tokens[$j]['id'] === T_NAME_FULLY_QUALIFIED || $tokens[$j]['id'] === T_STRING)) {
                $fqn .= $tokens[$j]['text'];
                ++$j;
            }
            $normalized = strtolower(ltrim($fqn, '\\'));
            if ($normalized !== self::LOWER_TREE_BUILDER) {
                continue;
            }
            if (isset($tokens[$j], $tokens[$j + 1]) && $tokens[$j]['id'] === T_AS && $tokens[$j + 1]['id'] === T_STRING) {
                $aliases[strtolower($tokens[$j + 1]['text'])] = true;
            } else {
                $aliases['treebuilder'] = true;
            }
        }

        return $aliases;
    }

    /**
     * @param list<array{id: int|string, text: string, start: int, end: int}> $tokens
     * @param array<string, true> $treeBuilderAliases
     */
    private static function parseRhsTreeBuilderType(array $tokens, int $rhsStart, array $treeBuilderAliases): ?string
    {
        $idx = $rhsStart;
        while (isset($tokens[$idx]) && $tokens[$idx]['id'] === '(') {
            ++$idx;
        }

        if (!isset($tokens[$idx]) || $tokens[$idx]['id'] !== T_NEW) {
            return null;
        }

        return self::parseNewTreeBuilderType($tokens, $idx, $treeBuilderAliases);
    }

    /**
     * @param list<array{id: int|string, text: string, start: int, end: int}> $tokens
     * @param array<string, true> $treeBuilderAliases
     */
    private static function parseNewTreeBuilderType(array $tokens, int $newIdx, array $treeBuilderAliases): ?string
    {
        $classIdx = $newIdx + 1;
        if (!isset($tokens[$classIdx])) {
            return null;
        }

        $classId = $tokens[$classIdx]['id'];
        if ($classId !== T_STRING && $classId !== T_NAME_QUALIFIED && $classId !== T_NAME_FULLY_QUALIFIED) {
            return null;
        }

        $className = strtolower(ltrim($tokens[$classIdx]['text'], '\\'));
        if (!isset($treeBuilderAliases[$className])) {
            return null;
        }

        $openParen = $classIdx + 1;
        if (!isset($tokens[$openParen]) || $tokens[$openParen]['id'] !== '(') {
            return 'array';
        }

        $closeParen = self::findMatchingDelimiter($tokens, $openParen, '(', ')');
        if ($closeParen === null) {
            return self::AMBIGUOUS;
        }

        return self::extractTypeFromArgTokens($tokens, $openParen + 1, $closeParen);
    }

    /**
     * @param list<array{id: int|string, text: string, start: int, end: int}> $tokens
     */
    private static function extractTypeFromArgTokens(array $tokens, int $start, int $end): string
    {
        /** @var list<array{int, int}> $argRanges */
        $argRanges = [];
        $argStart = $start;
        $depth = 0;
        for ($i = $start; $i < $end; ++$i) {
            $id = $tokens[$i]['id'];
            if ($id === '(' || $id === '[' || $id === '{') {
                ++$depth;
            } elseif ($id === ')' || $id === ']' || $id === '}') {
                --$depth;
            } elseif ($id === ',' && $depth === 0) {
                $argRanges[] = [$argStart, $i];
                $argStart = $i + 1;
            }
        }
        if ($argStart < $end) {
            $argRanges[] = [$argStart, $end];
        }

        $positional = 0;
        foreach ($argRanges as [$aStart, $aEnd]) {
            if ($aStart >= $aEnd) {
                continue;
            }

            if (
                $aStart + 2 <= $aEnd
                && $tokens[$aStart]['id'] === T_STRING
                && $tokens[$aStart + 1]['id'] === ':'
            ) {
                if ($tokens[$aStart]['text'] === 'type') {
                    return self::extractLiteralRootType($tokens, $aStart + 2, $aEnd);
                }
                continue;
            }

            if ($positional === 1) {
                return self::extractLiteralRootType($tokens, $aStart, $aEnd);
            }
            ++$positional;
        }

        return 'array';
    }

    /**
     * @param list<array{id: int|string, text: string, start: int, end: int}> $tokens
     */
    private static function extractLiteralRootType(array $tokens, int $start, int $end): string
    {
        while ($start < $end && $tokens[$start]['id'] === '(' && $tokens[$end - 1]['id'] === ')') {
            ++$start;
            --$end;
        }

        if ($end - $start !== 1 || $tokens[$start]['id'] !== T_CONSTANT_ENCAPSED_STRING) {
            return self::AMBIGUOUS;
        }

        $raw = $tokens[$start]['text'];
        if (strlen($raw) < 2) {
            return self::AMBIGUOUS;
        }

        $value = strtolower(substr($raw, 1, -1));
        if ($value === 'array' || isset(self::NON_ARRAY_TYPES[$value])) {
            return $value;
        }

        return self::AMBIGUOUS;
    }

    /**
     * @param list<array{id: int|string, text: string, start: int, end: int}> $tokens
     * @param array<string, true> $treeBuilderAliases
     * @param list<array{braceDepth: int, vars: array<string, string>, branchDepth: int}> $scopes
     */
    private static function resolveReceiverRootType(
        array $tokens,
        int $recvStart,
        int $recvEnd,
        array $treeBuilderAliases,
        array $scopes,
    ): ?string {
        $start = $recvStart;
        $end = $recvEnd;
        while ($start < $end && $tokens[$start]['id'] === '(' && $tokens[$end]['id'] === ')') {
            $match = self::findMatchingDelimiter($tokens, $start, '(', ')');
            if ($match !== $end) {
                break;
            }
            ++$start;
            --$end;
        }

        if ($start === $end && $tokens[$start]['id'] === T_VARIABLE) {
            $varName = $tokens[$start]['text'];
            $topVars = $scopes[count($scopes) - 1]['vars'];

            return $topVars[$varName] ?? null;
        }

        if ($tokens[$start]['id'] === T_NEW) {
            return self::parseNewTreeBuilderType($tokens, $start, $treeBuilderAliases);
        }

        return null;
    }

    /**
     * Finds the start token index of the receiver expression immediately preceding `->getRootNode()`.
     *
     * @param list<array{id: int|string, text: string, start: int, end: int}> $tokens
     */
    private static function findReceiverStartIndex(array $tokens, int $endIdx): ?int
    {
        if ($endIdx < 0 || !isset($tokens[$endIdx])) {
            return null;
        }

        if ($tokens[$endIdx]['id'] === T_VARIABLE) {
            return $endIdx;
        }

        if ($tokens[$endIdx]['id'] === ')') {
            $depth = 0;
            for ($k = $endIdx; $k >= 0; --$k) {
                $id = $tokens[$k]['id'];
                if ($id === ')') {
                    ++$depth;
                } elseif ($id === '(') {
                    --$depth;
                    if ($depth === 0) {
                        return $k;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param list<array{id: int|string, text: string, start: int, end: int}> $tokens
     */
    private static function findNextToken(array $tokens, int $from, int|string $target): ?int
    {
        $total = count($tokens);
        for ($i = $from; $i < $total; ++$i) {
            if ($tokens[$i]['id'] === $target) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param list<array{id: int|string, text: string, start: int, end: int}> $tokens
     */
    private static function findTokenInRange(array $tokens, int $start, int $end, int|string $target): ?int
    {
        for ($i = $start; $i < $end; ++$i) {
            if ($tokens[$i]['id'] === $target) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param list<array{id: int|string, text: string, start: int, end: int}> $tokens
     */
    private static function findMatchingDelimiter(array $tokens, int $openIdx, string $open, string $close): ?int
    {
        $depth = 0;
        $total = count($tokens);
        for ($i = $openIdx; $i < $total; ++$i) {
            $id = $tokens[$i]['id'];
            if ($id === $open) {
                ++$depth;
            } elseif ($id === $close) {
                --$depth;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * @param list<array{id: int|string, text: string, start: int, end: int}> $tokens
     */
    private static function skipArrowExpression(array $tokens, int $start): int
    {
        $depth = 0;
        $total = count($tokens);
        for ($i = $start; $i < $total; ++$i) {
            $id = $tokens[$i]['id'];
            if ($id === '(' || $id === '[' || $id === '{') {
                ++$depth;
            } elseif ($id === ')' || $id === ']' || $id === '}') {
                if ($depth === 0) {
                    return $i - 1;
                }
                --$depth;
            } elseif ($depth === 0 && ($id === ';' || $id === ',')) {
                return $i - 1;
            }
        }

        return $total - 1;
    }
}

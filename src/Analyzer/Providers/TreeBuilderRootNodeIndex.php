<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\CodebaseScanContext;
use Mago\Sdk\Analyzer\CodebaseScanHook;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_key_exists;
use function array_key_last;
use function count;
use function ltrim;
use function stripos;
use function strlen;
use function strpos;
use function strtolower;
use function substr;

/**
 * Indexes `TreeBuilder::getRootNode()` calls by exact byte span when the enclosing scope
 * constructs `TreeBuilder` with a literal root node type (such as `'scalar'` or `'enum'`).
 *
 * Cross-file span collisions with differing root types degrade conservatively to unresolved.
 */
final class TreeBuilderRootNodeIndex implements CodebaseScanHook
{
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

    /** @var array<string, string> spanKey => normalized root type ('scalar', 'enum', 'array', ...) */
    private array $rootTypeBySpan = [];

    /** @var array<string, true> */
    private array $ambiguousSpans = [];

    /** @var list<SourceFile> */
    private array $deferredCollisionFiles = [];

    private bool $hasNonArrayRoot = false;

    public function getTargets(): array
    {
        return ['**/*.php'];
    }

    public function scan(CodebaseScanContext $context): void
    {
        if ($context->firstBatch) {
            $this->rootTypeBySpan = [];
            $this->ambiguousSpans = [];
            $this->deferredCollisionFiles = [];
            $this->hasNonArrayRoot = false;
        }

        foreach ($context->files as $source) {
            if (stripos($source->contents, 'getRootNode') === false) {
                continue;
            }

            if (stripos($source->contents, 'TreeBuilder') === false || !self::mightHaveNonArrayRoot($source->contents)) {
                $this->deferredCollisionFiles[] = $source;
                continue;
            }

            $fileFoundNonArray = false;
            $fileCalls = [];
            foreach ($source->getNodes(NodeKind::MethodCall) as $call) {
                $method = self::calledMethodName($source, $call);
                if ($method === null || strtolower($method) !== 'getrootnode') {
                    continue;
                }

                $rootType = self::resolveRootNodeType($source, $call) ?? 'array';
                if ($rootType !== 'array' && isset(self::NON_ARRAY_TYPES[$rootType])) {
                    $fileFoundNonArray = true;
                } else {
                    $rootType = 'array';
                }
                $fileCalls[] = [$call->span->start . ':' . $call->span->end, $rootType];
            }

            if ($fileFoundNonArray) {
                $this->hasNonArrayRoot = true;
            }

            foreach ($fileCalls as [$key, $rootType]) {
                $this->recordSpanType($key, $rootType);
            }
        }

        if ($context->lastBatch) {
            if ($this->hasNonArrayRoot && $this->deferredCollisionFiles !== []) {
                foreach ($this->deferredCollisionFiles as $source) {
                    foreach ($source->getNodes(NodeKind::MethodCall) as $call) {
                        $method = self::calledMethodName($source, $call);
                        if ($method === null || strtolower($method) !== 'getrootnode') {
                            continue;
                        }
                        $this->recordSpanType($call->span->start . ':' . $call->span->end, 'array');
                    }
                }
            }
            $this->deferredCollisionFiles = [];
        }
    }

    public function rootTypeForSpan(Span $span): ?string
    {
        if (!$this->hasNonArrayRoot || ($span->start === 0 && $span->end === 0)) {
            return null;
        }

        $key = $span->start . ':' . $span->end;
        if (isset($this->ambiguousSpans[$key])) {
            return null;
        }

        return $this->rootTypeBySpan[$key] ?? null;
    }

    private function recordSpanType(string $key, string $rootType): void
    {
        if (isset($this->ambiguousSpans[$key])) {
            return;
        }

        if (array_key_exists($key, $this->rootTypeBySpan)) {
            if ($this->rootTypeBySpan[$key] !== $rootType) {
                $this->ambiguousSpans[$key] = true;
                unset($this->rootTypeBySpan[$key]);
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

    private static function resolveRootNodeType(SourceFile $source, Node $call): ?string
    {
        $receiver = self::unwrap($source, $source->getChildren($call)[0] ?? null);
        if ($receiver === null) {
            return null;
        }

        if (self::isTreeBuilderInstantiation($source, $receiver)) {
            return self::extractTreeBuilderType($source, $receiver);
        }

        $varName = self::directVariableName($source, $receiver);
        if ($varName === null) {
            return null;
        }

        $scope = self::enclosingScope($source, $call);
        $contents = $source->contents;
        $varLen = strlen($varName);
        $callStart = $call->span->start;
        $firstHit = strpos($contents, $varName, $scope->span->start);
        if ($firstHit === false || $firstHit + $varLen > $callStart) {
            return null;
        }

        $unconditionalType = null;
        $hasUnconditional = false;
        $branchTypes = [];

        /** @var list<array{Node, bool}> $stack */
        $stack = [];
        $scopeChildren = $source->getChildren($scope);
        for ($i = count($scopeChildren) - 1; $i >= 0; --$i) {
            $stack[] = [$scopeChildren[$i], false];
        }

        while (($entry = array_pop($stack)) !== null) {
            [$node, $inBranch] = $entry;
            $span = $node->span;
            if ($span->start >= $callStart || self::isScopeBoundary($node->kind)) {
                continue;
            }

            $hit = strpos($contents, $varName, $span->start);
            if ($hit === false || $hit + $varLen > $span->end) {
                continue;
            }

            $nextInBranch = $inBranch || self::isConditionalOrLoop($node->kind);
            $children = $source->getChildren($node);

            if ($node->kind === NodeKind::Assignment) {
                $lhs = $children[0] ?? null;
                if ($lhs !== null && self::directVariableName($source, $lhs) === $varName) {
                    $rhs = $children !== [] ? $children[array_key_last($children)] : null;
                    $rhsUnwrapped = self::unwrap($source, $rhs);
                    $assignedType = ($rhsUnwrapped !== null && self::isTreeBuilderInstantiation($source, $rhsUnwrapped))
                        ? (self::extractTreeBuilderType($source, $rhsUnwrapped) ?? 'array')
                        : null;

                    if (!$nextInBranch) {
                        $unconditionalType = $assignedType;
                        $hasUnconditional = true;
                        $branchTypes = [];
                    } else {
                        $branchTypes[] = $assignedType;
                    }
                }
            }

            for ($i = count($children) - 1; $i >= 0; --$i) {
                $stack[] = [$children[$i], $nextInBranch];
            }
        }

        if (!$hasUnconditional) {
            return null;
        }

        foreach ($branchTypes as $branchType) {
            if ($branchType !== $unconditionalType) {
                return null;
            }
        }

        return $unconditionalType;
    }

    private static function enclosingScope(SourceFile $source, Node $node): Node
    {
        $current = $source->getParent($node);
        while ($current !== null) {
            if (self::isScopeBoundary($current->kind)) {
                return $current;
            }

            $parent = $source->getParent($current);
            if ($parent === null) {
                return $current;
            }

            $current = $parent;
        }

        return $node;
    }

    private static function isScopeBoundary(NodeKind $kind): bool
    {
        return $kind === NodeKind::Method
            || $kind === NodeKind::Function
            || $kind === NodeKind::Closure
            || $kind === NodeKind::ArrowFunction;
    }

    private static function isConditionalOrLoop(NodeKind $kind): bool
    {
        return $kind === NodeKind::If
            || $kind === NodeKind::IfStatementBodyElseIfClause
            || $kind === NodeKind::IfStatementBodyElseClause
            || $kind === NodeKind::IfColonDelimitedBodyElseIfClause
            || $kind === NodeKind::IfColonDelimitedBodyElseClause
            || $kind === NodeKind::Switch
            || $kind === NodeKind::Match
            || $kind === NodeKind::Try
            || $kind === NodeKind::TryCatchClause
            || $kind === NodeKind::While
            || $kind === NodeKind::DoWhile
            || $kind === NodeKind::For
            || $kind === NodeKind::Foreach
            || $kind === NodeKind::Conditional;
    }

    private static function isTreeBuilderInstantiation(SourceFile $source, Node $node): bool
    {
        if ($node->kind !== NodeKind::Instantiation) {
            return false;
        }

        foreach ($source->getChildren($node) as $child) {
            $unwrapped = self::unwrap($source, $child);
            if (
                $unwrapped === null
                || $unwrapped->kind === NodeKind::Keyword
                || $unwrapped->kind === NodeKind::ArgumentList
            ) {
                continue;
            }

            if (
                $unwrapped->kind === NodeKind::Identifier
                || $unwrapped->kind === NodeKind::LocalIdentifier
                || $unwrapped->kind === NodeKind::QualifiedIdentifier
                || $unwrapped->kind === NodeKind::FullyQualifiedIdentifier
            ) {
                $resolved = $source->getResolvedName($unwrapped)?->name;
                $name = $resolved !== null ? ltrim($resolved, '\\') : ltrim($source->getText($unwrapped), '\\');

                return strtolower($name) === self::LOWER_TREE_BUILDER;
            }
        }

        return false;
    }

    private static function extractTreeBuilderType(SourceFile $source, Node $instantiation): ?string
    {
        $argumentList = null;
        foreach ($source->getChildren($instantiation) as $child) {
            if ($child->kind === NodeKind::ArgumentList) {
                $argumentList = $child;
                break;
            }
        }
        $argumentList ??= $source->getFirstDescendant($instantiation, NodeKind::ArgumentList);
        if ($argumentList === null) {
            return 'array';
        }

        $positionalIndex = 0;
        $hasTypeArg = false;
        foreach ($source->getChildren($argumentList) as $argument) {
            $variant = $source->getChildren($argument)[0] ?? null;
            if ($variant === null) {
                continue;
            }

            if ($variant->kind === NodeKind::PositionalArgument) {
                if ($positionalIndex === 1) {
                    $hasTypeArg = true;
                    $value = $source->getChildren($variant)[0] ?? null;
                    $literal = $value !== null ? self::extractLiteralString($source, $value) : null;

                    return $literal !== null ? strtolower($literal) : null;
                }
                ++$positionalIndex;
                continue;
            }

            if ($variant->kind === NodeKind::NamedArgument) {
                $argChildren = $source->getChildren($variant);
                $label = $argChildren[0] ?? null;
                $value = $argChildren[1] ?? null;
                if ($label !== null && $value !== null && $source->getText($label) === 'type') {
                    $hasTypeArg = true;
                    $literal = self::extractLiteralString($source, $value);

                    return $literal !== null ? strtolower($literal) : null;
                }
            }
        }

        return $hasTypeArg ? null : 'array';
    }

    private static function calledMethodName(SourceFile $source, Node $call): ?string
    {
        $member = $source->getChildren($call)[1] ?? null;
        if ($member === null) {
            return null;
        }
        $selector = $source->getChildren($member)[0] ?? null;

        return $selector !== null && $selector->kind === NodeKind::LocalIdentifier
            ? $source->getText($selector)
            : null;
    }

    private static function directVariableName(SourceFile $source, ?Node $node): ?string
    {
        $unwrapped = self::unwrap($source, $node);
        if ($unwrapped === null || $unwrapped->kind !== NodeKind::DirectVariable) {
            return null;
        }

        return $source->getText($unwrapped);
    }

    private static function extractLiteralString(SourceFile $source, Node $node): ?string
    {
        $node = self::unwrap($source, $node);
        if ($node === null) {
            return null;
        }

        $literal = $source->getLiteralString($node);
        if ($literal !== null) {
            return $literal;
        }

        $text = $source->getText($node);
        if (strlen($text) >= 2 && (($text[0] === "'" && $text[-1] === "'") || ($text[0] === '"' && $text[-1] === '"'))) {
            return substr($text, 1, -1);
        }

        return null;
    }

    private static function unwrap(SourceFile $source, ?Node $node): ?Node
    {
        while (
            $node !== null
            && (
                $node->kind === NodeKind::Expression
                || $node->kind === NodeKind::Call
                || $node->kind === NodeKind::Parenthesized
                || $node->kind === NodeKind::Variable
                || $node->kind === NodeKind::Literal
                || $node->kind === NodeKind::ConstantAccess
            )
        ) {
            $node = $source->getChildren($node)[0] ?? null;
        }

        return $node;
    }
}

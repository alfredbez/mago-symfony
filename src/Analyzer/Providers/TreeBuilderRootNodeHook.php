<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\MethodCallAnalysisHook;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\SourceLocation;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

/**
 * Checks calls on `TreeBuilder::getRootNode()` when the TreeBuilder uses a
 * non-array root node type such as `'scalar'` or `'boolean'`.
 */
final class TreeBuilderRootNodeHook implements MethodCallAnalysisHook
{
    private const TREE_BUILDER = 'Symfony\\Component\\Config\\Definition\\Builder\\TreeBuilder';
    private const LOWER_TREE_BUILDER = 'symfony\\component\\config\\definition\\builder\\treebuilder';
    private const NS = 'Symfony\\Component\\Config\\Definition\\Builder\\';

    private const NODE_TYPE_MAP = [
        'variable' => self::NS . 'VariableNodeDefinition',
        'scalar' => self::NS . 'ScalarNodeDefinition',
        'string' => self::NS . 'StringNodeDefinition',
        'boolean' => self::NS . 'BooleanNodeDefinition',
        'integer' => self::NS . 'IntegerNodeDefinition',
        'float' => self::NS . 'FloatNodeDefinition',
        'array' => self::NS . 'ArrayNodeDefinition',
        'enum' => self::NS . 'EnumNodeDefinition',
    ];

    /** Methods on NodeDefinition that transition away from the node class itself. */
    private const TYPE_CHANGING_METHODS = [
        'beforenormalization' => true,
        'end' => true,
        'getnode' => true,
        'validate' => true,
    ];

    private ?SourceFile $indexedSource = null;

    /** @var array<int, array<int, Node>> */
    private array $methodCallsBySpan = [];

    public function getTargets(): array
    {
        return [
            MethodTarget::exact(self::TREE_BUILDER, 'getRootNode'),
        ];
    }

    public function getRequirements(): array
    {
        return [
            FileAnalysisRequirement::SourceText,
            FileAnalysisRequirement::TargetSubtree,
        ];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        if ($context->node->kind !== NodeKind::MethodCall) {
            return;
        }

        $source = $context->analysis->getSourceFile();
        $call = $this->findMethodCallBySpan($source, $context->node->span);
        if ($call !== null) {
            $this->checkRootNodeCall($context, $source, $call);
        }
    }

    private function findMethodCallBySpan(SourceFile $source, Span $targetSpan): ?Node
    {
        $root = $source->getNode(0);
        if ($root->span->start <= $targetSpan->start && $root->span->end >= $targetSpan->end) {
            $found = $this->descendToMethodCall($source, $root, $targetSpan->start, $targetSpan->end);
            if ($found !== null) {
                return $found;
            }
        }

        if ($this->indexedSource !== $source) {
            $this->indexedSource = $source;
            $this->methodCallsBySpan = [];
            foreach ($source->getNodes(NodeKind::MethodCall) as $methodCall) {
                $this->methodCallsBySpan[$methodCall->span->start][$methodCall->span->end] ??= $methodCall;
            }
        }

        return $this->methodCallsBySpan[$targetSpan->start][$targetSpan->end] ?? null;
    }

    private function descendToMethodCall(SourceFile $source, Node $node, int $start, int $end): ?Node
    {
        $current = $node;
        while (true) {
            if ($current->kind === NodeKind::MethodCall && $current->span->start === $start && $current->span->end === $end) {
                return $current;
            }

            $next = null;
            foreach ($source->getChildren($current) as $child) {
                if ($child->span->start <= $start && $child->span->end >= $end) {
                    $next = $child;
                    break;
                }
            }

            if ($next === null) {
                return null;
            }

            $current = $next;
        }
    }

    private function checkRootNodeCall(NodeAnalysisContext $context, SourceFile $source, Node $call): void
    {
        $scope = $this->enclosingScope($source, $call);
        $instantiation = $this->resolveTreeBuilderInstantiation($source, $scope, $call);
        if ($instantiation === null) {
            return;
        }

        $nodeType = $this->extractTreeBuilderType($source, $instantiation);
        if ($nodeType === null) {
            return;
        }

        $lowerType = strtolower($nodeType);
        if ($lowerType === 'array') {
            return;
        }

        $actualNodeClass = self::NODE_TYPE_MAP[$lowerType] ?? null;
        if ($actualNodeClass === null || !$context->codebase->classExists($actualNodeClass)) {
            return;
        }

        $this->checkChainFromExpression($context, $source, $call, $actualNodeClass, $nodeType);

        $assignedVar = $this->assignedVariableName($source, $call);
        if ($assignedVar !== null) {
            $this->checkVariableUsages($context, $source, $scope, $assignedVar, $call->span->end, $actualNodeClass, $nodeType);
        }
    }

    private function enclosingScope(SourceFile $source, Node $node): Node
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

    private function resolveTreeBuilderInstantiation(SourceFile $source, Node $scope, Node $getRootCall): ?Node
    {
        $receiver = $this->unwrap($source, $source->getChildren($getRootCall)[0] ?? null);
        if ($receiver === null) {
            return null;
        }

        if ($this->isTreeBuilderInstantiation($source, $receiver)) {
            return $receiver;
        }

        $varName = $this->directVariableName($source, $receiver);
        if ($varName === null) {
            return null;
        }

        $contents = $source->contents;
        $varLen = strlen($varName);
        $callStart = $getRootCall->span->start;
        $firstHit = strpos($contents, $varName, $scope->span->start);
        if ($firstHit === false || $firstHit + $varLen > $callStart) {
            return null;
        }

        $latest = null;
        $stack = [];
        $scopeChildren = $source->getChildren($scope);
        for ($i = count($scopeChildren) - 1; $i >= 0; --$i) {
            $stack[] = $scopeChildren[$i];
        }

        while (($node = array_pop($stack)) !== null) {
            $span = $node->span;
            if ($span->start >= $callStart || self::isScopeBoundary($node->kind)) {
                continue;
            }

            $hit = strpos($contents, $varName, $span->start);
            if ($hit === false || $hit + $varLen > $span->end) {
                continue;
            }

            $children = $source->getChildren($node);
            if ($node->kind === NodeKind::Assignment) {
                $lhs = $children[0] ?? null;
                if ($lhs !== null && $this->directVariableName($source, $lhs) === $varName) {
                    $rhs = $children !== [] ? $children[array_key_last($children)] : null;
                    $rhsUnwrapped = $this->unwrap($source, $rhs);
                    $latest = ($rhsUnwrapped !== null && $this->isTreeBuilderInstantiation($source, $rhsUnwrapped))
                        ? $rhsUnwrapped
                        : null;
                }
            }

            for ($i = count($children) - 1; $i >= 0; --$i) {
                $stack[] = $children[$i];
            }
        }

        return $latest;
    }

    private function isTreeBuilderInstantiation(SourceFile $source, Node $node): bool
    {
        if ($node->kind !== NodeKind::Instantiation) {
            return false;
        }

        foreach ($source->getChildren($node) as $child) {
            $unwrapped = $this->unwrap($source, $child);
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

    private function extractTreeBuilderType(SourceFile $source, Node $instantiation): ?string
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
            return null;
        }

        $positionalIndex = 0;
        foreach ($source->getChildren($argumentList) as $argument) {
            $variant = $source->getChildren($argument)[0] ?? null;
            if ($variant === null) {
                continue;
            }

            if ($variant->kind === NodeKind::PositionalArgument) {
                if ($positionalIndex === 1) {
                    $value = $source->getChildren($variant)[0] ?? null;

                    return $value !== null ? $this->extractLiteralString($source, $value) : null;
                }
                $positionalIndex++;
                continue;
            }

            if ($variant->kind === NodeKind::NamedArgument) {
                $argChildren = $source->getChildren($variant);
                $label = $argChildren[0] ?? null;
                $value = $argChildren[1] ?? null;
                if ($label !== null && $value !== null && $source->getText($label) === 'type') {
                    return $this->extractLiteralString($source, $value);
                }
            }
        }

        return null;
    }

    private function extractLiteralString(SourceFile $source, Node $node): ?string
    {
        $node = $this->unwrap($source, $node);
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

    private function assignedVariableName(SourceFile $source, Node $call): ?string
    {
        $parent = $this->nearestNonWrapperAncestor($source, $call);
        if ($parent === null || $parent->kind !== NodeKind::Assignment) {
            return null;
        }

        $lhs = $source->getChildren($parent)[0] ?? null;

        return $this->directVariableName($source, $lhs);
    }

    private function checkVariableUsages(
        NodeAnalysisContext $context,
        SourceFile $source,
        Node $scope,
        string $varName,
        int $afterOffset,
        string $actualNodeClass,
        string $nodeType,
    ): void {
        $contents = $source->contents;
        $varLen = strlen($varName);
        $firstHit = strpos($contents, $varName, $afterOffset);
        if ($firstHit === false || $firstHit + $varLen > $scope->span->end) {
            return;
        }

        $stack = [];
        $scopeChildren = $source->getChildren($scope);
        for ($i = count($scopeChildren) - 1; $i >= 0; --$i) {
            $stack[] = $scopeChildren[$i];
        }

        while (($node = array_pop($stack)) !== null) {
            $span = $node->span;
            if ($span->end <= $afterOffset || self::isScopeBoundary($node->kind)) {
                continue;
            }

            $searchFrom = $span->start > $afterOffset ? $span->start : $afterOffset;
            $hit = strpos($contents, $varName, $searchFrom);
            if ($hit === false || $hit + $varLen > $span->end) {
                continue;
            }

            $children = $source->getChildren($node);
            if ($span->start > $afterOffset) {
                if ($node->kind === NodeKind::Assignment) {
                    $lhs = $children[0] ?? null;
                    if ($this->directVariableName($source, $lhs) === $varName) {
                        break;
                    }
                } elseif ($node->kind === NodeKind::MethodCall) {
                    if ($this->directVariableName($source, $children[0] ?? null) === $varName) {
                        if ($this->checkSingleMethodCall($context, $source, $node, $children[1] ?? null, $actualNodeClass, $nodeType)) {
                            $this->checkChainFromExpression($context, $source, $node, $actualNodeClass, $nodeType);
                        }
                    }
                }
            }

            for ($i = count($children) - 1; $i >= 0; --$i) {
                $stack[] = $children[$i];
            }
        }
    }

    private function checkChainFromExpression(
        NodeAnalysisContext $context,
        SourceFile $source,
        Node $expression,
        string $actualNodeClass,
        string $nodeType,
    ): void {
        $current = $expression;
        while (true) {
            $parent = $this->nearestNonWrapperAncestor($source, $current);
            if ($parent === null || $parent->kind !== NodeKind::MethodCall) {
                break;
            }

            $children = $source->getChildren($parent);
            $receiver = $this->unwrap($source, $children[0] ?? null);
            if ($receiver === null || $receiver->id !== $current->id) {
                break;
            }

            if (!$this->checkSingleMethodCall($context, $source, $parent, $children[1] ?? null, $actualNodeClass, $nodeType)) {
                break;
            }

            $current = $parent;
        }
    }

    private function checkSingleMethodCall(
        NodeAnalysisContext $context,
        SourceFile $source,
        Node $callNode,
        ?Node $member,
        string $actualNodeClass,
        string $nodeType,
    ): bool {
        if ($member === null) {
            return false;
        }

        $selector = $source->getChildren($member)[0] ?? null;
        if ($selector === null || $selector->kind !== NodeKind::LocalIdentifier) {
            return false;
        }

        $methodName = $source->getText($selector);
        if (!$context->codebase->methodExists($actualNodeClass, $methodName)) {
            $context->report(
                Level::Error,
                'invalid-root-node-method',
                Issue::at(
                    "Method `{$methodName}` does not exist on type `{$actualNodeClass}` (root node type `{$nodeType}`).",
                    new SourceLocation($context->analysis->file, $member->span),
                ),
            );

            return false;
        }

        return !isset(self::TYPE_CHANGING_METHODS[strtolower($methodName)]);
    }

    private function directVariableName(SourceFile $source, ?Node $node): ?string
    {
        $unwrapped = $this->unwrap($source, $node);
        if ($unwrapped === null || $unwrapped->kind !== NodeKind::DirectVariable) {
            return null;
        }

        return $source->getText($unwrapped);
    }

    private function nearestNonWrapperAncestor(SourceFile $source, Node $node): ?Node
    {
        $parent = $source->getParent($node);
        while (
            $parent !== null
            && (
                $parent->kind === NodeKind::Expression
                || $parent->kind === NodeKind::Call
                || $parent->kind === NodeKind::Parenthesized
            )
        ) {
            $parent = $source->getParent($parent);
        }

        return $parent;
    }

    private function unwrap(SourceFile $source, ?Node $node): ?Node
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

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
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

/**
 * Checks calls on `TreeBuilder::getRootNode()` when the TreeBuilder uses a
 * non-array root node type such as `'scalar'` or `'boolean'`.
 *
 * `RootNodeReturnTypeProvider` narrows `getRootNode()` to `ArrayNodeDefinition`
 * (the default `'array'` root type), because `ReturnTypeProviderContext` cannot
 * inspect the constructor arguments of `new TreeBuilder($name, $type)`. This
 * hook reads the literal `$type` from `new TreeBuilder(...)` in the same scope
 * and flags methods on the root node that do not exist on that concrete node
 * definition class.
 */
final class TreeBuilderRootNodeHook implements MethodCallAnalysisHook
{
    private const TREE_BUILDER = 'Symfony\\Component\\Config\\Definition\\Builder\\TreeBuilder';
    private const NS = 'Symfony\\Component\\Config\\Definition\\Builder\\';

    private const NODE_TYPE_MAP = [
        'variable' => self::NS . 'VariableNodeDefinition',
        'scalar' => self::NS . 'ScalarNodeDefinition',
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
        $targetSpan = $context->node->span;
        foreach ($source->getNodes(NodeKind::MethodCall) as $call) {
            if ($call->span->start === $targetSpan->start && $call->span->end === $targetSpan->end) {
                $this->checkRootNodeCall($context, $source, $call);
                break;
            }
        }
    }

    private function checkRootNodeCall(NodeAnalysisContext $context, SourceFile $source, Node $call): void
    {
        $scope = $this->enclosingScope($source, $call);
        $callExpr = CallExpression::fromNode($source, $call);
        $instantiation = $this->resolveTreeBuilderInstantiation($source, $scope, $callExpr);
        if ($instantiation === null) {
            return;
        }

        $nodeType = $this->extractTreeBuilderType($source, $instantiation);
        if ($nodeType === null || strtolower($nodeType) === 'array') {
            return;
        }

        $actualNodeClass = self::NODE_TYPE_MAP[strtolower($nodeType)] ?? null;
        if ($actualNodeClass === null || !$context->codebase->classExists($actualNodeClass)) {
            return;
        }

        // Direct fluent chain: $treeBuilder->getRootNode()->children()
        $this->checkChainFromExpression($context, $source, $call, $actualNodeClass, $nodeType);

        // Local variable assignment: $root = $treeBuilder->getRootNode(); $root->children();
        $assignedVar = $this->assignedVariableName($source, $call);
        if ($assignedVar !== null) {
            $this->checkVariableUsages($context, $source, $scope, $assignedVar, $call->span->end, $actualNodeClass, $nodeType);
        }
    }

    private function enclosingScope(SourceFile $source, Node $node): Node
    {
        foreach ($source->getAncestors($node) as $ancestor) {
            if (
                $ancestor->kind === NodeKind::Method
                || $ancestor->kind === NodeKind::Function
                || $ancestor->kind === NodeKind::Closure
                || $ancestor->kind === NodeKind::ArrowFunction
            ) {
                return $ancestor;
            }
        }

        return $source->getNodes(NodeKind::Program)[0];
    }

    private function resolveTreeBuilderInstantiation(SourceFile $source, Node $scope, CallExpression $getRootCall): ?Node
    {
        $receiver = $this->unwrap($source, $getRootCall->receiver);
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

        $latest = null;
        foreach ($source->getDescendants($scope, NodeKind::Assignment) as $assignment) {
            if ($assignment->span->start >= $getRootCall->node->span->start) {
                break;
            }

            if ($this->enclosingScope($source, $assignment)->id !== $scope->id) {
                continue;
            }

            $children = $source->getChildren($assignment);
            $lhs = $children[0] ?? null;
            $rhs = $children !== [] ? $children[array_key_last($children)] : null;
            if ($lhs === null || $this->directVariableName($source, $lhs) !== $varName) {
                continue;
            }

            $rhsUnwrapped = $this->unwrap($source, $rhs);
            $latest = ($rhsUnwrapped !== null && $this->isTreeBuilderInstantiation($source, $rhsUnwrapped))
                ? $rhsUnwrapped
                : null;
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

                return strtolower($name) === strtolower(self::TREE_BUILDER);
            }
        }

        return false;
    }

    private function extractTreeBuilderType(SourceFile $source, Node $instantiation): ?string
    {
        $argumentList = $source->getFirstDescendant($instantiation, NodeKind::ArgumentList);
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
                $value = $source->getChildren($variant)[0] ?? null;
                if ($positionalIndex === 1 && $value !== null) {
                    return $this->extractLiteralString($source, $value);
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
        foreach ($source->getDescendants($scope) as $node) {
            if ($node->span->start <= $afterOffset || $this->enclosingScope($source, $node)->id !== $scope->id) {
                continue;
            }

            if ($node->kind === NodeKind::Assignment) {
                $lhs = $source->getChildren($node)[0] ?? null;
                if ($this->directVariableName($source, $lhs) === $varName) {
                    break;
                }
            }

            if ($node->kind !== NodeKind::MethodCall) {
                continue;
            }

            $callExpr = CallExpression::fromNode($source, $node);
            if ($this->directVariableName($source, $callExpr->receiver) !== $varName) {
                continue;
            }

            if ($this->checkSingleMethodCall($context, $source, $callExpr, $actualNodeClass, $nodeType)) {
                $this->checkChainFromExpression($context, $source, $node, $actualNodeClass, $nodeType);
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

            $callExpr = CallExpression::fromNode($source, $parent);
            $receiver = $this->unwrap($source, $callExpr->receiver);
            if ($receiver === null || $receiver->id !== $current->id) {
                break;
            }

            if (!$this->checkSingleMethodCall($context, $source, $callExpr, $actualNodeClass, $nodeType)) {
                break;
            }

            $current = $parent;
        }
    }

    private function checkSingleMethodCall(
        NodeAnalysisContext $context,
        SourceFile $source,
        CallExpression $callExpr,
        string $actualNodeClass,
        string $nodeType,
    ): bool {
        $methodName = $callExpr->getName($source);
        if ($methodName === null) {
            return false;
        }

        if (!$context->codebase->methodExists($actualNodeClass, $methodName)) {
            $span = $callExpr->member?->span ?? $callExpr->node->span;
            $context->report(
                Level::Error,
                'invalid-root-node-method',
                Issue::at(
                    "Method `{$methodName}` does not exist on type `{$actualNodeClass}` (root node type `{$nodeType}`).",
                    new SourceLocation($context->analysis->file, $span),
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

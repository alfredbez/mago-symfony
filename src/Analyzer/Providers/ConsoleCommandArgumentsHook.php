<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\ClassLikeAnalysisHook;
use Mago\Sdk\Analyzer\ClassLikeTarget;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\SourceLocation;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

/**
 * Validates Symfony Console Command configure() declarations and literal
 * $input->getArgument() / $input->getOption() calls against those declarations.
 *
 * @internal
 */
final class ConsoleCommandArgumentsHook implements ClassLikeAnalysisHook
{
    public const INVALID_DEFINITION_ISSUE = 'invalid-console-input-definition';

    private const COMMAND = 'Symfony\\Component\\Console\\Command\\Command';
    private const APPLICATION = 'Symfony\\Component\\Console\\Application';
    private const FRAMEWORK_APPLICATION = 'Symfony\\Bundle\\FrameworkBundle\\Console\\Application';
    private const OUTPUT_INTERFACE = 'Symfony\\Component\\Console\\Output\\OutputInterface';
    private const INPUT_INTERFACE = 'symfony\\component\\console\\input\\inputinterface';

    private const CONSTRUCTOR_INPUT_METHODS = [
        'addargument' => true,
        'addoption' => true,
        'setdefinition' => true,
        'getdefinition' => true,
        'getnativedefinition' => true,
    ];

    private const FALLBACK_BASE_APPLICATION_OPTIONS = [
        'help' => true,
        'quiet' => true,
        'verbose' => true,
        'version' => true,
        'ansi' => true,
        'no-ansi' => true,
        'no-interaction' => true,
    ];

    private const FALLBACK_FRAMEWORK_APPLICATION_OPTIONS = [
        'env' => true,
        'no-debug' => true,
        'profile' => true,
    ];

    /** @var array<string, array<string, true>> */
    private array $applicationOptionsCache = [];

    public function getTargets(): array
    {
        return [
            ClassLikeTarget::descendantsOf(self::COMMAND),
        ];
    }

    public function getRequirements(): array
    {
        return [
            FileAnalysisRequirement::SourceText,
            FileAnalysisRequirement::TargetSubtree,
            FileAnalysisRequirement::ExpressionTypes,
        ];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        $source = $context->source;
        $classNode = $context->node;
        if ($classNode->kind !== NodeKind::Class_) {
            return;
        }

        $configure = null;
        $constructor = null;
        $methods = [];
        $declaredMethods = [];
        foreach ($source->getDescendants($classNode, NodeKind::Method) as $method) {
            if ($this->isNestedClass($source, $classNode, $method)) {
                continue;
            }
            $name = strtolower($this->methodName($source, $method) ?? '');
            if ($name === '') {
                continue;
            }
            $declaredMethods[$name] = $method;
            if ($name === 'configure') {
                $configure = $method;
            } elseif ($name === '__construct') {
                $constructor = $method;
                $methods[] = $method;
            } else {
                $methods[] = $method;
            }
        }

        if ($configure === null) {
            return;
        }

        $definition = ConsoleCommandDefinition::parseConfigure($source, $configure, $context->analysis);

        foreach ($definition->definitionErrors as $error) {
            $context->report(
                Level::Error,
                self::INVALID_DEFINITION_ISSUE,
                Issue::at(
                    $error['message'],
                    new SourceLocation($context->analysis->file, $error['span']),
                ),
            );
        }

        if (!$definition->isComplete || $methods === []) {
            return;
        }

        $className = $this->className($source, $classNode);
        $hasCustomParent = $this->hasCustomCommandParent($context, $className);
        if ($constructor !== null) {
            $classIsFinal = $this->hasModifier($source, $classNode, 'final');
            $visited = ['__construct' => true];
            if ($this->methodMayConfigureInputs($context, $source, $classNode, $constructor, $declaredMethods, $className, $classIsFinal, $hasCustomParent, $visited)) {
                return;
            }
        }

        $checkEmptyArguments = !$hasCustomParent;
        $checkEmptyOptions = !$hasCustomParent;
        $defaultApplicationOptions = $this->resolveDefaultApplicationOptions($context);
        foreach ($methods as $method) {
            $this->checkInputCalls($context, $source, $method, $className, $definition, $checkEmptyArguments, $checkEmptyOptions, $defaultApplicationOptions);
        }
    }

    /**
     * @param array<string, Node> $declaredMethods
     * @param array<string, true> $visited
     */
    private function methodMayConfigureInputs(
        NodeAnalysisContext $context,
        SourceFile $source,
        Node $classNode,
        Node $method,
        array $declaredMethods,
        string $className,
        bool $classIsFinal,
        bool $hasCustomParent,
        array &$visited,
    ): bool {
        foreach ([NodeKind::MethodCall, NodeKind::NullSafeMethodCall, NodeKind::MethodPartialApplication] as $callKind) {
            foreach ($source->getDescendants($method, $callKind) as $call) {
                if ($this->isNestedClass($source, $classNode, $call)) {
                    continue;
                }

                $calledRaw = ConsoleCommandDefinition::calledMethodName($source, $call);
                $called = $calledRaw !== null ? strtolower($calledRaw) : null;
                if ($called === 'addargument' || $called === 'addoption' || $called === 'setdefinition') {
                    return true;
                }

                $rawReceiver = $source->getChildren($call)[0] ?? null;
                if (!$this->isThisCommandReceiver($context, $source, $rawReceiver, $className)) {
                    continue;
                }

                if ($called === null || isset(self::CONSTRUCTOR_INPUT_METHODS[$called])) {
                    return true;
                }

                if (isset($declaredMethods[$called])) {
                    if ($called === 'configure') {
                        continue;
                    }
                    $helper = $declaredMethods[$called];
                    if (!$classIsFinal && !$this->isNonOverridableMethod($source, $helper)) {
                        return true;
                    }
                    if (isset($visited[$called])) {
                        continue;
                    }
                    $visited[$called] = true;
                    if ($this->methodMayConfigureInputs($context, $source, $classNode, $helper, $declaredMethods, $className, $classIsFinal, $hasCustomParent, $visited)) {
                        return true;
                    }
                    continue;
                }

                if ($this->isUnoverriddenSafeCommandMethod($context, $className, $called)) {
                    continue;
                }

                return true;
            }
        }

        foreach ([NodeKind::StaticMethodCall, NodeKind::StaticMethodPartialApplication] as $staticCallKind) {
            foreach ($source->getDescendants($method, $staticCallKind) as $call) {
                if ($this->isNestedClass($source, $classNode, $call)) {
                    continue;
                }

                $targetKind = $this->staticCallTargetKind($context, $source, $call, $className);
                if ($targetKind === 'other') {
                    continue;
                }

                $calledRaw = ConsoleCommandDefinition::calledMethodName($source, $call);
                $called = $calledRaw !== null ? strtolower($calledRaw) : null;
                if ($called === null || isset(self::CONSTRUCTOR_INPUT_METHODS[$called])) {
                    return true;
                }

                if ($targetKind === 'parent') {
                    if (!$hasCustomParent && ($called === '__construct' || $called === 'configure' || $this->isUnoverriddenSafeCommandMethod($context, $className, $called))) {
                        continue;
                    }

                    return true;
                }

                if (isset($declaredMethods[$called])) {
                    if ($called === 'configure') {
                        continue;
                    }
                    $helper = $declaredMethods[$called];
                    if ($this->hasModifier($source, $helper, 'abstract')) {
                        return true;
                    }
                    if ($targetKind === 'static' && !$classIsFinal && !$this->isNonOverridableMethod($source, $helper)) {
                        return true;
                    }
                    if (isset($visited[$called])) {
                        continue;
                    }
                    $visited[$called] = true;
                    if ($this->methodMayConfigureInputs($context, $source, $classNode, $helper, $declaredMethods, $className, $classIsFinal, $hasCustomParent, $visited)) {
                        return true;
                    }
                    continue;
                }

                if ($this->isUnoverriddenSafeCommandMethod($context, $className, $called)) {
                    continue;
                }

                return true;
            }
        }

        return false;
    }

    private function isThisCommandReceiver(NodeAnalysisContext $context, SourceFile $source, ?Node $rawReceiver, string $className): bool
    {
        $receiver = ConsoleCommandDefinition::unwrap($source, $rawReceiver);
        if ($receiver === null) {
            return false;
        }
        if ($source->getText($receiver) === '$this') {
            return true;
        }
        if ($receiver->kind === NodeKind::MethodCall || $receiver->kind === NodeKind::NullSafeMethodCall) {
            $innerMethod = ConsoleCommandDefinition::calledMethodName($source, $receiver);
            if ($innerMethod !== null && ConsoleCommandDefinition::isKnownSafeConfigureMethod($innerMethod)) {
                return $this->isThisCommandReceiver($context, $source, $source->getChildren($receiver)[0] ?? null, $className);
            }
        }

        $targetLower = strtolower(ltrim($className, '\\'));
        foreach ([$rawReceiver, $receiver] as $candidate) {
            if ($candidate === null) {
                continue;
            }
            $type = $context->analysis->getExpressionType($candidate);
            if ($type === null) {
                continue;
            }
            foreach ($type->atomicTypes as $atomic) {
                if (!property_exists($atomic, 'name') || !is_string($atomic->name)) {
                    continue;
                }
                if (strtolower(ltrim($atomic->name, '\\')) === $targetLower) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isUnoverriddenSafeCommandMethod(NodeAnalysisContext $context, string $className, string $calledLower): bool
    {
        if (!ConsoleCommandDefinition::isKnownSafeConfigureMethod($calledLower)) {
            return false;
        }

        $declaring = $context->codebase->getDeclaringMethod($className, $calledLower);
        if ($declaring === null || $declaring->identifier->class === null) {
            return false;
        }

        return strtolower(ltrim($declaring->identifier->class, '\\')) === strtolower(self::COMMAND);
    }

    private function staticCallTargetKind(NodeAnalysisContext $context, SourceFile $source, Node $call, string $className): string
    {
        $rawTarget = $source->getChildren($call)[0] ?? null;
        $target = ConsoleCommandDefinition::unwrap($source, $rawTarget);
        if ($target === null) {
            return 'other';
        }

        $text = strtolower(trim($source->getText($target)));
        if ($text === 'parent' || $text === 'self' || $text === 'static') {
            return $text;
        }

        if ($this->isThisCommandReceiver($context, $source, $rawTarget, $className)) {
            return 'static';
        }

        if (
            $target->kind === NodeKind::Identifier
            || $target->kind === NodeKind::LocalIdentifier
            || $target->kind === NodeKind::QualifiedIdentifier
            || $target->kind === NodeKind::FullyQualifiedIdentifier
        ) {
            $resolved = $source->getResolvedName($target)?->name;
            $name = strtolower(ltrim($resolved ?? $source->getText($target), '\\'));
            if ($name === strtolower(ltrim($className, '\\'))) {
                return 'self';
            }
        }

        return 'other';
    }

    private function hasModifier(SourceFile $source, Node $node, string $modifier): bool
    {
        foreach ($source->getChildren($node) as $child) {
            if ($child->kind === NodeKind::Modifier && strtolower(trim($source->getText($child))) === $modifier) {
                return true;
            }
        }

        return false;
    }

    private function isNonOverridableMethod(SourceFile $source, Node $method): bool
    {
        foreach ($source->getChildren($method) as $child) {
            if ($child->kind !== NodeKind::Modifier) {
                continue;
            }
            $mod = strtolower(trim($source->getText($child)));
            if ($mod === 'private' || $mod === 'final') {
                return true;
            }
        }

        return false;
    }

    private function hasCustomCommandParent(NodeAnalysisContext $context, string $className): bool
    {
        $meta = $context->codebase->getClass($className);
        if ($meta === null || $meta->directParentClass === null) {
            return false;
        }

        return strtolower(ltrim($meta->directParentClass, '\\')) !== strtolower(self::COMMAND);
    }

    /**
     * @param array<string, true> $defaultApplicationOptions
     */
    private function checkInputCalls(
        NodeAnalysisContext $context,
        SourceFile $source,
        Node $method,
        string $className,
        ConsoleCommandDefinition $definition,
        bool $checkEmptyArguments,
        bool $checkEmptyOptions,
        array $defaultApplicationOptions,
    ): void {
        foreach ($source->getDescendants($method, NodeKind::MethodCall) as $call) {
            $called = strtolower(ConsoleCommandDefinition::calledMethodName($source, $call) ?? '');
            if ($called !== 'getargument' && $called !== 'getoption') {
                continue;
            }

            $rawReceiver = $source->getChildren($call)[0] ?? null;
            $receiver = ConsoleCommandDefinition::unwrap($source, $rawReceiver);
            if ($rawReceiver === null || $receiver === null || (!$this->isInputReceiver($context, $rawReceiver) && !$this->isInputReceiver($context, $receiver))) {
                continue;
            }

            $args = ConsoleCommandDefinition::extractCallArguments($source, $call);
            $argNode = ConsoleCommandDefinition::resolveArgument($args, 0, 'name');
            $name = $argNode !== null ? ConsoleCommandDefinition::extractLiteralString($source, $argNode, $context->analysis) : null;
            if ($name === null) {
                continue;
            }

            if ($called === 'getargument' && ($definition->arguments !== [] || $checkEmptyArguments) && !isset($definition->arguments[$name])) {
                if ($name === 'command') {
                    continue;
                }
                if ($this->isGuardedByHasCheck($context, $source, $method, $call, $receiver, 'hasargument', $name)) {
                    continue;
                }
                $context->report(
                    Level::Error,
                    'unknown-console-argument',
                    Issue::at(
                        "Console argument `{$name}` is not declared in `{$className}::configure()`.",
                        new SourceLocation($context->analysis->file, $argNode->span),
                    ),
                );
            } elseif (
                $called === 'getoption'
                && ($definition->options !== [] || $checkEmptyOptions)
                && !$definition->hasDeclaredOption($name)
                && !isset($defaultApplicationOptions[$name])
            ) {
                if ($this->isGuardedByHasCheck($context, $source, $method, $call, $receiver, 'hasoption', $name)) {
                    continue;
                }
                $context->report(
                    Level::Error,
                    'unknown-console-option',
                    Issue::at(
                        "Console option `{$name}` is not declared in `{$className}::configure()`.",
                        new SourceLocation($context->analysis->file, $argNode->span),
                    ),
                );
            }
        }
    }

    private function isGuardedByHasCheck(
        NodeAnalysisContext $context,
        SourceFile $source,
        Node $method,
        Node $call,
        Node $receiver,
        string $guardMethod,
        string $expectedKey,
    ): bool {
        if ($receiver->kind !== NodeKind::DirectVariable) {
            return false;
        }

        $varName = $source->getText($receiver);
        if ($varName === '') {
            return false;
        }

        $current = $call;
        while (true) {
            $parent = $source->getParent($current);
            if ($parent === null || $parent->id === $method->id) {
                break;
            }
            if (
                $parent->kind === NodeKind::Closure
                || $parent->kind === NodeKind::ArrowFunction
                || $parent->kind === NodeKind::AnonymousClass
            ) {
                break;
            }

            if ($parent->kind === NodeKind::Binary) {
                $children = $source->getChildren($parent);
                if (count($children) === 3 && $children[2]->id === $current->id) {
                    $op = strtolower(trim($source->getText($children[1])));
                    if (
                        ($op === '&&' || $op === 'and')
                        && $this->conditionAssertsHas($context, $source, $children[0], $varName, $guardMethod, $expectedKey, true)
                        && !$this->variableReassignedInRange($source, $parent, $varName, $children[0]->span->end, $call->span->start)
                    ) {
                        return true;
                    }
                }
            } elseif ($parent->kind === NodeKind::Conditional) {
                $children = $source->getChildren($parent);
                if (
                    count($children) === 3
                    && $children[1]->id === $current->id
                    && $this->conditionAssertsHas($context, $source, $children[0], $varName, $guardMethod, $expectedKey, true)
                    && !$this->variableReassignedInRange($source, $children[1], $varName, $children[0]->span->end, $call->span->start)
                ) {
                    return true;
                }
            } elseif ($parent->kind === NodeKind::IfStatementBody) {
                $bodyChildren = $source->getChildren($parent);
                if (isset($bodyChildren[0]) && $bodyChildren[0]->id === $current->id) {
                    $ifNode = $this->enclosingIfForBody($source, $parent);
                    if ($ifNode !== null) {
                        $cond = $this->firstChildOfKind($source, $ifNode, NodeKind::Expression);
                        if (
                            $cond !== null
                            && $this->conditionAssertsHas($context, $source, $cond, $varName, $guardMethod, $expectedKey, true)
                            && !$this->variableReassignedInRange($source, $bodyChildren[0], $varName, $cond->span->end, $call->span->start)
                        ) {
                            return true;
                        }
                    }
                }
            } elseif ($parent->kind === NodeKind::IfColonDelimitedBody) {
                if (
                    $current->kind !== NodeKind::IfColonDelimitedBodyElseIfClause
                    && $current->kind !== NodeKind::IfColonDelimitedBodyElseClause
                ) {
                    $ifNode = $this->enclosingIfForBody($source, $parent);
                    if ($ifNode !== null) {
                        $cond = $this->firstChildOfKind($source, $ifNode, NodeKind::Expression);
                        if (
                            $cond !== null
                            && $this->conditionAssertsHas($context, $source, $cond, $varName, $guardMethod, $expectedKey, true)
                            && !$this->variableReassignedInRange($source, $parent, $varName, $cond->span->end, $call->span->start)
                        ) {
                            return true;
                        }
                    }
                }
            } elseif (
                $parent->kind === NodeKind::IfStatementBodyElseIfClause
                || $parent->kind === NodeKind::IfColonDelimitedBodyElseIfClause
            ) {
                $cond = $this->firstChildOfKind($source, $parent, NodeKind::Expression);
                if ($cond !== null && $cond->id !== $current->id) {
                    if (
                        $this->conditionAssertsHas($context, $source, $cond, $varName, $guardMethod, $expectedKey, true)
                        && !$this->variableReassignedInRange($source, $parent, $varName, $cond->span->end, $call->span->start)
                    ) {
                        return true;
                    }
                }
            } elseif ($parent->kind === NodeKind::Block) {
                if ($this->hasPrecedingEarlyExitGuard($context, $source, $parent, $current, $call, $varName, $guardMethod, $expectedKey)) {
                    return true;
                }
            }

            $current = $parent;
        }

        return false;
    }

    private function firstChildOfKind(SourceFile $source, Node $parent, NodeKind $kind): ?Node
    {
        foreach ($source->getChildren($parent) as $child) {
            if ($child->kind === $kind) {
                return $child;
            }
        }

        return null;
    }

    private function enclosingIfForBody(SourceFile $source, Node $bodyNode): ?Node
    {
        $parent = $source->getParent($bodyNode);
        if ($parent !== null && $parent->kind === NodeKind::IfBody) {
            $parent = $source->getParent($parent);
        }

        return ($parent !== null && $parent->kind === NodeKind::If) ? $parent : null;
    }

    private function hasPrecedingEarlyExitGuard(
        NodeAnalysisContext $context,
        SourceFile $source,
        Node $block,
        Node $currentStmt,
        Node $call,
        string $varName,
        string $guardMethod,
        string $expectedKey,
    ): bool {
        foreach ($source->getChildren($block) as $sibling) {
            if ($sibling->id === $currentStmt->id || $sibling->span->start >= $currentStmt->span->start) {
                break;
            }
            $stmt = $sibling->kind === NodeKind::Statement ? ($source->getChildren($sibling)[0] ?? null) : $sibling;
            if ($stmt === null || $stmt->kind !== NodeKind::If) {
                continue;
            }
            $cond = $this->firstChildOfKind($source, $stmt, NodeKind::Expression);
            $body = $this->firstChildOfKind($source, $stmt, NodeKind::IfBody);
            if ($cond === null || $body === null) {
                continue;
            }
            if (
                $this->ifBodyUnconditionallyExitsWithoutElse($source, $body)
                && $this->conditionAssertsHas($context, $source, $cond, $varName, $guardMethod, $expectedKey, false)
                && !$this->variableReassignedInRange($source, $block, $varName, $stmt->span->end, $call->span->start)
            ) {
                return true;
            }
        }

        return false;
    }

    private function ifBodyUnconditionallyExitsWithoutElse(SourceFile $source, Node $ifBody): bool
    {
        $inner = $ifBody->kind === NodeKind::IfBody ? ($source->getChildren($ifBody)[0] ?? null) : $ifBody;
        if ($inner === null) {
            return false;
        }

        if ($inner->kind === NodeKind::IfStatementBody) {
            $children = $source->getChildren($inner);
            if (count($children) !== 1) {
                return false;
            }

            return $this->statementUnconditionallyExits($source, $children[0]);
        }

        if ($inner->kind === NodeKind::IfColonDelimitedBody) {
            $stmts = [];
            foreach ($source->getChildren($inner) as $child) {
                if (
                    $child->kind === NodeKind::IfColonDelimitedBodyElseClause
                    || $child->kind === NodeKind::IfColonDelimitedBodyElseIfClause
                ) {
                    return false;
                }
                $stmts[] = $child;
            }
            $last = $stmts !== [] ? $stmts[count($stmts) - 1] : null;

            return $last !== null && $this->statementUnconditionallyExits($source, $last);
        }

        return false;
    }

    private function statementUnconditionallyExits(SourceFile $source, Node $stmt): bool
    {
        if ($stmt->kind === NodeKind::Statement) {
            $stmt = $source->getChildren($stmt)[0] ?? null;
            if ($stmt === null) {
                return false;
            }
        }

        if ($stmt->kind === NodeKind::Block) {
            $children = $source->getChildren($stmt);
            $last = $children !== [] ? $children[count($children) - 1] : null;

            return $last !== null && $this->statementUnconditionallyExits($source, $last);
        }

        if (
            $stmt->kind === NodeKind::Return
            || $stmt->kind === NodeKind::Throw
            || $stmt->kind === NodeKind::Continue
            || $stmt->kind === NodeKind::Break
        ) {
            return true;
        }

        if ($stmt->kind === NodeKind::ExpressionStatement) {
            $expr = ConsoleCommandDefinition::unwrap($source, $source->getChildren($stmt)[0] ?? null);

            return $expr !== null && $expr->kind === NodeKind::Throw;
        }

        return false;
    }

    private function conditionAssertsHas(
        NodeAnalysisContext $context,
        SourceFile $source,
        Node $expr,
        string $varName,
        string $guardMethod,
        string $expectedKey,
        bool $positive,
    ): bool {
        $node = ConsoleCommandDefinition::unwrap($source, $expr);
        if ($node === null) {
            return false;
        }

        if ($node->kind === NodeKind::UnaryPrefix) {
            $children = $source->getChildren($node);
            if (count($children) === 2 && trim($source->getText($children[0])) === '!') {
                return $this->conditionAssertsHas($context, $source, $children[1], $varName, $guardMethod, $expectedKey, !$positive);
            }

            return false;
        }

        if ($node->kind === NodeKind::Binary) {
            $children = $source->getChildren($node);
            if (count($children) !== 3) {
                return false;
            }
            $op = strtolower(trim($source->getText($children[1])));
            if ($positive && ($op === '&&' || $op === 'and')) {
                return $this->conditionAssertsHas($context, $source, $children[0], $varName, $guardMethod, $expectedKey, true)
                    || $this->conditionAssertsHas($context, $source, $children[2], $varName, $guardMethod, $expectedKey, true);
            }
            if (!$positive && ($op === '||' || $op === 'or')) {
                return $this->conditionAssertsHas($context, $source, $children[0], $varName, $guardMethod, $expectedKey, false)
                    || $this->conditionAssertsHas($context, $source, $children[2], $varName, $guardMethod, $expectedKey, false);
            }
            if ($op === '===' || $op === '==' || $op === '!==' || $op === '!=') {
                $lhsText = strtolower(trim($source->getText($children[0])));
                $rhsText = strtolower(trim($source->getText($children[2])));
                $negatedOp = ($op === '!==' || $op === '!=');
                if ($lhsText === 'true' || $lhsText === 'false') {
                    $want = ($lhsText === 'true') ? !$negatedOp : $negatedOp;

                    return $this->conditionAssertsHas($context, $source, $children[2], $varName, $guardMethod, $expectedKey, $positive ? $want : !$want);
                }
                if ($rhsText === 'true' || $rhsText === 'false') {
                    $want = ($rhsText === 'true') ? !$negatedOp : $negatedOp;

                    return $this->conditionAssertsHas($context, $source, $children[0], $varName, $guardMethod, $expectedKey, $positive ? $want : !$want);
                }
            }

            return false;
        }

        if (!$positive || $node->kind !== NodeKind::MethodCall) {
            return false;
        }

        $called = strtolower(ConsoleCommandDefinition::calledMethodName($source, $node) ?? '');
        if ($called !== $guardMethod) {
            return false;
        }

        $callRecv = ConsoleCommandDefinition::unwrap($source, $source->getChildren($node)[0] ?? null);
        if ($callRecv === null || $callRecv->kind !== NodeKind::DirectVariable || $source->getText($callRecv) !== $varName) {
            return false;
        }

        $args = ConsoleCommandDefinition::extractCallArguments($source, $node);
        $argNode = ConsoleCommandDefinition::resolveArgument($args, 0, 'name');
        if ($argNode === null) {
            return false;
        }

        return ConsoleCommandDefinition::extractLiteralString($source, $argNode, $context->analysis) === $expectedKey;
    }

    private function variableReassignedInRange(
        SourceFile $source,
        Node $scope,
        string $varName,
        int $afterOffset,
        int $beforeOffset,
    ): bool {
        foreach ($source->getDescendants($scope, NodeKind::Assignment) as $assign) {
            if ($assign->span->start < $afterOffset || $assign->span->start >= $beforeOffset) {
                continue;
            }
            $lhs = $source->getChildren($assign)[0] ?? null;
            if ($lhs === null) {
                continue;
            }
            $lhsUnwrapped = ConsoleCommandDefinition::unwrap($source, $lhs);
            if ($lhsUnwrapped !== null && $lhsUnwrapped->kind === NodeKind::DirectVariable && $source->getText($lhsUnwrapped) === $varName) {
                return true;
            }
            foreach ($source->getDescendants($lhs, NodeKind::DirectVariable) as $targetVar) {
                if ($source->getText($targetVar) === $varName) {
                    return true;
                }
            }
        }

        foreach ([NodeKind::ForeachTarget, NodeKind::TryCatchClause, NodeKind::Unset] as $killKind) {
            foreach ($source->getDescendants($scope, $killKind) as $killNode) {
                if ($killNode->span->start < $afterOffset || $killNode->span->start >= $beforeOffset) {
                    continue;
                }
                foreach ($source->getDescendants($killNode, NodeKind::DirectVariable) as $targetVar) {
                    if ($source->getText($targetVar) === $varName) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, true>
     */
    private function resolveDefaultApplicationOptions(NodeAnalysisContext $context): array
    {
        $baseMeta = $context->codebase->getClass(self::APPLICATION);
        $fwMeta = $context->codebase->getClass(self::FRAMEWORK_APPLICATION);

        $baseFile = $baseMeta?->location->file ?? '';
        $fwFile = $fwMeta?->location->file ?? '';
        $cacheKey = $baseFile . "\0" . ($fwMeta !== null ? '1:' . $fwFile : '0');

        if (isset($this->applicationOptionsCache[$cacheKey])) {
            return $this->applicationOptionsCache[$cacheKey];
        }

        $options = $this->extractOptionsFromClassFile(
            $baseFile,
            $context->analysis->file,
            ['getdefaultinputdefinition'],
        );
        if ($options === null) {
            $options = self::FALLBACK_BASE_APPLICATION_OPTIONS;
            if ($context->codebase->classConstantExists(self::OUTPUT_INTERFACE, 'VERBOSITY_SILENT')) {
                $options['silent'] = true;
            }
        }

        if ($fwMeta !== null) {
            $fwOptions = $this->extractOptionsFromClassFile(
                $fwFile,
                $context->analysis->file,
                ['__construct', 'getdefaultinputdefinition'],
            ) ?? self::FALLBACK_FRAMEWORK_APPLICATION_OPTIONS;
            $options += $fwOptions;
        }

        $this->applicationOptionsCache[$cacheKey] = $options;

        return $options;
    }

    /**
     * @param list<string> $targetMethods
     * @return array<string, true>|null
     */
    private function extractOptionsFromClassFile(string $file, string $analysisFile, array $targetMethods): ?array
    {
        $targetMethods = array_map(strtolower(...), $targetMethods);
        $resolved = $this->resolveReadableFile($file, $analysisFile);
        if ($resolved === null) {
            return null;
        }

        $contents = @file_get_contents($resolved);
        if ($contents === false || stripos($contents, 'InputOption') === false) {
            return null;
        }

        $tokens = [];
        foreach (token_get_all($contents) as $tok) {
            if (is_array($tok)) {
                if ($tok[0] === T_WHITESPACE || $tok[0] === T_COMMENT || $tok[0] === T_DOC_COMMENT) {
                    continue;
                }
                $tokens[] = [$tok[0], $tok[1]];
            } else {
                $tokens[] = [$tok, $tok];
            }
        }

        $targetSet = array_fill_keys($targetMethods, true);
        $options = [];
        $total = count($tokens);

        for ($i = 0; $i < $total; ++$i) {
            if ($tokens[$i][0] !== T_FUNCTION) {
                continue;
            }
            $nameIdx = $i + 1;
            if (isset($tokens[$nameIdx]) && TokenHelper::isAmpersand($tokens[$nameIdx][0])) {
                ++$nameIdx;
            }
            if (!isset($tokens[$nameIdx]) || $tokens[$nameIdx][0] !== T_STRING) {
                continue;
            }
            if (!isset($targetSet[strtolower($tokens[$nameIdx][1])])) {
                continue;
            }

            $braceIdx = null;
            for ($j = $nameIdx + 1; $j < $total; ++$j) {
                if ($tokens[$j][0] === ';') {
                    break;
                }
                if ($tokens[$j][0] === '{') {
                    $braceIdx = $j;
                    break;
                }
            }
            if ($braceIdx === null) {
                continue;
            }

            $depth = 0;
            $bodyEnd = $braceIdx;
            for ($j = $braceIdx; $j < $total; ++$j) {
                if ($tokens[$j][0] === '{') {
                    ++$depth;
                } elseif ($tokens[$j][0] === '}') {
                    --$depth;
                    if ($depth === 0) {
                        $bodyEnd = $j;
                        break;
                    }
                }
            }

            for ($j = $braceIdx + 1; $j < $bodyEnd; ++$j) {
                if ($tokens[$j][0] !== T_NEW || !isset($tokens[$j + 1], $tokens[$j + 2])) {
                    continue;
                }
                $classTok = $tokens[$j + 1];
                if (
                    $classTok[0] !== T_STRING
                    && $classTok[0] !== T_NAME_QUALIFIED
                    && $classTok[0] !== T_NAME_FULLY_QUALIFIED
                ) {
                    continue;
                }
                $classLower = strtolower(ltrim($classTok[1], '\\'));
                if ($classLower !== 'inputoption' && !str_ends_with($classLower, '\\inputoption')) {
                    continue;
                }
                if ($tokens[$j + 2][0] !== '(') {
                    continue;
                }

                $argDepth = 0;
                $closeParen = null;
                for ($k = $j + 2; $k < $bodyEnd; ++$k) {
                    if ($tokens[$k][0] === '(') {
                        ++$argDepth;
                    } elseif ($tokens[$k][0] === ')') {
                        --$argDepth;
                        if ($argDepth === 0) {
                            $closeParen = $k;
                            break;
                        }
                    }
                }
                if ($closeParen === null) {
                    continue;
                }

                $this->collectInputOptionFromTokens($tokens, $j + 3, $closeParen, $options);
                $j = $closeParen;
            }

            $i = $bodyEnd;
        }

        return $options === [] ? null : $options;
    }

    /**
     * @param list<array{0: int|string, 1: string}> $tokens
     * @param array<string, true> $options
     */
    private function collectInputOptionFromTokens(array $tokens, int $start, int $end, array &$options): void
    {
        $argRanges = [];
        $argStart = $start;
        $depth = 0;
        for ($i = $start; $i < $end; ++$i) {
            $id = $tokens[$i][0];
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

        $nameRange = null;
        $modeRange = null;
        $positional = 0;
        foreach ($argRanges as [$aStart, $aEnd]) {
            if ($aStart >= $aEnd) {
                continue;
            }
            if ($aStart + 2 <= $aEnd && $tokens[$aStart][0] === T_STRING && $tokens[$aStart + 1][0] === ':') {
                $argName = $tokens[$aStart][1];
                if ($argName === 'name') {
                    $nameRange = [$aStart + 2, $aEnd];
                } elseif ($argName === 'mode') {
                    $modeRange = [$aStart + 2, $aEnd];
                }
                continue;
            }
            if ($positional === 0) {
                $nameRange = [$aStart, $aEnd];
            } elseif ($positional === 2) {
                $modeRange = [$aStart, $aEnd];
            }
            ++$positional;
        }

        if ($nameRange === null || $nameRange[1] - $nameRange[0] !== 1 || $tokens[$nameRange[0]][0] !== T_CONSTANT_ENCAPSED_STRING) {
            return;
        }

        $raw = $tokens[$nameRange[0]][1];
        if (strlen($raw) < 2) {
            return;
        }
        $normalized = ConsoleCommandDefinition::normalizeOptionName(substr($raw, 1, -1));
        if ($normalized === null) {
            return;
        }

        $options[$normalized] = true;

        if ($modeRange !== null) {
            for ($i = $modeRange[0]; $i < $modeRange[1]; ++$i) {
                if ($tokens[$i][0] === T_STRING && strtolower($tokens[$i][1]) === 'value_negatable') {
                    $options['no-' . $normalized] = true;
                    break;
                }
                if ($tokens[$i][0] === T_LNUMBER) {
                    $parsedMode = ConsoleCommandDefinition::parseIntegerLiteral($tokens[$i][1]);
                    if ($parsedMode !== null && ($parsedMode & ConsoleCommandDefinition::OPT_VALUE_NEGATABLE) !== 0) {
                        $options['no-' . $normalized] = true;
                        break;
                    }
                }
            }
        }
    }

    private function resolveReadableFile(string $file, string $analysisFile): ?string
    {
        if ($file === '') {
            return null;
        }
        if (is_file($file)) {
            return $file;
        }
        if ($file[0] !== '/') {
            $pwd = getenv('PWD');
            if (is_string($pwd) && $pwd !== '' && is_file($pwd . '/' . $file)) {
                return $pwd . '/' . $file;
            }
            if ($analysisFile !== '') {
                $dir = dirname($analysisFile);
                for ($depth = 0; $depth < 8; ++$depth) {
                    $candidate = $dir . '/' . $file;
                    if (is_file($candidate)) {
                        return $candidate;
                    }
                    $parent = dirname($dir);
                    if ($parent === $dir) {
                        break;
                    }
                    $dir = $parent;
                }
            }
        }

        return null;
    }

    private function isInputReceiver(NodeAnalysisContext $context, Node $receiver): bool
    {
        $type = $context->analysis->getExpressionType($receiver);
        if ($type === null) {
            return false;
        }

        foreach ($type->atomicTypes as $atomic) {
            if (!property_exists($atomic, 'name') || !is_string($atomic->name)) {
                continue;
            }
            $lower = strtolower($atomic->name);
            if ($lower === self::INPUT_INTERFACE) {
                return true;
            }
            foreach ($context->codebase->getClassAncestors($atomic->name) as $ancestor) {
                if (strtolower($ancestor) === self::INPUT_INTERFACE) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isNestedClass(SourceFile $source, Node $classNode, Node $node): bool
    {
        $insideMember = $node->kind === NodeKind::ClassLikeMember;
        $parent = $source->getParent($node);
        while ($parent !== null && $parent->id !== $classNode->id) {
            if ($parent->kind === NodeKind::Class_) {
                return true;
            }
            if ($parent->kind === NodeKind::AnonymousClass && $insideMember) {
                return true;
            }
            if ($parent->kind === NodeKind::ClassLikeMember) {
                $insideMember = true;
            }
            $parent = $source->getParent($parent);
        }

        return false;
    }

    private function className(SourceFile $source, Node $classNode): string
    {
        foreach ($source->getChildren($classNode) as $child) {
            if ($child->kind === NodeKind::LocalIdentifier) {
                return $source->getResolvedName($child)?->name ?? $source->getText($child);
            }
        }

        return 'Command';
    }

    private function methodName(SourceFile $source, Node $method): ?string
    {
        foreach ($source->getChildren($method) as $child) {
            if ($child->kind === NodeKind::LocalIdentifier) {
                return $source->getText($child);
            }
        }

        return null;
    }
}

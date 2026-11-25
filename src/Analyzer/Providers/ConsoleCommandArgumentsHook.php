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
 */
final class ConsoleCommandArgumentsHook implements ClassLikeAnalysisHook
{
    public const INVALID_DEFINITION_ISSUE = 'invalid-console-input-definition';

    private const COMMAND = 'Symfony\\Component\\Console\\Command\\Command';
    private const INPUT_INTERFACE = 'symfony\\component\\console\\input\\inputinterface';

    private const CONSTRUCTOR_INPUT_METHODS = [
        'addargument' => true,
        'addoption' => true,
        'setdefinition' => true,
        'getdefinition' => true,
        'getnativedefinition' => true,
    ];

    private const DEFAULT_APPLICATION_OPTIONS = [
        'help' => true,
        'quiet' => true,
        'silent' => true,
        'verbose' => true,
        'version' => true,
        'ansi' => true,
        'no-ansi' => true,
        'no-interaction' => true,
        'env' => true,
        'no-debug' => true,
        'profile' => true,
    ];

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
        foreach ($methods as $method) {
            $this->checkInputCalls($context, $source, $method, $className, $definition, $checkEmptyArguments, $checkEmptyOptions);
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

    private function checkInputCalls(
        NodeAnalysisContext $context,
        SourceFile $source,
        Node $method,
        string $className,
        ConsoleCommandDefinition $definition,
        bool $checkEmptyArguments,
        bool $checkEmptyOptions,
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
            $name = $argNode !== null ? ConsoleCommandDefinition::extractLiteralString($source, $argNode) : null;
            if ($name === null || $name === '') {
                continue;
            }

            if ($called === 'getargument' && ($definition->arguments !== [] || $checkEmptyArguments) && !isset($definition->arguments[$name])) {
                if ($name === 'command') {
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
                && !isset($definition->options[$name])
                && !isset(self::DEFAULT_APPLICATION_OPTIONS[$name])
            ) {
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

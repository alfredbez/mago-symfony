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
        $methods = [];
        foreach ($source->getDescendants($classNode, NodeKind::Method) as $method) {
            if ($this->isNestedClass($source, $classNode, $method)) {
                continue;
            }
            $name = strtolower($this->methodName($source, $method) ?? '');
            if ($name === 'configure') {
                $configure = $method;
            } elseif ($name !== '') {
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
        foreach ($methods as $method) {
            $this->checkInputCalls($context, $source, $method, $className, $definition);
        }
    }

    private function checkInputCalls(
        NodeAnalysisContext $context,
        SourceFile $source,
        Node $method,
        string $className,
        ConsoleCommandDefinition $definition,
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

            if ($called === 'getargument' && $definition->arguments !== [] && !isset($definition->arguments[$name])) {
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
            } elseif ($called === 'getoption' && $definition->options !== [] && !isset($definition->options[$name])) {
                $normalizedLookup = ltrim($name, '-');
                if (isset($definition->options[$normalizedLookup]) || isset(self::DEFAULT_APPLICATION_OPTIONS[$normalizedLookup])) {
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
        $parent = $source->getParent($node);
        while ($parent !== null && $parent->id !== $classNode->id) {
            if ($parent->kind === NodeKind::Class_ || $parent->kind === NodeKind::AnonymousClass) {
                return true;
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

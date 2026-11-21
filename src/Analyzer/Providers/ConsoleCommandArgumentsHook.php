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
 * Validates literal `$input->getArgument(...)` and `$input->getOption(...)`
 * calls inside Symfony Console Command subclasses against literal declarations
 * in `configure()`.
 *
 * Skips commands that call `parent::configure()`, `setDefinition()`, or dynamic
 * argument/option registration so inherited or runtime-defined inputs never
 * produce false positives.
 */
final class ConsoleCommandArgumentsHook implements ClassLikeAnalysisHook
{
    private const COMMAND = 'Symfony\\Component\\Console\\Command\\Command';
    private const INPUT_INTERFACE = 'symfony\\component\\console\\input\\inputinterface';

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

        if ($configure === null || $methods === []) {
            return;
        }

        $declared = $this->extractDeclaredInputs($source, $configure);
        if ($declared === null) {
            return;
        }

        $className = $this->className($source, $classNode);
        foreach ($methods as $method) {
            $this->checkInputCalls($context, $source, $method, $className, $declared['arguments'], $declared['options']);
        }
    }

    /**
     * @return null|array{arguments: array<string, true>, options: array<string, true>}
     */
    private function extractDeclaredInputs(SourceFile $source, Node $configure): ?array
    {
        $arguments = [];
        $options = [];

        foreach ($source->getDescendants($configure, NodeKind::StaticMethodCall) as $staticCall) {
            $text = strtolower($source->getText($staticCall));
            if (str_contains($text, 'parent::configure')) {
                return null;
            }
        }

        foreach ($source->getDescendants($configure, NodeKind::MethodCall) as $call) {
            $methodName = strtolower($this->calledMethodName($source, $call) ?? '');
            if ($methodName === 'setdefinition') {
                if (!$this->extractSetDefinitionInputs($source, $call, $arguments, $options)) {
                    return null;
                }
                continue;
            }

            if ($methodName !== 'addargument' && $methodName !== 'addoption') {
                if (!isset(self::KNOWN_SAFE_CONFIGURE_METHODS[$methodName])) {
                    $rawRecv = $this->unwrap($source, $source->getChildren($call)[0] ?? null);
                    if ($rawRecv !== null && $source->getText($rawRecv) === '$this') {
                        return null;
                    }
                }
                continue;
            }

            $firstArg = $this->firstCallArgument($source, $call, 'name');
            $literal = $firstArg !== null ? $this->extractLiteralString($source, $firstArg) : null;
            if ($literal === null || $literal === '') {
                return null;
            }

            if ($methodName === 'addargument') {
                $arguments[$literal] = true;
            } else {
                $normalizedOption = ltrim($literal, '-');
                if ($normalizedOption === '') {
                    return null;
                }
                $options[$normalizedOption] = true;
            }
        }

        if ($arguments === [] && $options === []) {
            return null;
        }

        return ['arguments' => $arguments, 'options' => $options];
    }

    /**
     * @param array<string, true> $arguments
     * @param array<string, true> $options
     */
    private function checkInputCalls(
        NodeAnalysisContext $context,
        SourceFile $source,
        Node $method,
        string $className,
        array $arguments,
        array $options,
    ): void {
        foreach ($source->getDescendants($method, NodeKind::MethodCall) as $call) {
            $called = strtolower($this->calledMethodName($source, $call) ?? '');
            if ($called !== 'getargument' && $called !== 'getoption') {
                continue;
            }

            $rawReceiver = $source->getChildren($call)[0] ?? null;
            $receiver = $this->unwrap($source, $rawReceiver);
            if ($rawReceiver === null || ($receiver === null) || (!$this->isInputReceiver($context, $rawReceiver) && !$this->isInputReceiver($context, $receiver))) {
                continue;
            }

            $argNode = $this->firstCallArgument($source, $call, 'name');
            $name = $argNode !== null ? $this->extractLiteralString($source, $argNode) : null;
            if ($name === null || $name === '') {
                continue;
            }

            if ($called === 'getargument' && $arguments !== [] && !isset($arguments[$name])) {
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
            } elseif ($called === 'getoption' && $options !== [] && !isset($options[$name])) {
                $normalizedLookup = ltrim($name, '-');
                if (isset($options[$normalizedLookup]) || isset(self::DEFAULT_APPLICATION_OPTIONS[$normalizedLookup])) {
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

    /**
     * @param array<string, true> $arguments
     * @param array<string, true> $options
     */
    private function extractSetDefinitionInputs(
        SourceFile $source,
        Node $call,
        array &$arguments,
        array &$options,
    ): bool {
        $defArg = $this->firstCallArgument($source, $call, 'definition');
        $defNode = $this->unwrap($source, $defArg);
        if ($defNode === null || ($defNode->kind !== NodeKind::Array && $defNode->kind !== NodeKind::LegacyArray)) {
            return false;
        }

        $instantiations = $source->getDescendants($defNode, NodeKind::Instantiation);
        if ($instantiations === []) {
            return false;
        }

        foreach ($instantiations as $inst) {
            $classText = strtolower($source->getText($inst));
            $isArg = str_contains($classText, 'inputargument');
            $isOpt = !$isArg && str_contains($classText, 'inputoption');
            if (!$isArg && !$isOpt) {
                return false;
            }

            $firstArg = $this->firstCallArgument($source, $inst, 'name');
            $literal = $firstArg !== null ? $this->extractLiteralString($source, $firstArg) : null;
            if ($literal === null || $literal === '') {
                return false;
            }

            if ($isArg) {
                $arguments[$literal] = true;
            } else {
                $normalized = ltrim($literal, '-');
                if ($normalized === '') {
                    return false;
                }
                $options[$normalized] = true;
            }
        }

        return true;
    }

    private const KNOWN_SAFE_CONFIGURE_METHODS = [
        'setname' => true,
        'setdescription' => true,
        'sethelp' => true,
        'setaliases' => true,
        'sethidden' => true,
        'addusage' => true,
        'setprocesstitle' => true,
        'setcode' => true,
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

    private function calledMethodName(SourceFile $source, Node $call): ?string
    {
        $children = $source->getChildren($call);
        $member = $children[1] ?? null;
        if ($member === null) {
            return null;
        }
        $selector = $source->getChildren($member)[0] ?? null;
        if ($selector === null || $selector->kind !== NodeKind::LocalIdentifier) {
            return null;
        }

        return $source->getText($selector);
    }

    private function firstCallArgument(SourceFile $source, Node $call, string $namedKey): ?Node
    {
        $argumentList = null;
        foreach ($source->getChildren($call) as $child) {
            if ($child->kind === NodeKind::ArgumentList) {
                $argumentList = $child;
                break;
            }
        }
        if ($argumentList === null) {
            return null;
        }

        $positional = 0;
        foreach ($source->getChildren($argumentList) as $argument) {
            $variant = $source->getChildren($argument)[0] ?? null;
            if ($variant === null) {
                continue;
            }
            if ($variant->kind === NodeKind::PositionalArgument) {
                if ($positional === 0) {
                    return $source->getChildren($variant)[0] ?? null;
                }
                $positional++;
            } elseif ($variant->kind === NodeKind::NamedArgument) {
                $parts = $source->getChildren($variant);
                if (isset($parts[0], $parts[1]) && $source->getText($parts[0]) === $namedKey) {
                    return $parts[1];
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
            )
        ) {
            $node = $source->getChildren($node)[0] ?? null;
        }

        return $node;
    }
}

<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\FileAnalysis;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

/**
 * Parsed Console Command::configure() declarations for arguments and options,
 * including constant mode bitmasks, literal defaults, and invalid definition diagnostics.
 *
 * @internal
 */
final class ConsoleCommandDefinition
{
    public const ARG_REQUIRED = 1;
    public const ARG_OPTIONAL = 2;
    public const ARG_IS_ARRAY = 4;

    public const OPT_VALUE_NONE = 1;
    public const OPT_VALUE_REQUIRED = 2;
    public const OPT_VALUE_OPTIONAL = 4;
    public const OPT_VALUE_IS_ARRAY = 8;
    public const OPT_VALUE_NEGATABLE = 16;

    private const ARG_MODE_MAX = (self::ARG_IS_ARRAY << 1) - 1;
    private const OPT_MODE_MAX = (self::OPT_VALUE_NEGATABLE << 1) - 1;

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

    private const ARGUMENT_MODES = [
        'required' => self::ARG_REQUIRED,
        'optional' => self::ARG_OPTIONAL,
        'is_array' => self::ARG_IS_ARRAY,
    ];

    private const OPTION_MODES = [
        'value_none' => self::OPT_VALUE_NONE,
        'value_required' => self::OPT_VALUE_REQUIRED,
        'value_optional' => self::OPT_VALUE_OPTIONAL,
        'value_is_array' => self::OPT_VALUE_IS_ARRAY,
        'value_negatable' => self::OPT_VALUE_NEGATABLE,
    ];

    /**
     * @param array<string, array{mode: ?int, defaultKind: string}> $arguments
     * @param array<string, array{mode: ?int, defaultKind: string}> $options
     * @param list<array{span: Span, message: string}> $definitionErrors
     */
    private function __construct(
        public readonly bool $isComplete,
        public readonly array $arguments,
        public readonly array $options,
        public readonly array $definitionErrors,
    ) {}

    public static function isKnownSafeConfigureMethod(string $methodName): bool
    {
        return isset(self::KNOWN_SAFE_CONFIGURE_METHODS[strtolower($methodName)]);
    }

    /**
     * Mirrors Symfony InputOption::__construct() option-name normalization:
     * only a single leading `--` prefix is stripped; single-dash names stay literal.
     */
    public static function normalizeOptionName(string $name): ?string
    {
        if (str_starts_with($name, '--')) {
            $name = substr($name, 2);
        }

        if ($name === '' || $name === '0') {
            return null;
        }

        return $name;
    }

    public function hasDeclaredOption(string $name): bool
    {
        if (isset($this->options[$name])) {
            return true;
        }

        if (str_starts_with($name, 'no-')) {
            $base = substr($name, 3);
            $entry = $this->options[$base] ?? null;
            if (
                $entry !== null
                && $entry['mode'] !== null
                && $entry['mode'] >= 1
                && $entry['mode'] <= self::OPT_MODE_MAX
                && ($entry['mode'] & self::OPT_VALUE_NEGATABLE) === self::OPT_VALUE_NEGATABLE
                && ($entry['mode'] & (self::OPT_VALUE_REQUIRED | self::OPT_VALUE_OPTIONAL)) === 0
            ) {
                return true;
            }
        }

        return false;
    }

    public static function parseConfigure(SourceFile $source, Node $configure, ?FileAnalysis $analysis = null): self
    {
        $arguments = [];
        $options = [];
        $errors = [];
        $complete = true;

        foreach ($source->getDescendants($configure, NodeKind::StaticMethodCall) as $staticCall) {
            if (str_contains(strtolower($source->getText($staticCall)), 'parent::configure')) {
                $complete = false;
                break;
            }
        }

        foreach ($source->getDescendants($configure, NodeKind::MethodCall) as $call) {
            $methodName = strtolower(self::calledMethodName($source, $call) ?? '');
            if ($methodName === 'setdefinition') {
                if (!self::extractSetDefinitionInputs($source, $call, $arguments, $options, $errors, $analysis)) {
                    $complete = false;
                }
                continue;
            }

            if ($methodName !== 'addargument' && $methodName !== 'addoption') {
                if (!isset(self::KNOWN_SAFE_CONFIGURE_METHODS[$methodName])) {
                    $rawRecv = self::unwrap($source, $source->getChildren($call)[0] ?? null);
                    if ($rawRecv !== null && $source->getText($rawRecv) === '$this') {
                        $complete = false;
                    }
                }
                continue;
            }

            $args = self::extractCallArguments($source, $call);
            $nameNode = self::resolveArgument($args, 0, 'name');
            $literal = $nameNode !== null ? self::extractLiteralString($source, $nameNode) : null;
            if ($literal === null || $literal === '') {
                $complete = false;
                continue;
            }

            if ($methodName === 'addargument') {
                $modeNode = self::resolveArgument($args, 1, 'mode');
                $defaultNode = self::resolveArgument($args, 3, 'default');
                $mode = $modeNode === null ? self::ARG_OPTIONAL : self::parseMode($source, $modeNode, $analysis, 'symfony\\component\\console\\input\\inputargument', self::ARGUMENT_MODES, self::ARG_OPTIONAL);
                $defaultKind = self::classifyDefault($source, $defaultNode);
                $arguments[$literal] = ['mode' => $mode, 'defaultKind' => $defaultKind];
                self::validateArgumentDefinition($literal, $mode, $defaultKind, $call->span, $errors);
            } else {
                $normalizedOption = self::normalizeOptionName($literal);
                if ($normalizedOption === null) {
                    $complete = false;
                    continue;
                }
                $modeNode = self::resolveArgument($args, 2, 'mode');
                $defaultNode = self::resolveArgument($args, 4, 'default');
                $mode = $modeNode === null ? self::OPT_VALUE_NONE : self::parseMode($source, $modeNode, $analysis, 'symfony\\component\\console\\input\\inputoption', self::OPTION_MODES, self::OPT_VALUE_NONE);
                $defaultKind = self::classifyDefault($source, $defaultNode);
                $options[$normalizedOption] = ['mode' => $mode, 'defaultKind' => $defaultKind];
                self::validateOptionDefinition($normalizedOption, $mode, $defaultKind, $call->span, $errors);
            }
        }

        if ($arguments === [] && $options === []) {
            $complete = false;
        }

        return new self($complete, $arguments, $options, $errors);
    }

    /**
     * @param array<string, array{mode: ?int, defaultKind: string}> $arguments
     * @param array<string, array{mode: ?int, defaultKind: string}> $options
     * @param list<array{span: Span, message: string}> $errors
     */
    private static function extractSetDefinitionInputs(
        SourceFile $source,
        Node $call,
        array &$arguments,
        array &$options,
        array &$errors,
        ?FileAnalysis $analysis,
    ): bool {
        $args = self::extractCallArguments($source, $call);
        $defArg = self::resolveArgument($args, 0, 'definition');
        $defNode = self::unwrap($source, $defArg);
        if ($defNode === null || ($defNode->kind !== NodeKind::Array && $defNode->kind !== NodeKind::LegacyArray)) {
            return false;
        }

        $foundAny = false;
        $allStatic = true;
        foreach ($source->getChildren($defNode) as $element) {
            if ($element->kind !== NodeKind::ArrayElement) {
                continue;
            }

            $variant = $source->getChildren($element)[0] ?? null;
            if ($variant === null || $variant->kind !== NodeKind::ValueArrayElement) {
                $allStatic = false;
                continue;
            }

            $inst = self::unwrap($source, $source->getChildren($variant)[0] ?? null);
            if ($inst === null || $inst->kind !== NodeKind::Instantiation) {
                $allStatic = false;
                continue;
            }

            $classText = strtolower($source->getText($inst));
            $isArg = str_contains($classText, 'inputargument');
            $isOpt = !$isArg && str_contains($classText, 'inputoption');
            if (!$isArg && !$isOpt) {
                $allStatic = false;
                continue;
            }

            $instArgs = self::extractCallArguments($source, $inst);
            $nameNode = self::resolveArgument($instArgs, 0, 'name');
            $literal = $nameNode !== null ? self::extractLiteralString($source, $nameNode) : null;
            if ($literal === null || $literal === '') {
                $allStatic = false;
                continue;
            }

            if ($isArg) {
                $modeNode = self::resolveArgument($instArgs, 1, 'mode');
                $defaultNode = self::resolveArgument($instArgs, 3, 'default');
                $mode = $modeNode === null ? self::ARG_OPTIONAL : self::parseMode($source, $modeNode, $analysis, 'symfony\\component\\console\\input\\inputargument', self::ARGUMENT_MODES, self::ARG_OPTIONAL);
                $defaultKind = self::classifyDefault($source, $defaultNode);
                $arguments[$literal] = ['mode' => $mode, 'defaultKind' => $defaultKind];
                self::validateArgumentDefinition($literal, $mode, $defaultKind, $inst->span, $errors);
            } else {
                $normalized = self::normalizeOptionName($literal);
                if ($normalized === null) {
                    $allStatic = false;
                    continue;
                }
                $modeNode = self::resolveArgument($instArgs, 2, 'mode');
                $defaultNode = self::resolveArgument($instArgs, 4, 'default');
                $mode = $modeNode === null ? self::OPT_VALUE_NONE : self::parseMode($source, $modeNode, $analysis, 'symfony\\component\\console\\input\\inputoption', self::OPTION_MODES, self::OPT_VALUE_NONE);
                $defaultKind = self::classifyDefault($source, $defaultNode);
                $options[$normalized] = ['mode' => $mode, 'defaultKind' => $defaultKind];
                self::validateOptionDefinition($normalized, $mode, $defaultKind, $inst->span, $errors);
            }
            $foundAny = true;
        }

        return $foundAny && $allStatic;
    }

    /**
     * @param list<array{span: Span, message: string}> $errors
     */
    private static function validateArgumentDefinition(
        string $name,
        ?int $mode,
        string $defaultKind,
        Span $span,
        array &$errors,
    ): void {
        if ($mode === null) {
            return;
        }

        if ($mode < 1 || $mode > self::ARG_MODE_MAX) {
            $errors[] = [
                'span' => $span,
                'message' => "Console argument `{$name}` uses invalid mode `{$mode}`; expected a bitmask between 1 and " . self::ARG_MODE_MAX . '.',
            ];
            return;
        }

        $isRequired = ($mode & self::ARG_REQUIRED) === self::ARG_REQUIRED;
        $isArray = ($mode & self::ARG_IS_ARRAY) === self::ARG_IS_ARRAY;

        if ($isRequired && ($defaultKind === 'scalar' || $defaultKind === 'array')) {
            $errors[] = [
                'span' => $span,
                'message' => "Console argument `{$name}` uses InputArgument::REQUIRED and cannot declare a non-null default value.",
            ];
            return;
        }

        if ($isArray && $defaultKind === 'scalar') {
            $errors[] = [
                'span' => $span,
                'message' => "Console argument `{$name}` uses InputArgument::IS_ARRAY and its default value must be an array or null.",
            ];
        }
    }

    /**
     * @param list<array{span: Span, message: string}> $errors
     */
    private static function validateOptionDefinition(
        string $name,
        ?int $mode,
        string $defaultKind,
        Span $span,
        array &$errors,
    ): void {
        if ($mode === null) {
            return;
        }

        if ($mode < 1 || $mode > self::OPT_MODE_MAX) {
            $errors[] = [
                'span' => $span,
                'message' => "Console option `{$name}` uses invalid mode `{$mode}`; expected a bitmask between 1 and " . self::OPT_MODE_MAX . '.',
            ];
            return;
        }

        $acceptsValue = ($mode & self::OPT_VALUE_REQUIRED) === self::OPT_VALUE_REQUIRED
            || ($mode & self::OPT_VALUE_OPTIONAL) === self::OPT_VALUE_OPTIONAL;
        $isNone = ($mode & self::OPT_VALUE_NONE) === self::OPT_VALUE_NONE;
        $isArray = ($mode & self::OPT_VALUE_IS_ARRAY) === self::OPT_VALUE_IS_ARRAY;
        $isNegatable = ($mode & self::OPT_VALUE_NEGATABLE) === self::OPT_VALUE_NEGATABLE;

        if ($isArray && !$acceptsValue) {
            $errors[] = [
                'span' => $span,
                'message' => "Console option `{$name}` combines InputOption::VALUE_IS_ARRAY without VALUE_REQUIRED or VALUE_OPTIONAL.",
            ];
            return;
        }

        if ($isNegatable && $acceptsValue) {
            $errors[] = [
                'span' => $span,
                'message' => "Console option `{$name}` combines InputOption::VALUE_NEGATABLE with a value-accepting mode.",
            ];
            return;
        }

        if ($isNone && ($defaultKind === 'scalar' || $defaultKind === 'array')) {
            $errors[] = [
                'span' => $span,
                'message' => "Console option `{$name}` uses InputOption::VALUE_NONE and cannot declare a non-null default value.",
            ];
            return;
        }

        if ($isArray && $defaultKind === 'scalar') {
            $errors[] = [
                'span' => $span,
                'message' => "Console option `{$name}` uses InputOption::VALUE_IS_ARRAY and its default value must be an array or null.",
            ];
        }
    }

    /**
     * @param array<string, int> $constants
     */
    private static function parseMode(
        SourceFile $source,
        Node $node,
        ?FileAnalysis $analysis,
        string $expectedOwnerLower,
        array $constants,
        int $defaultIfNull,
    ): ?int {
        if ($analysis !== null) {
            $exprType = $analysis->getExpressionType($node);
            if ($exprType === null) {
                $unwrapped = self::unwrap($source, $node);
                if ($unwrapped !== null) {
                    $exprType = $analysis->getExpressionType($unwrapped);
                }
            }
            if ($exprType !== null) {
                $literalInt = $exprType->getLiteralInt();
                if ($literalInt !== null) {
                    return $literalInt;
                }
            }
        }

        $text = trim($source->getText($node));
        if ($text === '') {
            return null;
        }

        if (strtolower($text) === 'null') {
            return $defaultIfNull;
        }

        $resolvedOwners = [];
        foreach ($source->getResolvedNames($node) as $resolved) {
            $resolvedOwners[] = strtolower(ltrim($resolved->name, '\\'));
        }

        $parts = explode('|', $text);
        $mask = 0;
        $ownerIndex = 0;
        foreach ($parts as $part) {
            $part = trim($part, " \t\n\r()");
            if ($part === '') {
                return null;
            }
            $intLiteral = self::parseIntegerLiteral($part);
            if ($intLiteral !== null) {
                $mask |= $intLiteral;
                continue;
            }

            if (preg_match('/^[\\\\A-Za-z_\x80-\xff][\\\\A-Za-z0-9_\x80-\xff]*::[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*$/', $part) !== 1) {
                return null;
            }
            $pos = strrpos($part, '::');
            if ($pos === false) {
                return null;
            }
            $owner = $resolvedOwners[$ownerIndex] ?? null;
            $ownerIndex++;
            if ($owner !== $expectedOwnerLower) {
                return null;
            }
            $constName = strtolower(substr($part, $pos + 2));
            if (!isset($constants[$constName])) {
                return null;
            }
            $mask |= $constants[$constName];
        }

        return $mask;
    }

    public static function parseIntegerLiteral(string $part): ?int
    {
        $normalized = str_replace(['_', ' '], '', $part);
        if (preg_match('/^([+-]?)(0[xX][0-9a-fA-F]+|0[bB][01]+|0[oO][0-7]+|0[0-7]+|0|[1-9]\d*)$/', $normalized, $match) !== 1) {
            return null;
        }

        $sign = $match[1] === '-' ? -1 : 1;
        $digits = $match[2];
        if (str_starts_with($digits, '0x') || str_starts_with($digits, '0X')) {
            return $sign * (int) hexdec(substr($digits, 2));
        }
        if (str_starts_with($digits, '0b') || str_starts_with($digits, '0B')) {
            return $sign * (int) bindec(substr($digits, 2));
        }
        if (str_starts_with($digits, '0o') || str_starts_with($digits, '0O')) {
            return $sign * (int) octdec(substr($digits, 2));
        }
        if (preg_match('/^0[0-7]+$/', $digits) === 1) {
            return $sign * intval($digits, 8);
        }

        return $sign * (int) $digits;
    }

    private static function classifyDefault(SourceFile $source, ?Node $node): string
    {
        if ($node === null) {
            return 'null';
        }

        $unwrapped = self::unwrap($source, $node);
        if ($unwrapped === null) {
            return 'unknown';
        }

        if ($unwrapped->kind === NodeKind::Array || $unwrapped->kind === NodeKind::LegacyArray) {
            return 'array';
        }

        if (
            $unwrapped->kind === NodeKind::LiteralString
            || $unwrapped->kind === NodeKind::LiteralInteger
            || $unwrapped->kind === NodeKind::LiteralFloat
        ) {
            return 'scalar';
        }

        $text = strtolower(trim($source->getText($unwrapped)));
        if ($text === 'null') {
            return 'null';
        }
        if ($text === 'true' || $text === 'false' || is_numeric($text)) {
            return 'scalar';
        }
        if (strlen($text) >= 2 && (($text[0] === "'" && $text[-1] === "'") || ($text[0] === '"' && $text[-1] === '"'))) {
            return 'scalar';
        }

        return 'unknown';
    }

    /**
     * @return array{positional: list<Node>, named: array<string, Node>}
     */
    public static function extractCallArguments(SourceFile $source, Node $call): array
    {
        $positional = [];
        $named = [];
        $argumentList = null;
        foreach ($source->getChildren($call) as $child) {
            if ($child->kind === NodeKind::ArgumentList) {
                $argumentList = $child;
                break;
            }
        }
        if ($argumentList === null) {
            return ['positional' => [], 'named' => []];
        }

        foreach ($source->getChildren($argumentList) as $argument) {
            $variant = $source->getChildren($argument)[0] ?? null;
            if ($variant === null) {
                continue;
            }
            if ($variant->kind === NodeKind::PositionalArgument) {
                $expr = $source->getChildren($variant)[0] ?? null;
                if ($expr !== null) {
                    $positional[] = $expr;
                }
            } elseif ($variant->kind === NodeKind::NamedArgument) {
                $parts = $source->getChildren($variant);
                if (isset($parts[0], $parts[1])) {
                    $named[$source->getText($parts[0])] = $parts[1];
                }
            }
        }

        return ['positional' => $positional, 'named' => $named];
    }

    /**
     * @param array{positional: list<Node>, named: array<string, Node>} $args
     */
    public static function resolveArgument(array $args, int $position, string $name): ?Node
    {
        return $args['named'][$name] ?? $args['positional'][$position] ?? null;
    }

    public static function calledMethodName(SourceFile $source, Node $call): ?string
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

    public static function extractLiteralString(SourceFile $source, Node $node): ?string
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

    public static function unwrap(SourceFile $source, ?Node $node): ?Node
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

<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\IssueFilterContext;
use Mago\Sdk\Analyzer\IssueFilterDecision;
use Mago\Sdk\Analyzer\IssueFilterHook;
use Mago\Sdk\Analyzer\Metadata\FunctionLikeMetadata;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\Reporting\AnnotationKind;

use function array_intersect_assoc;
use function array_pop;
use function array_slice;
use function count;
use function explode;
use function implode;
use function is_array;
use function ltrim;
use function max;
use function min;
use function preg_match;
use function str_contains;
use function strlen;
use function strtolower;
use function substr;
use function token_get_all;

use const T_AS;
use const T_BREAK;
use const T_CASE;
use const T_CATCH;
use const T_CLASS;
use const T_COALESCE;
use const T_COALESCE_EQUAL;
use const T_COMMENT;
use const T_CONST;
use const T_CONSTANT_ENCAPSED_STRING;
use const T_CONTINUE;
use const T_DEFAULT;
use const T_DO;
use const T_DOC_COMMENT;
use const T_DOUBLE_ARROW;
use const T_DOUBLE_COLON;
use const T_ELLIPSIS;
use const T_ELSE;
use const T_ELSEIF;
use const T_ENDFOR;
use const T_ENDFOREACH;
use const T_ENDIF;
use const T_ENDSWITCH;
use const T_ENDWHILE;
use const T_EVAL;
use const T_FINALLY;
use const T_FN;
use const T_FOR;
use const T_FOREACH;
use const T_FUNCTION;
use const T_GLOBAL;
use const T_GOTO;
use const T_IF;
use const T_INCLUDE;
use const T_INCLUDE_ONCE;
use const T_LIST;
use const T_LNUMBER;
use const T_MATCH;
use const T_NAMESPACE;
use const T_NEW;
use const T_REQUIRE;
use const T_REQUIRE_ONCE;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NS_SEPARATOR;
use const T_RETURN;
use const T_STRING;
use const T_SWITCH;
use const T_THROW;
use const T_TRY;
use const T_UNSET;
use const T_USE;
use const T_VARIABLE;
use const T_WHILE;
use const T_WHITESPACE;

/**
 * Suppresses Symfony 7's TreeBuilder<string> vs TreeBuilder<'array'> false positive
 * only when a matching TreeBuilder root type dominates every reachable path to the
 * reported return statement, and suppresses ArrayNodeDefinition<TreeBuilder<...>>
 * vs ArrayNodeDefinition<null> parent mismatches only when the enclosing signature's
 * effective return declaration is proven unparameterized.
 */
final class TreeBuilderReturnIssueFilter implements IssueFilterHook
{
    private const PATTERN = '/expected `Symfony\\\\Component\\\\Config\\\\Definition\\\\Builder\\\\TreeBuilder<string\(\'([a-z]+)\'\)>`, but found `Symfony\\\\Component\\\\Config\\\\Definition\\\\Builder\\\\TreeBuilder<string>`/';
    private const ROOT_NODE_RETURN_PATTERN = '/^Invalid return type for function `([^`]+)`: expected `Symfony\\\\Component\\\\Config\\\\Definition\\\\Builder\\\\ArrayNodeDefinition<null>`, but found `Symfony\\\\Component\\\\Config\\\\Definition\\\\Builder\\\\ArrayNodeDefinition<Symfony\\\\Component\\\\Config\\\\Definition\\\\Builder\\\\TreeBuilder(?:<[^>]+>)?>`\.$/';
    private const TREE_BUILDER_NS = 'symfony\\component\\config\\definition\\builder';
    private const TREE_BUILDER_FQN = self::TREE_BUILDER_NS . '\\treebuilder';
    private const ARRAY_NODE_DEFINITION_FQN = self::TREE_BUILDER_NS . '\\arraynodedefinition';

    private const KNOWN_ROOT_TYPES = [
        'array' => true,
        'variable' => true,
        'scalar' => true,
        'string' => true,
        'boolean' => true,
        'integer' => true,
        'float' => true,
        'enum' => true,
    ];

    /** @var list<array{id: int|string, text: string, start: int, end: int}> */
    private array $tokens = [];

    /** @var array<string, string> */
    private array $imports = [];

    private string $currentNamespace = '';

    /** @var array<string, true> */
    private array $aliasedVars = [];

    private int $targetOffset = -1;
    private bool $targetMatched = false;
    private ?string $targetRootType = null;

    /** @var list<array{breakEnv: ?array<string, string>, continueEnv: ?array<string, string>, isLoop: bool}> */
    private array $breakStack = [];

    /** @var list<array{catchEntry: ?array<string, string>, finallyEntry: ?array<string, string>, inTryBody: bool}> */
    private array $tryStack = [];

    public function getCodes(): array
    {
        return ['invalid-return-statement'];
    }

    public function filterIssue(IssueFilterContext $context): IssueFilterDecision
    {
        $message = $context->issue->message;
        if (str_contains($message, 'ArrayNodeDefinition<null>')) {
            $rootMatch = [];
            if (
                preg_match(self::ROOT_NODE_RETURN_PATTERN, $message, $rootMatch) === 1
                && $this->isProvenUnparameterizedArrayNodeReturnTarget($context, $rootMatch[1])
            ) {
                return IssueFilterDecision::Remove;
            }

            return IssueFilterDecision::Keep;
        }

        if (!str_contains($message, 'TreeBuilder<string>')) {
            return IssueFilterDecision::Keep;
        }

        $match = [];
        if (preg_match(self::PATTERN, $message, $match) !== 1) {
            return IssueFilterDecision::Keep;
        }

        $expectedRootType = strtolower($match[1]);
        $primarySpan = null;
        foreach ($context->issue->annotations as $annotation) {
            if ($annotation->kind === AnnotationKind::Primary) {
                $primarySpan = $annotation->span;
                break;
            }
        }
        $primarySpan ??= $context->issue->annotations[0]->span ?? null;
        if ($primarySpan === null) {
            return IssueFilterDecision::Keep;
        }

        $actualRootType = $this->resolveDominatingReturnRootType($context->contents, $primarySpan->start);

        return $actualRootType === $expectedRootType
            ? IssueFilterDecision::Remove
            : IssueFilterDecision::Keep;
    }

    private function isProvenUnparameterizedArrayNodeReturnTarget(IssueFilterContext $context, string $symbol): bool
    {
        if (str_contains($symbol, '::')) {
            [$className, $methodName] = explode('::', $symbol, 2);
            if ($className === '' || $methodName === '') {
                return false;
            }

            $classMeta = $context->codebase->getClassLike($className);
            $methodMeta = $context->codebase->getMethod($className, $methodName);
            if ($classMeta === null || $methodMeta === null || $classMeta->hasIncompleteHierarchy()) {
                return false;
            }

            if (!self::hasUnparameterizedArrayNodeReturn($methodMeta)) {
                return false;
            }

            $queue = [...$classMeta->parentClasses, ...$classMeta->parentInterfaces, ...$classMeta->usedTraits];
            $visited = [strtolower($className) => true];
            while ($queue !== []) {
                $ancestor = array_pop($queue);
                $ancestorKey = strtolower($ancestor);
                if (isset($visited[$ancestorKey])) {
                    continue;
                }
                $visited[$ancestorKey] = true;

                $ancestorClass = $context->codebase->getClassLike($ancestor);
                if ($ancestorClass === null || $ancestorClass->hasIncompleteHierarchy()) {
                    return false;
                }

                $ancestorMethod = $context->codebase->getMethod($ancestor, $methodName);
                if ($ancestorMethod !== null && $ancestorMethod->returnType?->fromDocblock === true) {
                    return false;
                }

                foreach ([...$ancestorClass->parentClasses, ...$ancestorClass->parentInterfaces, ...$ancestorClass->usedTraits] as $next) {
                    if (!isset($visited[strtolower($next)])) {
                        $queue[] = $next;
                    }
                }
            }

            return true;
        }

        $functionMeta = $context->codebase->getFunction($symbol);
        if ($functionMeta === null) {
            return false;
        }

        return self::hasUnparameterizedArrayNodeReturn($functionMeta);
    }

    private static function hasUnparameterizedArrayNodeReturn(FunctionLikeMetadata $metadata): bool
    {
        $declared = $metadata->declaredReturnType;
        $effective = $metadata->returnType;
        if ($declared === null || $effective === null) {
            return false;
        }

        if ($effective->fromDocblock) {
            return false;
        }

        return self::isUnparameterizedArrayNodeType($declared->type)
            && self::isUnparameterizedArrayNodeType($effective->type);
    }

    private static function isUnparameterizedArrayNodeType(Type $type): bool
    {
        if (count($type->atomicTypes) !== 1) {
            return false;
        }

        $atomic = $type->atomicTypes[0];

        return $atomic instanceof NamedObjectType
            && strtolower($atomic->name) === self::ARRAY_NODE_DEFINITION_FQN
            && $atomic->parameters === null;
    }

    private function resolveDominatingReturnRootType(string $contents, int $targetOffset): ?string
    {
        $this->tokens = self::tokenize($contents);
        $this->targetOffset = $targetOffset;
        $this->targetMatched = false;
        $this->targetRootType = null;
        $this->breakStack = [];
        $this->tryStack = [];
        $this->collectImportsBefore($targetOffset);

        $enclosing = $this->findEnclosingFunctionBody($targetOffset);
        if ($enclosing === null) {
            return null;
        }

        [$bodyStart, $bodyEnd, $isArrow] = $enclosing;
        if ($isArrow) {
            $env = [];

            return $this->evalExprType($bodyStart, $bodyEnd, $env);
        }

        if ($this->containsGoto($bodyStart, $bodyEnd)) {
            return null;
        }

        $this->aliasedVars = $this->collectAliasedVars($bodyStart, $bodyEnd);
        $this->walkBlock($bodyStart, $bodyEnd, []);

        return $this->targetMatched ? $this->targetRootType : null;
    }

    /**
     * @return list<array{id: int|string, text: string, start: int, end: int}>
     */
    private static function tokenize(string $contents): array
    {
        $tokens = [];
        $offset = 0;
        foreach (token_get_all($contents) as $tok) {
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

        return $tokens;
    }

    private function collectImportsBefore(int $targetOffset): void
    {
        $this->imports = [];
        $this->currentNamespace = '';
        $total = count($this->tokens);
        $braceDepth = 0;
        $namespaceIsBraced = false;

        for ($i = 0; $i < $total; ++$i) {
            $tok = $this->tokens[$i];
            if ($tok['start'] >= $targetOffset) {
                break;
            }

            if ($tok['id'] === '{') {
                ++$braceDepth;
                continue;
            }
            if ($tok['id'] === '}') {
                if ($braceDepth > 0) {
                    --$braceDepth;
                }
                if ($braceDepth === 0 && $namespaceIsBraced) {
                    $namespaceIsBraced = false;
                }
                continue;
            }

            if ($tok['id'] === T_NAMESPACE && $braceDepth === 0) {
                $this->imports = [];
                $this->currentNamespace = '';
                $namespaceIsBraced = false;
                $j = $i + 1;
                if (isset($this->tokens[$j]) && ($this->tokens[$j]['id'] === T_STRING || $this->tokens[$j]['id'] === T_NAME_QUALIFIED)) {
                    $this->currentNamespace = strtolower(ltrim($this->tokens[$j]['text'], '\\'));
                    ++$j;
                }
                if (isset($this->tokens[$j]) && $this->tokens[$j]['id'] === '{') {
                    $namespaceIsBraced = true;
                }
                continue;
            }

            $importDepth = $namespaceIsBraced ? 1 : 0;
            if ($tok['id'] !== T_USE || $braceDepth !== $importDepth) {
                continue;
            }
            if (isset($this->tokens[$i + 1]) && $this->tokens[$i + 1]['id'] === '(') {
                continue;
            }

            $j = $i + 1;
            if (
                isset($this->tokens[$j])
                && (
                    $this->tokens[$j]['id'] === T_FUNCTION
                    || $this->tokens[$j]['id'] === T_CONST
                    || ($this->tokens[$j]['id'] === T_STRING && strtolower($this->tokens[$j]['text']) === 'const')
                )
            ) {
                continue;
            }

            $prefix = '';
            while ($j < $total && $this->tokens[$j]['id'] !== ';') {
                if (
                    $this->tokens[$j]['id'] === T_FUNCTION
                    || $this->tokens[$j]['id'] === T_CONST
                ) {
                    while ($j < $total && $this->tokens[$j]['id'] !== ',' && $this->tokens[$j]['id'] !== '}' && $this->tokens[$j]['id'] !== ';') {
                        ++$j;
                    }
                    if ($j < $total && ($this->tokens[$j]['id'] === ',' || $this->tokens[$j]['id'] === '}')) {
                        if ($this->tokens[$j]['id'] === '}') {
                            $prefix = '';
                        }
                        ++$j;
                    }
                    continue;
                }

                $name = '';
                while (
                    $j < $total
                    && (
                        $this->tokens[$j]['id'] === T_STRING
                        || $this->tokens[$j]['id'] === T_NAME_QUALIFIED
                        || $this->tokens[$j]['id'] === T_NAME_FULLY_QUALIFIED
                        || $this->tokens[$j]['id'] === T_NS_SEPARATOR
                    )
                ) {
                    $name .= $this->tokens[$j]['text'];
                    ++$j;
                }

                if ($j < $total && $this->tokens[$j]['id'] === '{') {
                    $prefix = ltrim($name, '\\');
                    ++$j;
                    continue;
                }

                $fqn = strtolower(ltrim($prefix !== '' ? $prefix . $name : $name, '\\'));
                $alias = null;
                if ($j + 1 < $total && $this->tokens[$j]['id'] === T_AS && $this->tokens[$j + 1]['id'] === T_STRING) {
                    $alias = strtolower($this->tokens[$j + 1]['text']);
                    $j += 2;
                } elseif ($fqn !== '') {
                    $parts = explode('\\', $fqn);
                    $alias = $parts[count($parts) - 1];
                }

                if ($alias !== null && $fqn !== '') {
                    $this->imports[$alias] = $fqn;
                }

                if ($j < $total && ($this->tokens[$j]['id'] === ',' || $this->tokens[$j]['id'] === '}')) {
                    if ($this->tokens[$j]['id'] === '}') {
                        $prefix = '';
                    }
                    ++$j;
                } else {
                    break;
                }
            }
            $i = $j;
        }
    }

    /**
     * @return array{0: int, 1: int, 2: bool}|null
     */
    private function findEnclosingFunctionBody(int $targetOffset): ?array
    {
        $total = count($this->tokens);
        $best = null;
        $bestStartOffset = -1;

        for ($i = 0; $i < $total; ++$i) {
            $id = $this->tokens[$i]['id'];
            if ($id === T_FUNCTION) {
                $openParen = $this->findNext($i + 1, $total, '(');
                if ($openParen === null) {
                    continue;
                }
                $closeParen = $this->findMatching($openParen, $total, '(', ')');
                if ($closeParen === null) {
                    continue;
                }
                $cursor = $closeParen + 1;
                while ($cursor < $total && $this->tokens[$cursor]['id'] !== '{' && $this->tokens[$cursor]['id'] !== ';') {
                    if ($this->tokens[$cursor]['id'] === '(') {
                        $matched = $this->findMatching($cursor, $total, '(', ')');
                        if ($matched === null) {
                            break;
                        }
                        $cursor = $matched + 1;
                        continue;
                    }
                    ++$cursor;
                }
                if ($cursor >= $total || $this->tokens[$cursor]['id'] !== '{') {
                    continue;
                }
                $closeBrace = $this->findMatching($cursor, $total, '{', '}');
                if ($closeBrace === null) {
                    continue;
                }
                $startByte = $this->tokens[$cursor]['start'];
                $endByte = $this->tokens[$closeBrace]['end'];
                if ($startByte <= $targetOffset && $targetOffset <= $endByte && $startByte >= $bestStartOffset) {
                    $bestStartOffset = $startByte;
                    $best = [$cursor + 1, $closeBrace, false];
                }
            } elseif ($id === T_FN) {
                $arrow = $this->findArrowDoubleArrow($i, $total);
                if ($arrow === null) {
                    continue;
                }
                $exprEnd = $this->findArrowExprEnd($arrow + 1, $total);
                $startByte = $this->tokens[$arrow]['end'];
                $endByte = $this->tokens[$exprEnd - 1]['end'] ?? $startByte;
                if ($startByte <= $targetOffset && $targetOffset <= $endByte && $startByte >= $bestStartOffset) {
                    $bestStartOffset = $startByte;
                    $best = [$arrow + 1, $exprEnd, true];
                }
            }
        }

        return $best;
    }

    private function containsGoto(int $start, int $end): bool
    {
        for ($i = $start; $i < $end; ++$i) {
            if ($this->tokens[$i]['id'] === T_GOTO) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, true>
     */
    private function collectAliasedVars(int $start, int $end): array
    {
        $aliased = [];
        for ($i = $start; $i < $end; ++$i) {
            $id = $this->tokens[$i]['id'];
            if ($id === T_FUNCTION) {
                $i = $this->scanClosureUseAndSkipBody($i, $end, $aliased);
                continue;
            }
            if ($id === T_FN) {
                $arrow = $this->findArrowDoubleArrow($i, $end);
                if ($arrow !== null) {
                    $i = $this->findArrowExprEnd($arrow + 1, $end) - 1;
                }
                continue;
            }
            if ($id === T_CLASS) {
                $parts = $this->findAnonymousClassParts($i, $end);
                if ($parts !== null) {
                    [$ctorRange, $closeBrace] = $parts;
                    if ($ctorRange !== null) {
                        foreach ($this->collectAliasedVars($ctorRange[0], $ctorRange[1]) as $var => $_) {
                            $aliased[$var] = true;
                        }
                    }
                    $i = $closeBrace;
                    continue;
                }
            }
            if ($id === T_GLOBAL) {
                $semi = $this->findStatementEnd($i + 1, $end);
                for ($k = $i + 1; $k < $semi; ++$k) {
                    if ($this->tokens[$k]['id'] === T_VARIABLE) {
                        $aliased[$this->tokens[$k]['text']] = true;
                    }
                }
                $i = $semi;
                continue;
            }
            if ($id === '&' && $i + 1 < $end && $this->tokens[$i + 1]['id'] === T_VARIABLE) {
                $prevId = $i > $start ? $this->tokens[$i - 1]['id'] : null;
                if (
                    $prevId === '='
                    || $prevId === '('
                    || $prevId === ','
                    || $prevId === '['
                    || $prevId === T_AS
                    || $prevId === T_DOUBLE_ARROW
                ) {
                    $aliased[$this->tokens[$i + 1]['text']] = true;
                }
            }
        }

        return $aliased;
    }

    /**
     * @param array<string, true> $aliased
     */
    private function scanClosureUseAndSkipBody(int $fnIdx, int $limit, array &$aliased): int
    {
        $openParen = $this->findNext($fnIdx + 1, $limit, '(');
        if ($openParen === null) {
            return $fnIdx;
        }
        $closeParen = $this->findMatching($openParen, $limit, '(', ')');
        if ($closeParen === null) {
            return $fnIdx;
        }

        $cursor = $closeParen + 1;
        while ($cursor < $limit && $this->tokens[$cursor]['id'] !== '{' && $this->tokens[$cursor]['id'] !== ';') {
            if ($this->tokens[$cursor]['id'] === T_USE && $cursor + 1 < $limit && $this->tokens[$cursor + 1]['id'] === '(') {
                $useClose = $this->findMatching($cursor + 1, $limit, '(', ')');
                if ($useClose !== null) {
                    for ($k = $cursor + 2; $k < $useClose; ++$k) {
                        if ($this->tokens[$k]['id'] === '&' && $k + 1 < $useClose && $this->tokens[$k + 1]['id'] === T_VARIABLE) {
                            $aliased[$this->tokens[$k + 1]['text']] = true;
                        }
                    }
                    $cursor = $useClose + 1;
                    continue;
                }
            }
            ++$cursor;
        }

        if ($cursor < $limit && $this->tokens[$cursor]['id'] === '{') {
            $closeBrace = $this->findMatching($cursor, $limit, '{', '}');
            if ($closeBrace !== null) {
                return $closeBrace;
            }
        }

        return $cursor;
    }

    /**
     * @param ?array<string, string> $env
     * @return ?array<string, string>
     */
    private function walkBlock(int $start, int $end, ?array $env): ?array
    {
        $cursor = $start;
        while ($cursor < $end) {
            [$env, $next] = $this->walkStatement($cursor, $end, $env);
            if ($next <= $cursor) {
                ++$cursor;
            } else {
                $cursor = $next;
            }
        }

        return $env;
    }

    /**
     * @param ?array<string, string> $env
     * @return array{0: ?array<string, string>, 1: int}
     */
    private function walkStatement(int $i, int $limit, ?array $env): array
    {
        if ($i >= $limit) {
            return [$env, $limit];
        }

        if ($env !== null) {
            $this->recordTryState($env);
        }

        $id = $this->tokens[$i]['id'];

        if ($id === ';') {
            return [$env, $i + 1];
        }

        if ($id === '{') {
            $close = $this->findMatching($i, $limit, '{', '}');
            if ($close === null) {
                return [null, $limit];
            }

            return [$this->walkBlock($i + 1, $close, $env), $close + 1];
        }

        if ($id === T_IF) {
            return $this->walkIf($i, $limit, $env);
        }

        if ($id === T_WHILE) {
            return $this->walkWhile($i, $limit, $env);
        }

        if ($id === T_DO) {
            return $this->walkDoWhile($i, $limit, $env);
        }

        if ($id === T_FOR) {
            return $this->walkFor($i, $limit, $env);
        }

        if ($id === T_FOREACH) {
            return $this->walkForeach($i, $limit, $env);
        }

        if ($id === T_SWITCH) {
            return $this->walkSwitch($i, $limit, $env);
        }

        if ($id === T_TRY) {
            return $this->walkTry($i, $limit, $env);
        }

        if ($id === T_RETURN) {
            $semi = $this->findStatementEnd($i + 1, $limit);
            $endByte = $this->tokens[$semi]['end'] ?? $this->tokens[$limit - 1]['end'];
            if ($env !== null) {
                $workEnv = $env;
                $exprType = $semi > $i + 1 ? $this->evalExprType($i + 1, $semi, $workEnv) : null;
                $this->recordTryState($workEnv);
                if ($this->tokens[$i]['start'] <= $this->targetOffset && $this->targetOffset <= $endByte) {
                    $this->recordTargetRootType($exprType);
                }
            }

            return [null, min($semi + 1, $limit)];
        }

        if ($id === T_THROW) {
            $semi = $this->findStatementEnd($i + 1, $limit);
            if ($env !== null && $semi > $i + 1) {
                $throwEnv = $env;
                $this->evalExprType($i + 1, $semi, $throwEnv);
                $this->recordTryState($throwEnv);
            }

            return [null, min($semi + 1, $limit)];
        }

        if ($id === T_BREAK || $id === T_CONTINUE) {
            $semi = $this->findStatementEnd($i + 1, $limit);
            $levels = 1;
            if ($semi === $i + 2 && $this->tokens[$i + 1]['id'] === T_LNUMBER) {
                $levels = max(1, (int) $this->tokens[$i + 1]['text']);
            }
            if ($env !== null) {
                $this->recordTryState($env, false);
                $this->recordJump($id === T_BREAK, $levels, $env);
            }

            return [null, min($semi + 1, $limit)];
        }

        if ($id === T_GLOBAL) {
            $semi = $this->findStatementEnd($i + 1, $limit);
            if ($env !== null) {
                for ($k = $i + 1; $k < $semi; ++$k) {
                    if ($this->tokens[$k]['id'] === T_VARIABLE) {
                        unset($env[$this->tokens[$k]['text']]);
                    }
                }
                $this->recordTryState($env);
            }

            return [$env, min($semi + 1, $limit)];
        }

        if ($id === T_UNSET) {
            $semi = $this->findStatementEnd($i + 1, $limit);
            if ($env !== null && $i + 1 < $limit && $this->tokens[$i + 1]['id'] === '(') {
                $close = $this->findMatching($i + 1, $semi, '(', ')');
                if ($close !== null) {
                    for ($k = $i + 2; $k < $close; ++$k) {
                        if (
                            $this->tokens[$k]['id'] === T_VARIABLE
                            && ($k + 1 >= $close || $this->tokens[$k + 1]['id'] === ',' || $this->tokens[$k + 1]['id'] === ')')
                        ) {
                            unset($env[$this->tokens[$k]['text']]);
                        }
                    }
                }
                $this->recordTryState($env);
            }

            return [$env, min($semi + 1, $limit)];
        }

        if ($id === T_FUNCTION && $i + 1 < $limit && $this->tokens[$i + 1]['id'] === T_STRING) {
            $dummy = [];
            $endFn = $this->scanClosureUseAndSkipBody($i, $limit, $dummy);

            return [$env, min($endFn + 1, $limit)];
        }

        $semi = $this->findStatementEnd($i, $limit);
        if ($env !== null) {
            $this->evalExprType($i, $semi, $env);
            $this->recordTryState($env);
        }

        return [$env, min($semi + 1, $limit)];
    }

    /**
     * @param ?array<string, string> $env
     * @return array{0: ?array<string, string>, 1: int}
     */
    private function walkIf(int $i, int $limit, ?array $env): array
    {
        $openParen = $this->findNext($i + 1, $limit, '(');
        if ($openParen === null) {
            return [$env, $i + 1];
        }
        $closeParen = $this->findMatching($openParen, $limit, '(', ')');
        if ($closeParen === null) {
            return [$env, $limit];
        }

        $condEnv = $env;
        if ($condEnv !== null) {
            $this->evalExprType($openParen + 1, $closeParen, $condEnv);
            $this->recordTryState($condEnv);
        }

        $colonSyntax = ($closeParen + 1 < $limit && $this->tokens[$closeParen + 1]['id'] === ':');
        if ($colonSyntax) {
            return $this->walkColonIf($closeParen + 2, $limit, $condEnv);
        }

        [$thenOut, $cursor] = $this->walkStatement($closeParen + 1, $limit, $condEnv);
        $joinedOut = $thenOut;
        $falseEnv = $condEnv;
        $hasElse = false;

        while ($cursor < $limit) {
            $nextId = $this->tokens[$cursor]['id'];
            if ($nextId === T_ELSEIF) {
                $eOpen = $this->findNext($cursor + 1, $limit, '(');
                if ($eOpen === null) {
                    break;
                }
                $eClose = $this->findMatching($eOpen, $limit, '(', ')');
                if ($eClose === null) {
                    return [$joinedOut, $limit];
                }
                $eCondEnv = $falseEnv;
                if ($eCondEnv !== null) {
                    $this->evalExprType($eOpen + 1, $eClose, $eCondEnv);
                    $this->recordTryState($eCondEnv);
                }
                [$eOut, $cursor] = $this->walkStatement($eClose + 1, $limit, $eCondEnv);
                $joinedOut = self::joinEnv($joinedOut, $eOut);
                $falseEnv = $eCondEnv;
                continue;
            }
            if ($nextId === T_ELSE) {
                $hasElse = true;
                [$elseOut, $cursor] = $this->walkStatement($cursor + 1, $limit, $falseEnv);
                $joinedOut = self::joinEnv($joinedOut, $elseOut);
                break;
            }
            break;
        }

        if (!$hasElse) {
            $joinedOut = self::joinEnv($joinedOut, $falseEnv);
        }

        return [$joinedOut, $cursor];
    }

    /**
     * @param ?array<string, string> $condEnv
     * @return array{0: ?array<string, string>, 1: int}
     */
    private function walkColonIf(int $bodyStart, int $limit, ?array $condEnv): array
    {
        $cursor = $bodyStart;
        $branchEnv = $condEnv;
        $falseEnv = $condEnv;
        $joinedOut = null;
        $hasElse = false;

        while ($cursor < $limit) {
            $id = $this->tokens[$cursor]['id'];
            if ($id === T_ELSEIF) {
                $joinedOut = self::joinEnv($joinedOut, $branchEnv);
                $eOpen = $this->findNext($cursor + 1, $limit, '(');
                $eClose = $eOpen !== null ? $this->findMatching($eOpen, $limit, '(', ')') : null;
                if ($eClose === null) {
                    return [$joinedOut, $limit];
                }
                $branchEnv = $falseEnv;
                if ($branchEnv !== null) {
                    $this->evalExprType($eOpen + 1, $eClose, $branchEnv);
                    $this->recordTryState($branchEnv);
                }
                $falseEnv = $branchEnv;
                $cursor = $eClose + 1;
                if ($cursor < $limit && $this->tokens[$cursor]['id'] === ':') {
                    ++$cursor;
                }
                continue;
            }
            if ($id === T_ELSE) {
                $joinedOut = self::joinEnv($joinedOut, $branchEnv);
                $hasElse = true;
                $branchEnv = $falseEnv;
                ++$cursor;
                if ($cursor < $limit && $this->tokens[$cursor]['id'] === ':') {
                    ++$cursor;
                }
                continue;
            }
            if ($id === T_ENDIF) {
                $joinedOut = self::joinEnv($joinedOut, $branchEnv);
                if (!$hasElse) {
                    $joinedOut = self::joinEnv($joinedOut, $falseEnv);
                }
                ++$cursor;
                if ($cursor < $limit && $this->tokens[$cursor]['id'] === ';') {
                    ++$cursor;
                }

                return [$joinedOut, $cursor];
            }

            [$branchEnv, $next] = $this->walkStatement($cursor, $limit, $branchEnv);
            $cursor = $next > $cursor ? $next : $cursor + 1;
        }

        return [self::joinEnv($joinedOut, $branchEnv), $limit];
    }

    /**
     * @param ?array<string, string> $env
     * @return array{0: ?array<string, string>, 1: int}
     */
    private function walkWhile(int $i, int $limit, ?array $env): array
    {
        $openParen = $this->findNext($i + 1, $limit, '(');
        $closeParen = $openParen !== null ? $this->findMatching($openParen, $limit, '(', ')') : null;
        if ($closeParen === null) {
            return [$env, $limit];
        }

        [$bodyStart, $bodyEnd, $afterLoop] = $this->findLoopBodyRange($closeParen + 1, $limit, T_ENDWHILE);

        $loopEntry = $env;
        $breakEnv = null;
        $maxPasses = max(8, count($loopEntry ?? []) + 4);
        for ($pass = 0; $pass < $maxPasses; ++$pass) {
            $condEnv = $loopEntry;
            if ($condEnv !== null) {
                $this->evalExprType($openParen + 1, $closeParen, $condEnv);
                $this->recordTryState($condEnv);
            }
            $this->breakStack[] = ['breakEnv' => null, 'continueEnv' => null, 'isLoop' => true];
            $bodyOut = $this->walkBlock($bodyStart, $bodyEnd, $condEnv);
            $frame = array_pop($this->breakStack);
            $breakEnv = self::joinEnv($breakEnv, $frame['breakEnv']);
            $nextEntry = self::joinEnv($loopEntry, self::joinEnv($bodyOut, $frame['continueEnv']));
            if ($nextEntry === $loopEntry) {
                break;
            }
            $loopEntry = $nextEntry;
        }

        $exitCondEnv = $loopEntry;
        if ($exitCondEnv !== null) {
            $this->evalExprType($openParen + 1, $closeParen, $exitCondEnv);
            $this->recordTryState($exitCondEnv);
        }

        return [self::joinEnv($exitCondEnv, $breakEnv), $afterLoop];
    }

    /**
     * @param ?array<string, string> $env
     * @return array{0: ?array<string, string>, 1: int}
     */
    private function walkDoWhile(int $i, int $limit, ?array $env): array
    {
        [$bodyStart, $bodyEnd, $afterBody] = $this->findLoopBodyRange($i + 1, $limit, null);

        $condStart = null;
        $condEnd = null;
        $afterLoop = $afterBody;
        if ($afterBody < $limit && $this->tokens[$afterBody]['id'] === T_WHILE) {
            $openParen = $this->findNext($afterBody + 1, $limit, '(');
            $closeParen = $openParen !== null ? $this->findMatching($openParen, $limit, '(', ')') : null;
            if ($closeParen !== null) {
                $condStart = $openParen + 1;
                $condEnd = $closeParen;
                $afterLoop = $closeParen + 1;
                if ($afterLoop < $limit && $this->tokens[$afterLoop]['id'] === ';') {
                    ++$afterLoop;
                }
            }
        }

        $isLiteralFalse = $condStart !== null
            && $condEnd === $condStart + 1
            && $this->tokens[$condStart]['id'] === T_STRING
            && strtolower($this->tokens[$condStart]['text']) === 'false';

        $iterIn = $env;
        $breakEnv = null;
        $exitViaCond = null;
        $maxPasses = $isLiteralFalse ? 1 : max(8, count($iterIn ?? []) + 4);
        for ($pass = 0; $pass < $maxPasses; ++$pass) {
            $this->breakStack[] = ['breakEnv' => null, 'continueEnv' => null, 'isLoop' => true];
            $bodyOut = $this->walkBlock($bodyStart, $bodyEnd, $iterIn);
            $frame = array_pop($this->breakStack);
            $breakEnv = self::joinEnv($breakEnv, $frame['breakEnv']);
            $beforeCond = self::joinEnv($bodyOut, $frame['continueEnv']);
            if ($beforeCond !== null && $condStart !== null && $condEnd !== null) {
                $this->evalExprType($condStart, $condEnd, $beforeCond);
                $this->recordTryState($beforeCond);
            }
            $exitViaCond = self::joinEnv($exitViaCond, $beforeCond);
            $nextIn = self::joinEnv($iterIn, $beforeCond);
            if ($nextIn === $iterIn) {
                break;
            }
            $iterIn = $nextIn;
        }

        return [self::joinEnv($exitViaCond, $breakEnv), $afterLoop];
    }

    /**
     * @param ?array<string, string> $env
     * @return array{0: ?array<string, string>, 1: int}
     */
    private function walkFor(int $i, int $limit, ?array $env): array
    {
        $openParen = $this->findNext($i + 1, $limit, '(');
        $closeParen = $openParen !== null ? $this->findMatching($openParen, $limit, '(', ')') : null;
        if ($closeParen === null) {
            return [$env, $limit];
        }

        $parts = $this->splitTopLevel($openParen + 1, $closeParen, ';');
        $afterInit = $env;
        if ($afterInit !== null && isset($parts[0]) && $parts[0][0] < $parts[0][1]) {
            $this->evalCommaSeparated($parts[0][0], $parts[0][1], $afterInit);
            $this->recordTryState($afterInit);
        }

        [$bodyStart, $bodyEnd, $afterLoop] = $this->findLoopBodyRange($closeParen + 1, $limit, T_ENDFOR);

        $loopEntry = $afterInit;
        $breakEnv = null;
        $maxPasses = max(8, count($loopEntry ?? []) + 4);
        for ($pass = 0; $pass < $maxPasses; ++$pass) {
            $condEnv = $loopEntry;
            if ($condEnv !== null && isset($parts[1]) && $parts[1][0] < $parts[1][1]) {
                $this->evalCommaSeparated($parts[1][0], $parts[1][1], $condEnv);
                $this->recordTryState($condEnv);
            }
            $this->breakStack[] = ['breakEnv' => null, 'continueEnv' => null, 'isLoop' => true];
            $bodyOut = $this->walkBlock($bodyStart, $bodyEnd, $condEnv);
            $frame = array_pop($this->breakStack);
            $breakEnv = self::joinEnv($breakEnv, $frame['breakEnv']);
            $stepEnv = self::joinEnv($bodyOut, $frame['continueEnv']);
            if ($stepEnv !== null && isset($parts[2]) && $parts[2][0] < $parts[2][1]) {
                $this->evalCommaSeparated($parts[2][0], $parts[2][1], $stepEnv);
                $this->recordTryState($stepEnv);
            }
            $nextEntry = self::joinEnv($loopEntry, $stepEnv);
            if ($nextEntry === $loopEntry) {
                break;
            }
            $loopEntry = $nextEntry;
        }

        $exitCondEnv = $loopEntry;
        if ($exitCondEnv !== null && isset($parts[1]) && $parts[1][0] < $parts[1][1]) {
            $this->evalCommaSeparated($parts[1][0], $parts[1][1], $exitCondEnv);
            $this->recordTryState($exitCondEnv);
        }

        return [self::joinEnv($exitCondEnv, $breakEnv), $afterLoop];
    }

    /**
     * @param ?array<string, string> $env
     * @return array{0: ?array<string, string>, 1: int}
     */
    private function walkForeach(int $i, int $limit, ?array $env): array
    {
        $openParen = $this->findNext($i + 1, $limit, '(');
        $closeParen = $openParen !== null ? $this->findMatching($openParen, $limit, '(', ')') : null;
        if ($closeParen === null) {
            return [$env, $limit];
        }

        $asIdx = $this->findTopLevelToken($openParen + 1, $closeParen, T_AS);
        $afterIter = $env;
        if ($afterIter !== null && $asIdx !== null) {
            $this->evalExprType($openParen + 1, $asIdx, $afterIter);
            $this->recordTryState($afterIter);
        }

        $loopVars = [];
        if ($asIdx !== null) {
            for ($k = $asIdx + 1; $k < $closeParen; ++$k) {
                if ($this->tokens[$k]['id'] === T_VARIABLE) {
                    $loopVars[] = $this->tokens[$k]['text'];
                }
            }
        }

        [$bodyStart, $bodyEnd, $afterLoop] = $this->findLoopBodyRange($closeParen + 1, $limit, T_ENDFOREACH);

        $loopEntry = $afterIter;
        $breakEnv = null;
        $maxPasses = max(8, count($loopEntry ?? []) + 4);
        for ($pass = 0; $pass < $maxPasses; ++$pass) {
            $bodyIn = $loopEntry;
            if ($bodyIn !== null) {
                foreach ($loopVars as $lv) {
                    unset($bodyIn[$lv]);
                }
            }
            $this->breakStack[] = ['breakEnv' => null, 'continueEnv' => null, 'isLoop' => true];
            $bodyOut = $this->walkBlock($bodyStart, $bodyEnd, $bodyIn);
            $frame = array_pop($this->breakStack);
            $breakEnv = self::joinEnv($breakEnv, $frame['breakEnv']);
            $nextEntry = self::joinEnv($loopEntry, self::joinEnv($bodyOut, $frame['continueEnv']));
            if ($nextEntry === $loopEntry) {
                break;
            }
            $loopEntry = $nextEntry;
        }

        return [self::joinEnv($loopEntry, $breakEnv), $afterLoop];
    }

    /**
     * @param ?array<string, string> $env
     * @return array{0: ?array<string, string>, 1: int}
     */
    private function walkSwitch(int $i, int $limit, ?array $env): array
    {
        $openParen = $this->findNext($i + 1, $limit, '(');
        $closeParen = $openParen !== null ? $this->findMatching($openParen, $limit, '(', ')') : null;
        if ($closeParen === null) {
            return [$env, $limit];
        }

        $switchEntry = $env;
        if ($switchEntry !== null) {
            $this->evalExprType($openParen + 1, $closeParen, $switchEntry);
            $this->recordTryState($switchEntry);
        }

        $bodyOpen = $closeParen + 1;
        if ($bodyOpen >= $limit) {
            return [$switchEntry, $limit];
        }

        $colonSyntax = ($this->tokens[$bodyOpen]['id'] === ':');
        if ($colonSyntax) {
            $bodyStart = $bodyOpen + 1;
            $bodyEnd = $this->findMatchingColonEnd($bodyStart, $limit, T_SWITCH, T_ENDSWITCH);
            $afterSwitch = $bodyEnd < $limit ? $bodyEnd + 1 : $limit;
            if ($afterSwitch < $limit && $this->tokens[$afterSwitch]['id'] === ';') {
                ++$afterSwitch;
            }
        } elseif ($this->tokens[$bodyOpen]['id'] === '{') {
            $bodyStart = $bodyOpen + 1;
            $bodyEnd = $this->findMatching($bodyOpen, $limit, '{', '}') ?? $limit;
            $afterSwitch = min($bodyEnd + 1, $limit);
        } else {
            return [$switchEntry, $bodyOpen];
        }

        $this->breakStack[] = ['breakEnv' => null, 'continueEnv' => null, 'isLoop' => false];
        $curEnv = null;
        $hasDefault = false;
        $cursor = $bodyStart;

        while ($cursor < $bodyEnd) {
            $id = $this->tokens[$cursor]['id'];
            if ($id === T_CASE) {
                $colon = $this->findCaseColon($cursor + 1, $bodyEnd);
                $caseEntry = $switchEntry;
                if ($caseEntry !== null && $colon !== null) {
                    $this->evalExprType($cursor + 1, $colon, $caseEntry);
                    $this->recordTryState($caseEntry);
                }
                $curEnv = self::joinEnv($curEnv, $caseEntry);
                $cursor = $colon !== null ? $colon + 1 : $cursor + 1;
                continue;
            }
            if ($id === T_DEFAULT) {
                $hasDefault = true;
                $colon = $this->findCaseColon($cursor + 1, $bodyEnd);
                $curEnv = self::joinEnv($curEnv, $switchEntry);
                $cursor = $colon !== null ? $colon + 1 : $cursor + 1;
                continue;
            }

            [$curEnv, $next] = $this->walkStatement($cursor, $bodyEnd, $curEnv);
            $cursor = $next > $cursor ? $next : $cursor + 1;
        }

        $frame = array_pop($this->breakStack);
        $out = self::joinEnv($curEnv, self::joinEnv($frame['breakEnv'], $frame['continueEnv']));
        if (!$hasDefault) {
            $out = self::joinEnv($out, $switchEntry);
        }

        return [$out, $afterSwitch];
    }

    /**
     * @param ?array<string, string> $env
     * @return array{0: ?array<string, string>, 1: int}
     */
    private function walkTry(int $i, int $limit, ?array $env): array
    {
        $tryOpen = $this->findNext($i + 1, $limit, '{');
        $tryClose = $tryOpen !== null ? $this->findMatching($tryOpen, $limit, '{', '}') : null;
        if ($tryClose === null) {
            return [$env, $limit];
        }

        $savedBreaks = $this->breakStack;
        $this->tryStack[] = [
            'catchEntry' => $env,
            'finallyEntry' => $env,
            'inTryBody' => true,
        ];
        $tryEnv = $this->walkBlock($tryOpen + 1, $tryClose, $env);
        $tryFrameIdx = count($this->tryStack) - 1;
        $this->tryStack[$tryFrameIdx]['inTryBody'] = false;
        $catchEntry = self::joinEnv($this->tryStack[$tryFrameIdx]['catchEntry'], $tryEnv);

        $afterTryCatch = $tryEnv;
        $cursor = $tryClose + 1;

        while ($cursor < $limit && $this->tokens[$cursor]['id'] === T_CATCH) {
            $openParen = $this->findNext($cursor + 1, $limit, '(');
            $closeParen = $openParen !== null ? $this->findMatching($openParen, $limit, '(', ')') : null;
            if ($closeParen === null) {
                array_pop($this->tryStack);

                return [$afterTryCatch, $limit];
            }
            $catchIn = $catchEntry;
            if ($catchIn !== null) {
                for ($k = $openParen + 1; $k < $closeParen; ++$k) {
                    if ($this->tokens[$k]['id'] === T_VARIABLE) {
                        unset($catchIn[$this->tokens[$k]['text']]);
                    }
                }
            }
            $cOpen = $this->findNext($closeParen + 1, $limit, '{');
            $cClose = $cOpen !== null ? $this->findMatching($cOpen, $limit, '{', '}') : null;
            if ($cClose === null) {
                array_pop($this->tryStack);

                return [$afterTryCatch, $limit];
            }
            $catchOut = $this->walkBlock($cOpen + 1, $cClose, $catchIn);
            $afterTryCatch = self::joinEnv($afterTryCatch, $catchOut);
            $cursor = $cClose + 1;
        }

        $tryFrame = array_pop($this->tryStack);

        if ($cursor < $limit && $this->tokens[$cursor]['id'] === T_FINALLY) {
            $fOpen = $this->findNext($cursor + 1, $limit, '{');
            $fClose = $fOpen !== null ? $this->findMatching($fOpen, $limit, '{', '}') : null;
            if ($fClose === null) {
                return [$afterTryCatch, $limit];
            }
            $targetInsideFinally = $this->tokens[$fOpen]['start'] <= $this->targetOffset
                && $this->targetOffset <= $this->tokens[$fClose]['end'];
            $finallyEntry = $tryFrame['finallyEntry'];
            foreach ($savedBreaks as $bIdx => $savedFrame) {
                if (!isset($this->breakStack[$bIdx])) {
                    continue;
                }
                if ($this->breakStack[$bIdx]['breakEnv'] !== $savedFrame['breakEnv']) {
                    $jumpOut = $this->walkBlock($fOpen + 1, $fClose, $this->breakStack[$bIdx]['breakEnv']);
                    $this->breakStack[$bIdx]['breakEnv'] = self::joinEnv($savedFrame['breakEnv'], $jumpOut);
                }
                if ($this->breakStack[$bIdx]['continueEnv'] !== $savedFrame['continueEnv']) {
                    $jumpOut = $this->walkBlock($fOpen + 1, $fClose, $this->breakStack[$bIdx]['continueEnv']);
                    $this->breakStack[$bIdx]['continueEnv'] = self::joinEnv($savedFrame['continueEnv'], $jumpOut);
                }
            }
            if ($targetInsideFinally) {
                $finallyIn = self::joinEnv($afterTryCatch, $finallyEntry);
                $finallyOut = $this->walkBlock($fOpen + 1, $fClose, $finallyIn);
            } elseif ($afterTryCatch !== null) {
                $finallyOut = $this->walkBlock($fOpen + 1, $fClose, $afterTryCatch);
            } else {
                if ($this->tryStack !== []) {
                    $this->walkBlock($fOpen + 1, $fClose, $finallyEntry);
                }
                $finallyOut = null;
            }
            $afterTryCatch = $afterTryCatch !== null ? $finallyOut : null;
            $cursor = $fClose + 1;
        }

        return [$afterTryCatch, $cursor];
    }

    /**
     * @param array<string, string> $env
     */
    private function evalExprType(int $start, int $end, array &$env): ?string
    {
        [$start, $end] = $this->stripOuterParens($start, $end);
        if ($start >= $end) {
            return null;
        }

        if ($this->tokens[$start]['id'] === '[') {
            $close = $this->findMatching($start, $end, '[', ']');
            if ($close !== null && $close + 1 < $end && $this->tokens[$close + 1]['id'] === '=') {
                $this->evalExprType($close + 2, $end, $env);
                for ($k = $start + 1; $k < $close; ++$k) {
                    if ($this->tokens[$k]['id'] === T_VARIABLE) {
                        unset($env[$this->tokens[$k]['text']]);
                    }
                }

                return null;
            }
        }
        if ($this->tokens[$start]['id'] === T_LIST && $start + 1 < $end && $this->tokens[$start + 1]['id'] === '(') {
            $close = $this->findMatching($start + 1, $end, '(', ')');
            if ($close !== null && $close + 1 < $end && $this->tokens[$close + 1]['id'] === '=') {
                $this->evalExprType($close + 2, $end, $env);
                for ($k = $start + 2; $k < $close; ++$k) {
                    if ($this->tokens[$k]['id'] === T_VARIABLE) {
                        unset($env[$this->tokens[$k]['text']]);
                    }
                }

                return null;
            }
        }

        if ($this->tokens[$start]['id'] === T_VARIABLE && $start + 1 < $end) {
            $op = $this->tokens[$start + 1]['id'];
            if ($op === '=') {
                $varName = $this->tokens[$start]['text'];
                $rhsType = $this->evalExprType($start + 2, $end, $env);
                if ($rhsType !== null && !isset($this->aliasedVars[$varName])) {
                    $env[$varName] = $rhsType;
                } else {
                    unset($env[$varName]);
                }

                return $rhsType;
            }
            if ($op === T_COALESCE_EQUAL) {
                $varName = $this->tokens[$start]['text'];
                $rhsEnv = $env;
                $rhsType = $this->evalExprType($start + 2, $end, $rhsEnv);
                $merged = self::joinType($env[$varName] ?? null, $rhsType);
                $env = self::joinEnv($env, $rhsEnv) ?? [];
                if ($merged !== null && !isset($this->aliasedVars[$varName])) {
                    $env[$varName] = $merged;
                } else {
                    unset($env[$varName]);
                }

                return $merged;
            }
        }

        if ($end - $start === 1 && $this->tokens[$start]['id'] === T_VARIABLE) {
            $varName = $this->tokens[$start]['text'];
            if (isset($this->aliasedVars[$varName])) {
                return null;
            }

            return $env[$varName] ?? null;
        }

        if ($this->tokens[$start]['id'] === T_NEW) {
            $constructed = $this->parseNewTreeBuilder($start, $end, $env);
            if ($constructed !== false) {
                return $constructed;
            }
        }

        $qIdx = $this->findTopLevelToken($start, $end, '?');
        if ($qIdx !== null) {
            $colonIdx = $this->findTernaryColon($qIdx + 1, $end);
            if ($colonIdx !== null) {
                $condType = $this->evalExprType($start, $qIdx, $env);
                $thenEnv = $env;
                $elseEnv = $env;
                $thenType = $colonIdx === $qIdx + 1
                    ? $condType
                    : $this->evalExprType($qIdx + 1, $colonIdx, $thenEnv);
                $elseType = $this->evalExprType($colonIdx + 1, $end, $elseEnv);
                $env = self::joinEnv($thenEnv, $elseEnv) ?? [];

                return self::joinType($thenType, $elseType);
            }
        }

        $coalesceIdx = $this->findTopLevelToken($start, $end, T_COALESCE);
        if ($coalesceIdx !== null) {
            $lhsType = $this->evalExprType($start, $coalesceIdx, $env);
            $rhsEnv = $env;
            $rhsType = $this->evalExprType($coalesceIdx + 1, $end, $rhsEnv);
            $env = self::joinEnv($env, $rhsEnv) ?? [];

            return self::joinType($lhsType, $rhsType);
        }

        if ($this->tokens[$start]['id'] === T_MATCH) {
            return $this->evalMatchExpr($start, $end, $env);
        }

        if ($this->tokens[$start]['id'] === T_THROW) {
            if ($start + 1 < $end) {
                $this->evalExprType($start + 1, $end, $env);
            }
            $this->recordTryState($env);

            return null;
        }

        $this->scanExpressionSideEffects($start, $end, $env);

        return null;
    }

    /**
     * @param array<string, string> $env
     * @return string|null|false
     */
    private function parseNewTreeBuilder(int $start, int $end, array &$env): string|null|false
    {
        $classIdx = $start + 1;
        if ($classIdx >= $end) {
            return false;
        }
        $classId = $this->tokens[$classIdx]['id'];
        if ($classId !== T_STRING && $classId !== T_NAME_QUALIFIED && $classId !== T_NAME_FULLY_QUALIFIED) {
            return false;
        }

        if (!$this->isTreeBuilderClassName($this->tokens[$classIdx]['text'], $classId === T_NAME_FULLY_QUALIFIED)) {
            return false;
        }

        $openParen = $classIdx + 1;
        if ($openParen >= $end) {
            return 'array';
        }
        if ($this->tokens[$openParen]['id'] !== '(') {
            return false;
        }
        $closeParen = $this->findMatching($openParen, $end, '(', ')');
        if ($closeParen === null || $closeParen !== $end - 1) {
            return false;
        }

        $this->scanExpressionSideEffects($openParen + 1, $closeParen, $env);

        $argRanges = $this->splitTopLevel($openParen + 1, $closeParen, ',');
        foreach ($argRanges as [$aStart, $aEnd]) {
            if ($aStart < $aEnd && $this->tokens[$aStart]['id'] === T_ELLIPSIS) {
                return null;
            }
        }
        $positional = 0;
        $hasTypeArg = false;
        foreach ($argRanges as [$aStart, $aEnd]) {
            if ($aStart >= $aEnd) {
                continue;
            }
            if ($aStart + 2 <= $aEnd && $this->tokens[$aStart]['id'] === T_STRING && $this->tokens[$aStart + 1]['id'] === ':') {
                if ($this->tokens[$aStart]['text'] === 'type') {
                    $hasTypeArg = true;

                    return $this->extractLiteralRootType($aStart + 2, $aEnd);
                }
                continue;
            }
            if ($positional === 1) {
                $hasTypeArg = true;

                return $this->extractLiteralRootType($aStart, $aEnd);
            }
            ++$positional;
        }

        return $hasTypeArg ? null : 'array';
    }

    /**
     * @param array<string, string> $env
     */
    private function evalMatchExpr(int $start, int $end, array &$env): ?string
    {
        $openParen = $this->findNext($start + 1, $end, '(');
        $closeParen = $openParen !== null ? $this->findMatching($openParen, $end, '(', ')') : null;
        if ($closeParen === null) {
            return null;
        }
        $this->evalExprType($openParen + 1, $closeParen, $env);

        $openBrace = $closeParen + 1;
        if ($openBrace >= $end || $this->tokens[$openBrace]['id'] !== '{') {
            return null;
        }
        $closeBrace = $this->findMatching($openBrace, $end, '{', '}');
        if ($closeBrace === null || $closeBrace !== $end - 1) {
            return null;
        }

        $arms = $this->splitTopLevel($openBrace + 1, $closeBrace, ',');
        $joinedType = null;
        $hasValueArm = false;
        $joinedEnv = null;

        foreach ($arms as [$armStart, $armEnd]) {
            if ($armStart >= $armEnd) {
                continue;
            }
            $arrow = $this->findTopLevelToken($armStart, $armEnd, T_DOUBLE_ARROW);
            if ($arrow === null) {
                continue;
            }
            $armEnv = $env;
            $this->scanExpressionSideEffects($armStart, $arrow, $armEnv);
            $valStart = $arrow + 1;
            if ($valStart < $armEnd && $this->tokens[$valStart]['id'] === T_THROW) {
                if ($valStart + 1 < $armEnd) {
                    $this->evalExprType($valStart + 1, $armEnd, $armEnv);
                }
                $this->recordTryState($armEnv);
                continue;
            }
            $armType = $this->evalExprType($valStart, $armEnd, $armEnv);
            $joinedEnv = self::joinEnv($joinedEnv, $armEnv);
            if (!$hasValueArm) {
                $joinedType = $armType;
                $hasValueArm = true;
            } else {
                $joinedType = self::joinType($joinedType, $armType);
            }
        }

        if ($joinedEnv !== null) {
            $env = $joinedEnv;
        }

        return $hasValueArm ? $joinedType : null;
    }

    /**
     * @param array<string, string> $env
     */
    private function scanExpressionSideEffects(int $start, int $end, array &$env): void
    {
        for ($i = $start; $i < $end; ++$i) {
            $id = $this->tokens[$i]['id'];

            if ($id === T_FUNCTION) {
                $dummy = [];
                $i = $this->scanClosureUseAndSkipBody($i, $end, $dummy);
                continue;
            }
            if ($id === T_FN) {
                $arrow = $this->findArrowDoubleArrow($i, $end);
                if ($arrow !== null) {
                    $i = $this->findArrowExprEnd($arrow + 1, $end) - 1;
                }
                continue;
            }
            if ($id === T_CLASS) {
                $parts = $this->findAnonymousClassParts($i, $end);
                if ($parts !== null) {
                    [$ctorRange, $closeBrace] = $parts;
                    if ($ctorRange !== null) {
                        $this->scanExpressionSideEffects($ctorRange[0], $ctorRange[1], $env);
                    }
                    $i = $closeBrace;
                    continue;
                }
            }
            if ($id === T_THROW) {
                $this->recordTryState($env);
                continue;
            }

            if (
                $id === T_EVAL
                || $id === T_INCLUDE
                || $id === T_INCLUDE_ONCE
                || $id === T_REQUIRE
                || $id === T_REQUIRE_ONCE
            ) {
                $env = [];

                return;
            }
            if ($id === T_VARIABLE && $this->tokens[$i]['text'] === '$GLOBALS') {
                $env = [];

                return;
            }
            if (
                $id === '('
                && $i > $start
                && (
                    $this->tokens[$i - 1]['id'] === T_STRING
                    || $this->tokens[$i - 1]['id'] === T_NAME_QUALIFIED
                    || $this->tokens[$i - 1]['id'] === T_NAME_FULLY_QUALIFIED
                    || $this->tokens[$i - 1]['id'] === T_VARIABLE
                    || $this->tokens[$i - 1]['id'] === ')'
                    || $this->tokens[$i - 1]['id'] === ']'
                )
            ) {
                $closeCall = $this->findMatching($i, $end, '(', ')');
                if ($closeCall !== null) {
                    foreach ($this->splitTopLevel($i + 1, $closeCall, ',') as [$aStart, $aEnd]) {
                        [$aStart, $aEnd] = $this->stripOuterParens($aStart, $aEnd);
                        if (
                            $aStart + 2 <= $aEnd
                            && $this->tokens[$aStart]['id'] === T_STRING
                            && $this->tokens[$aStart + 1]['id'] === ':'
                        ) {
                            [$aStart, $aEnd] = $this->stripOuterParens($aStart + 2, $aEnd);
                        }
                        if ($aEnd - $aStart === 1 && $this->tokens[$aStart]['id'] === T_VARIABLE) {
                            unset($env[$this->tokens[$aStart]['text']]);
                        }
                    }
                }
            }
            if (
                $id === T_STRING
                && strtolower($this->tokens[$i]['text']) === 'extract'
                && $i + 1 < $end
                && $this->tokens[$i + 1]['id'] === '('
            ) {
                $env = [];

                return;
            }
            if ($id === '$' && $i + 1 < $end && ($this->tokens[$i + 1]['id'] === T_VARIABLE || $this->tokens[$i + 1]['id'] === '{')) {
                $env = [];

                return;
            }

            if ($id === '[') {
                $prevId = $i > $start ? $this->tokens[$i - 1]['id'] : null;
                $isSubscript = $prevId === T_VARIABLE
                    || $prevId === ']'
                    || $prevId === ')'
                    || $prevId === '}'
                    || $prevId === T_STRING
                    || $prevId === T_CONSTANT_ENCAPSED_STRING;
                if (!$isSubscript) {
                    $close = $this->findMatching($i, $end, '[', ']');
                    if ($close !== null && $close + 1 < $end && $this->tokens[$close + 1]['id'] === '=') {
                        $rhsEnd = $this->findArrowExprEnd($close + 2, $end);
                        $rhsEnv = $env;
                        $this->evalExprType($close + 2, $rhsEnd, $rhsEnv);
                        $env = self::joinEnv($env, $rhsEnv) ?? [];
                        for ($k = $i + 1; $k < $close; ++$k) {
                            if ($this->tokens[$k]['id'] === T_VARIABLE) {
                                unset($env[$this->tokens[$k]['text']]);
                            }
                        }
                        $i = $rhsEnd - 1;
                        continue;
                    }
                }
            }

            if ($id === T_LIST && $i + 1 < $end && $this->tokens[$i + 1]['id'] === '(') {
                $close = $this->findMatching($i + 1, $end, '(', ')');
                if ($close !== null && $close + 1 < $end && $this->tokens[$close + 1]['id'] === '=') {
                    $rhsEnd = $this->findArrowExprEnd($close + 2, $end);
                    $rhsEnv = $env;
                    $this->evalExprType($close + 2, $rhsEnd, $rhsEnv);
                    $env = self::joinEnv($env, $rhsEnv) ?? [];
                    for ($k = $i + 2; $k < $close; ++$k) {
                        if ($this->tokens[$k]['id'] === T_VARIABLE) {
                            unset($env[$this->tokens[$k]['text']]);
                        }
                    }
                    $i = $rhsEnd - 1;
                    continue;
                }
            }

            if ($id === T_VARIABLE && $i + 1 < $end) {
                $nextId = $this->tokens[$i + 1]['id'];
                if ($nextId === '=' || $nextId === T_COALESCE_EQUAL) {
                    $varName = $this->tokens[$i]['text'];
                    $rhsEnd = $this->findArrowExprEnd($i + 2, $end);
                    $rhsEnv = $env;
                    $rhsType = $this->evalExprType($i + 2, $rhsEnd, $rhsEnv);
                    $env = self::joinEnv($env, $rhsEnv) ?? [];
                    if ($nextId === '=' && $rhsType !== null && !isset($this->aliasedVars[$varName]) && ($env[$varName] ?? null) === $rhsType) {
                        $env[$varName] = $rhsType;
                    } else {
                        unset($env[$varName]);
                    }
                    $i = $rhsEnd - 1;
                    continue;
                }
            }
        }
    }

    private function isTreeBuilderClassName(string $rawName, bool $isFullyQualified): bool
    {
        $lower = strtolower(ltrim($rawName, '\\'));
        if ($isFullyQualified) {
            return $lower === self::TREE_BUILDER_FQN;
        }

        if ($lower === self::TREE_BUILDER_FQN) {
            return true;
        }

        $segments = explode('\\', $lower);
        $first = $segments[0];
        if (isset($this->imports[$first])) {
            $rest = count($segments) > 1 ? '\\' . implode('\\', array_slice($segments, 1)) : '';

            return $this->imports[$first] . $rest === self::TREE_BUILDER_FQN;
        }

        if ($this->currentNamespace !== '') {
            return $this->currentNamespace . '\\' . $lower === self::TREE_BUILDER_FQN;
        }

        return false;
    }

    private function extractLiteralRootType(int $start, int $end): ?string
    {
        [$start, $end] = $this->stripOuterParens($start, $end);
        if ($end - $start !== 1 || $this->tokens[$start]['id'] !== T_CONSTANT_ENCAPSED_STRING) {
            return null;
        }

        $raw = $this->tokens[$start]['text'];
        if (strlen($raw) < 2) {
            return null;
        }

        $value = strtolower(substr($raw, 1, -1));

        return isset(self::KNOWN_ROOT_TYPES[$value]) ? $value : null;
    }

    private function recordTargetRootType(?string $rootType): void
    {
        if (!$this->targetMatched) {
            $this->targetMatched = true;
            $this->targetRootType = $rootType;

            return;
        }

        $this->targetRootType = self::joinType($this->targetRootType, $rootType);
    }

    /**
     * @param ?array<string, string> $env
     */
    private function recordTryState(?array $env, bool $isException = true): void
    {
        if ($env === null || $this->tryStack === []) {
            return;
        }

        $count = count($this->tryStack);
        for ($k = 0; $k < $count; ++$k) {
            $this->tryStack[$k]['finallyEntry'] = self::joinEnv($this->tryStack[$k]['finallyEntry'], $env);
            if ($isException && $this->tryStack[$k]['inTryBody']) {
                $this->tryStack[$k]['catchEntry'] = self::joinEnv($this->tryStack[$k]['catchEntry'], $env);
            }
        }
    }

    private function findArrowDoubleArrow(int $fnIdx, int $limit): ?int
    {
        $openParen = $this->findNext($fnIdx + 1, $limit, '(');
        if ($openParen === null) {
            return null;
        }
        $closeParen = $this->findMatching($openParen, $limit, '(', ')');
        if ($closeParen === null) {
            return null;
        }

        return $this->findNext($closeParen + 1, $limit, T_DOUBLE_ARROW);
    }

    /**
     * @return array{0: ?array{0: int, 1: int}, 1: int}|null
     */
    private function findAnonymousClassParts(int $classIdx, int $limit): ?array
    {
        if ($classIdx > 0 && $this->tokens[$classIdx - 1]['id'] === T_DOUBLE_COLON) {
            return null;
        }

        $cursor = $classIdx + 1;
        $ctorRange = null;
        if ($cursor < $limit && $this->tokens[$cursor]['id'] === '(') {
            $closeParen = $this->findMatching($cursor, $limit, '(', ')');
            if ($closeParen === null) {
                return null;
            }
            $ctorRange = [$cursor + 1, $closeParen];
            $cursor = $closeParen + 1;
        }

        while ($cursor < $limit) {
            $tid = $this->tokens[$cursor]['id'];
            if ($tid === '{') {
                $closeBrace = $this->findMatching($cursor, $limit, '{', '}');
                if ($closeBrace === null) {
                    return null;
                }

                return [$ctorRange, $closeBrace];
            }
            if ($tid === ';' || $tid === ')' || $tid === ']') {
                return null;
            }
            ++$cursor;
        }

        return null;
    }

    /**
     * @param array<string, string> $env
     */
    private function recordJump(bool $isBreak, int $levels, array $env): void
    {
        $stackSize = count($this->breakStack);
        if ($stackSize === 0) {
            return;
        }

        $targetIdx = $stackSize - $levels;
        if ($targetIdx < 0) {
            $targetIdx = 0;
        }

        if ($isBreak) {
            $this->breakStack[$targetIdx]['breakEnv'] = self::joinEnv($this->breakStack[$targetIdx]['breakEnv'], $env);
        } else {
            $this->breakStack[$targetIdx]['continueEnv'] = self::joinEnv($this->breakStack[$targetIdx]['continueEnv'], $env);
        }
    }

    /**
     * @param ?array<string, string> $a
     * @param ?array<string, string> $b
     * @return ?array<string, string>
     */
    private static function joinEnv(?array $a, ?array $b): ?array
    {
        if ($a === null) {
            return $b;
        }
        if ($b === null) {
            return $a;
        }

        return array_intersect_assoc($a, $b);
    }

    private static function joinType(?string $a, ?string $b): ?string
    {
        return ($a !== null && $a === $b) ? $a : null;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function findLoopBodyRange(int $start, int $limit, ?int $endToken): array
    {
        if ($start >= $limit) {
            return [$limit, $limit, $limit];
        }

        if ($endToken !== null && $this->tokens[$start]['id'] === ':') {
            $openToken = match ($endToken) {
                T_ENDWHILE => T_WHILE,
                T_ENDFOR => T_FOR,
                T_ENDFOREACH => T_FOREACH,
                default => T_WHILE,
            };
            $endIdx = $this->findMatchingColonEnd($start + 1, $limit, $openToken, $endToken);
            $after = $endIdx < $limit ? $endIdx + 1 : $limit;
            if ($after < $limit && $this->tokens[$after]['id'] === ';') {
                ++$after;
            }

            return [$start + 1, $endIdx, $after];
        }

        if ($this->tokens[$start]['id'] === '{') {
            $close = $this->findMatching($start, $limit, '{', '}');
            if ($close === null) {
                return [$start + 1, $limit, $limit];
            }

            return [$start + 1, $close, $close + 1];
        }

        $stmtEnd = $this->findSingleStatementEnd($start, $limit);

        return [$start, $stmtEnd, $stmtEnd];
    }

    private function findSingleStatementEnd(int $start, int $limit): int
    {
        if ($start >= $limit) {
            return $limit;
        }

        $id = $this->tokens[$start]['id'];
        if ($id === ';') {
            return $start + 1;
        }
        if ($id === '{') {
            $close = $this->findMatching($start, $limit, '{', '}');

            return $close !== null ? $close + 1 : $limit;
        }
        if ($id === T_IF || $id === T_WHILE || $id === T_FOR || $id === T_FOREACH) {
            $open = $this->findNext($start + 1, $limit, '(');
            $close = $open !== null ? $this->findMatching($open, $limit, '(', ')') : null;
            if ($close === null) {
                return $limit;
            }
            $endTok = match ($id) {
                T_IF => T_ENDIF,
                T_WHILE => T_ENDWHILE,
                T_FOR => T_ENDFOR,
                T_FOREACH => T_ENDFOREACH,
            };
            [, , $after] = $this->findLoopBodyRange($close + 1, $limit, $endTok);
            if ($id === T_IF && $close + 1 < $limit && $this->tokens[$close + 1]['id'] !== ':') {
                while ($after < $limit && ($this->tokens[$after]['id'] === T_ELSEIF || $this->tokens[$after]['id'] === T_ELSE)) {
                    if ($this->tokens[$after]['id'] === T_ELSEIF) {
                        $eo = $this->findNext($after + 1, $limit, '(');
                        $ec = $eo !== null ? $this->findMatching($eo, $limit, '(', ')') : null;
                        if ($ec === null) {
                            return $limit;
                        }
                        $after = $this->findSingleStatementEnd($ec + 1, $limit);
                    } else {
                        $after = $this->findSingleStatementEnd($after + 1, $limit);
                        break;
                    }
                }
            }

            return $after;
        }
        if ($id === T_DO) {
            $afterBody = $this->findSingleStatementEnd($start + 1, $limit);
            if ($afterBody < $limit && $this->tokens[$afterBody]['id'] === T_WHILE) {
                $open = $this->findNext($afterBody + 1, $limit, '(');
                $close = $open !== null ? $this->findMatching($open, $limit, '(', ')') : null;
                if ($close !== null) {
                    $after = $close + 1;

                    return ($after < $limit && $this->tokens[$after]['id'] === ';') ? $after + 1 : $after;
                }
            }

            return $afterBody;
        }
        if ($id === T_SWITCH) {
            $open = $this->findNext($start + 1, $limit, '(');
            $close = $open !== null ? $this->findMatching($open, $limit, '(', ')') : null;
            if ($close === null || $close + 1 >= $limit) {
                return $limit;
            }
            if ($this->tokens[$close + 1]['id'] === ':') {
                $endIdx = $this->findMatchingColonEnd($close + 2, $limit, T_SWITCH, T_ENDSWITCH);
                $after = $endIdx < $limit ? $endIdx + 1 : $limit;

                return ($after < $limit && $this->tokens[$after]['id'] === ';') ? $after + 1 : $after;
            }
            $cb = $this->findMatching($close + 1, $limit, '{', '}');

            return $cb !== null ? $cb + 1 : $limit;
        }
        if ($id === T_TRY) {
            $to = $this->findNext($start + 1, $limit, '{');
            $tc = $to !== null ? $this->findMatching($to, $limit, '{', '}') : null;
            if ($tc === null) {
                return $limit;
            }
            $cursor = $tc + 1;
            while ($cursor < $limit && $this->tokens[$cursor]['id'] === T_CATCH) {
                $co = $this->findNext($cursor + 1, $limit, '{');
                $cc = $co !== null ? $this->findMatching($co, $limit, '{', '}') : null;
                if ($cc === null) {
                    return $limit;
                }
                $cursor = $cc + 1;
            }
            if ($cursor < $limit && $this->tokens[$cursor]['id'] === T_FINALLY) {
                $fo = $this->findNext($cursor + 1, $limit, '{');
                $fc = $fo !== null ? $this->findMatching($fo, $limit, '{', '}') : null;

                return $fc !== null ? $fc + 1 : $limit;
            }

            return $cursor;
        }

        $semi = $this->findStatementEnd($start, $limit);

        return min($semi + 1, $limit);
    }

    /**
     * @param array<string, string> $env
     */
    private function evalCommaSeparated(int $start, int $end, array &$env): void
    {
        foreach ($this->splitTopLevel($start, $end, ',') as [$pStart, $pEnd]) {
            if ($pStart < $pEnd) {
                $this->evalExprType($pStart, $pEnd, $env);
            }
        }
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function stripOuterParens(int $start, int $end): array
    {
        while ($start < $end && $this->tokens[$start]['id'] === '(' && $this->tokens[$end - 1]['id'] === ')') {
            $matched = $this->findMatching($start, $end, '(', ')');
            if ($matched !== $end - 1) {
                break;
            }
            ++$start;
            --$end;
        }

        return [$start, $end];
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private function splitTopLevel(int $start, int $end, int|string $delimiter): array
    {
        $parts = [];
        $partStart = $start;
        $depth = 0;

        for ($i = $start; $i < $end; ++$i) {
            $id = $this->tokens[$i]['id'];
            if ($id === '(' || $id === '[' || $id === '{') {
                ++$depth;
            } elseif ($id === ')' || $id === ']' || $id === '}') {
                --$depth;
            } elseif ($id === $delimiter && $depth === 0) {
                $parts[] = [$partStart, $i];
                $partStart = $i + 1;
            }
        }

        if ($partStart <= $end) {
            $parts[] = [$partStart, $end];
        }

        return $parts;
    }

    private function findStatementEnd(int $start, int $limit): int
    {
        $depth = 0;
        for ($i = $start; $i < $limit; ++$i) {
            $id = $this->tokens[$i]['id'];
            if ($id === '(' || $id === '[' || $id === '{') {
                ++$depth;
            } elseif ($id === ')' || $id === ']' || $id === '}') {
                if ($depth === 0) {
                    return $i;
                }
                --$depth;
            } elseif ($id === ';' && $depth === 0) {
                return $i;
            }
        }

        return $limit;
    }

    private function findArrowExprEnd(int $start, int $limit): int
    {
        $depth = 0;
        for ($i = $start; $i < $limit; ++$i) {
            $id = $this->tokens[$i]['id'];
            if ($id === '(' || $id === '[' || $id === '{') {
                ++$depth;
            } elseif ($id === ')' || $id === ']' || $id === '}') {
                if ($depth === 0) {
                    return $i;
                }
                --$depth;
            } elseif (($id === ';' || $id === ',') && $depth === 0) {
                return $i;
            }
        }

        return $limit;
    }

    private function findTopLevelToken(int $start, int $end, int|string $target): ?int
    {
        $depth = 0;
        for ($i = $start; $i < $end; ++$i) {
            $id = $this->tokens[$i]['id'];
            if ($id === '(' || $id === '[' || $id === '{') {
                ++$depth;
            } elseif ($id === ')' || $id === ']' || $id === '}') {
                --$depth;
            } elseif ($id === $target && $depth === 0) {
                return $i;
            }
        }

        return null;
    }

    private function findTernaryColon(int $start, int $end): ?int
    {
        $depth = 0;
        $ternaryDepth = 0;
        for ($i = $start; $i < $end; ++$i) {
            $id = $this->tokens[$i]['id'];
            if ($id === '(' || $id === '[' || $id === '{') {
                ++$depth;
            } elseif ($id === ')' || $id === ']' || $id === '}') {
                --$depth;
            } elseif ($depth === 0) {
                if ($id === '?') {
                    ++$ternaryDepth;
                } elseif ($id === ':') {
                    if ($ternaryDepth === 0) {
                        return $i;
                    }
                    --$ternaryDepth;
                }
            }
        }

        return null;
    }

    private function findCaseColon(int $start, int $limit): ?int
    {
        $depth = 0;
        for ($i = $start; $i < $limit; ++$i) {
            $id = $this->tokens[$i]['id'];
            if ($id === '(' || $id === '[' || $id === '{') {
                ++$depth;
            } elseif ($id === ')' || $id === ']' || $id === '}') {
                --$depth;
            } elseif (($id === ':' || $id === ';') && $depth === 0) {
                return $i;
            }
        }

        return null;
    }

    private function findMatchingColonEnd(int $start, int $limit, int $openToken, int $endToken): int
    {
        $depth = 1;
        for ($i = $start; $i < $limit; ++$i) {
            $id = $this->tokens[$i]['id'];
            if ($id === $openToken) {
                $openParen = $this->findNext($i + 1, $limit, '(');
                $closeParen = $openParen !== null ? $this->findMatching($openParen, $limit, '(', ')') : null;
                if ($closeParen !== null && $closeParen + 1 < $limit && $this->tokens[$closeParen + 1]['id'] === ':') {
                    ++$depth;
                }
            } elseif ($id === $endToken) {
                --$depth;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return $limit;
    }

    private function findNext(int $start, int $limit, int|string $target): ?int
    {
        for ($i = $start; $i < $limit; ++$i) {
            if ($this->tokens[$i]['id'] === $target) {
                return $i;
            }
        }

        return null;
    }

    private function findMatching(int $openIdx, int $limit, string $open, string $close): ?int
    {
        $depth = 0;
        for ($i = $openIdx; $i < $limit; ++$i) {
            $id = $this->tokens[$i]['id'];
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
}

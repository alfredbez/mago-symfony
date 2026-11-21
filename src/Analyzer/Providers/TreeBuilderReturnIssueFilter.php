<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\IssueFilterContext;
use Mago\Sdk\Analyzer\IssueFilterDecision;
use Mago\Sdk\Analyzer\IssueFilterHook;
use Mago\Sdk\Reporting\AnnotationKind;

/**
 * Suppresses Symfony 7's TreeBuilder<string> vs TreeBuilder<'array'> false positive
 * when Mago widens the default constructor parameter `$type = 'array'` to `string`.
 */
final class TreeBuilderReturnIssueFilter implements IssueFilterHook
{
    private const PATTERN = '/expected `Symfony\\\\Component\\\\Config\\\\Definition\\\\Builder\\\\TreeBuilder<string\(\'([a-z]+)\'\)>`, but found `Symfony\\\\Component\\\\Config\\\\Definition\\\\Builder\\\\TreeBuilder<string>`/';

    public function getCodes(): array
    {
        return ['invalid-return-statement'];
    }

    public function filterIssue(IssueFilterContext $context): IssueFilterDecision
    {
        if (!str_contains($context->issue->message, 'TreeBuilder<string>')) {
            return IssueFilterDecision::Keep;
        }

        if (preg_match(self::PATTERN, $context->issue->message, $match) !== 1) {
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

        $returnedText = trim(substr($context->contents, $primarySpan->start, $primarySpan->end - $primarySpan->start));
        $returnedText = preg_replace('/^return\s+/', '', $returnedText) ?? $returnedText;
        $returnedText = rtrim($returnedText, "; \t\n\r");

        $actualRootType = $this->extractConstructedRootType($returnedText);
        if ($actualRootType === null && preg_match('/^\$[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*$/', $returnedText) === 1) {
            $actualRootType = $this->findAssignedRootType($context->contents, $primarySpan->start, $returnedText);
        }

        return $actualRootType === $expectedRootType
            ? IssueFilterDecision::Remove
            : IssueFilterDecision::Keep;
    }

    private function extractConstructedRootType(string $expression): ?string
    {
        if (preg_match('/^new\s+\\\\?(?:Symfony\\\\Component\\\\Config\\\\Definition\\\\Builder\\\\)?TreeBuilder\s*\((.*)\)$/s', $expression, $match) !== 1) {
            return null;
        }

        $args = $this->splitTopLevelArgs($match[1]);
        if (count($args) < 2) {
            return 'array';
        }

        foreach ($args as $index => $arg) {
            if (preg_match('/^type\s*:\s*([\'"])([A-Za-z]+)\1$/', $arg, $named) === 1) {
                return strtolower($named[2]);
            }
            if ($index === 1 && preg_match('/^([\'"])([A-Za-z]+)\1$/', $arg, $positional) === 1) {
                return strtolower($positional[2]);
            }
        }

        return null;
    }

    private function findAssignedRootType(string $contents, int $beforeOffset, string $variable): ?string
    {
        $prefix = substr($contents, 0, $beforeOffset);
        if (preg_match_all('/\bfunction\b[^{]*\{/', $prefix, $fnMatches, PREG_OFFSET_CAPTURE) >= 1) {
            $lastFn = $fnMatches[0][array_key_last($fnMatches[0])];
            $prefix = substr($prefix, $lastFn[1]);
        }

        $assignPattern = '/' . preg_quote($variable, '/') . '\s*=\s*([^;]+);/s';
        if (preg_match_all($assignPattern, $prefix, $matches) < 1) {
            return null;
        }

        $lastRhs = trim($matches[1][array_key_last($matches[1])]);

        return $this->extractConstructedRootType($lastRhs);
    }

    /** @return list<string> */
    private function splitTopLevelArgs(string $argList): array
    {
        $args = [];
        $current = '';
        $depth = 0;
        $quote = null;
        $length = strlen($argList);

        for ($i = 0; $i < $length; ++$i) {
            $ch = $argList[$i];
            if ($quote !== null) {
                $current .= $ch;
                if ($ch === '\\' && $i + 1 < $length) {
                    $current .= $argList[++$i];
                } elseif ($ch === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($ch === "'" || $ch === '"') {
                $quote = $ch;
                $current .= $ch;
            } elseif ($ch === '(' || $ch === '[' || $ch === '{') {
                $depth++;
                $current .= $ch;
            } elseif ($ch === ')' || $ch === ']' || $ch === '}') {
                $depth--;
                $current .= $ch;
            } elseif ($ch === ',' && $depth === 0) {
                $args[] = trim($current);
                $current = '';
            } else {
                $current .= $ch;
            }
        }

        if (trim($current) !== '') {
            $args[] = trim($current);
        }

        return $args;
    }
}

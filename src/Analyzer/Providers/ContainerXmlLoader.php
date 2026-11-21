<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use DOMElement;
use DOMText;
use XMLReader;

/**
 * Parses compiled Symfony dependency injection container XML dumps.
 *
 * @phpstan-type ServiceFactory array{kind: 'method', class: string, method: string}|array{kind: 'function', function: string}
 * @phpstan-type ParameterDescriptor array{kind: string, value?: mixed, items?: array<int|string, mixed>, name?: string, type?: string}
 */
final class ContainerXmlLoader
{
    /**
     * @return array{
     *     classes: array<string, string>,
     *     factories: array<string, ServiceFactory>,
     *     parameters: array<string, ParameterDescriptor>,
     *     visibility: array<string, bool>,
     * }
     */
    public static function loadFile(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return ['classes' => [], 'factories' => [], 'parameters' => [], 'visibility' => []];
        }

        $reader = new XMLReader();
        $previous = libxml_use_internal_errors(true);
        try {
            if (!@$reader->open($path, null, LIBXML_NONET | LIBXML_COMPACT)) {
                return ['classes' => [], 'factories' => [], 'parameters' => [], 'visibility' => []];
            }

            return self::parseReader($reader);
        } finally {
            @$reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * @return array{
     *     classes: array<string, string>,
     *     factories: array<string, ServiceFactory>,
     *     parameters: array<string, ParameterDescriptor>,
     *     visibility: array<string, bool>,
     * }
     */
    public static function loadString(string $xml): array
    {
        if (trim($xml) === '') {
            return ['classes' => [], 'factories' => [], 'parameters' => [], 'visibility' => []];
        }

        $reader = new XMLReader();
        $previous = libxml_use_internal_errors(true);
        try {
            if (!@$reader->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT)) {
                return ['classes' => [], 'factories' => [], 'parameters' => [], 'visibility' => []];
            }

            return self::parseReader($reader);
        } finally {
            @$reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * @return array{
     *     classes: array<string, string>,
     *     factories: array<string, ServiceFactory>,
     *     parameters: array<string, ParameterDescriptor>,
     *     visibility: array<string, bool>,
     * }
     */
    private static function parseReader(XMLReader $reader): array
    {
        /** @var array<string, string> $classes */
        $classes = [];
        /** @var array<string, string> $aliases */
        $aliases = [];
        /** @var array<string, bool> $visibility */
        $visibility = [];
        /** @var array<string, array{kind: string, class?: string, service?: string, method?: string, function?: string}> $rawFactories */
        $rawFactories = [];
        /** @var array<string, ParameterDescriptor> $parameters */
        $parameters = [];

        $section = null;
        $currentServiceId = null;
        $currentServiceDepth = 0;

        while (@$reader->read()) {
            if ($reader->nodeType === XMLReader::END_ELEMENT) {
                $local = $reader->localName;
                if ($reader->depth === 1 && ($local === 'services' || $local === 'parameters')) {
                    $section = null;
                } elseif ($currentServiceId !== null && $local === 'service' && $reader->depth === $currentServiceDepth) {
                    $currentServiceId = null;
                }
                continue;
            }

            if ($reader->nodeType !== XMLReader::ELEMENT) {
                continue;
            }

            $local = $reader->localName;
            $depth = $reader->depth;

            if ($depth === 1) {
                if ($local === 'services' || $local === 'parameters') {
                    $section = $local;
                }
                continue;
            }

            if ($section === 'parameters' && $depth === 2 && $local === 'parameter') {
                $key = $reader->getAttribute('key');
                if ($key !== null && $key !== '') {
                    $node = @$reader->expand();
                    if ($node instanceof DOMElement) {
                        $parameters[$key] = self::parseParameterElement($node);
                    }
                }
                continue;
            }

            if ($section !== 'services') {
                continue;
            }

            if ($depth === 2 && $local === 'service') {
                $id = $reader->getAttribute('id');
                if ($id === null || $id === '') {
                    continue;
                }

                $visibility[$id] = strtolower((string) $reader->getAttribute('public')) === 'true';

                $alias = $reader->getAttribute('alias');
                if ($alias !== null && $alias !== '') {
                    $aliases[$id] = $alias;
                    continue;
                }

                $class = $reader->getAttribute('class');
                if ($class !== null && $class !== '') {
                    $class = ltrim($class, '\\');
                    $classes[$id] = $class;
                }

                $constructor = $reader->getAttribute('constructor');
                if ($constructor !== null && $constructor !== '' && $class !== null && $class !== '') {
                    $rawFactories[$id] = [
                        'kind' => 'method',
                        'class' => $class,
                        'method' => $constructor,
                    ];
                }

                if (!$reader->isEmptyElement) {
                    $currentServiceId = $id;
                    $currentServiceDepth = $depth;
                }
                continue;
            }

            if ($currentServiceId !== null && $depth === $currentServiceDepth + 1 && $local === 'factory') {
                $factory = self::parseFactoryElement($reader);
                if ($factory !== null) {
                    $rawFactories[$currentServiceId] = $factory;
                }
            }
        }

        $resolvedAliases = self::resolveAliases($aliases);
        foreach ($resolvedAliases as $aliasId => $targetId) {
            if (isset($classes[$targetId])) {
                $classes[$aliasId] = $classes[$targetId];
            }
        }

        /** @var array<string, ServiceFactory> $factories */
        $factories = [];
        foreach ($rawFactories as $serviceId => $factory) {
            if ($factory['kind'] === 'method' && isset($factory['class'], $factory['method'])) {
                $factories[$serviceId] = [
                    'kind' => 'method',
                    'class' => $factory['class'],
                    'method' => $factory['method'],
                ];
            } elseif ($factory['kind'] === 'function' && isset($factory['function'])) {
                $factories[$serviceId] = [
                    'kind' => 'function',
                    'function' => $factory['function'],
                ];
            } elseif ($factory['kind'] === 'service' && isset($factory['service'], $factory['method'])) {
                $factoryClass = $classes[$factory['service']] ?? null;
                if ($factoryClass !== null) {
                    $factories[$serviceId] = [
                        'kind' => 'method',
                        'class' => $factoryClass,
                        'method' => $factory['method'],
                    ];
                }
            }
        }

        foreach ($resolvedAliases as $aliasId => $targetId) {
            if (isset($factories[$targetId])) {
                $factories[$aliasId] = $factories[$targetId];
            }
        }

        return [
            'classes' => $classes,
            'factories' => $factories,
            'parameters' => $parameters,
            'visibility' => $visibility,
        ];
    }

    /**
     * @return null|array{kind: string, class?: string, service?: string, method?: string, function?: string}
     */
    private static function parseFactoryElement(XMLReader $reader): ?array
    {
        $function = $reader->getAttribute('function');
        if ($function !== null && $function !== '') {
            return [
                'kind' => 'function',
                'function' => ltrim($function, '\\'),
            ];
        }

        $method = $reader->getAttribute('method');
        if ($method === null || $method === '') {
            return null;
        }

        $class = $reader->getAttribute('class');
        if ($class !== null && $class !== '') {
            return [
                'kind' => 'method',
                'class' => ltrim($class, '\\'),
                'method' => $method,
            ];
        }

        $service = $reader->getAttribute('service');
        if ($service !== null && $service !== '') {
            return [
                'kind' => 'service',
                'service' => $service,
                'method' => $method,
            ];
        }

        if (!$reader->isEmptyElement) {
            $node = @$reader->expand();
            if ($node instanceof DOMElement) {
                foreach ($node->childNodes as $child) {
                    if ($child instanceof DOMElement && $child->localName === 'service') {
                        $inlineClass = $child->getAttribute('class');
                        if ($inlineClass !== '') {
                            return [
                                'kind' => 'method',
                                'class' => ltrim($inlineClass, '\\'),
                                'method' => $method,
                            ];
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $aliases
     * @return array<string, string>
     */
    private static function resolveAliases(array $aliases): array
    {
        $resolved = [];
        foreach ($aliases as $aliasId => $targetId) {
            $current = $targetId;
            $visited = [$aliasId => true];
            while (isset($aliases[$current])) {
                if (isset($visited[$current])) {
                    $current = null;
                    break;
                }
                $visited[$current] = true;
                $current = $aliases[$current];
            }

            if ($current !== null) {
                $resolved[$aliasId] = $current;
            }
        }

        return $resolved;
    }

    /**
     * @return ParameterDescriptor
     */
    private static function parseParameterElement(DOMElement $element): array
    {
        $type = $element->getAttribute('type');
        $trim = $element->hasAttribute('trim') && strtolower($element->getAttribute('trim')) === 'true';

        if ($type === 'collection') {
            return self::parseCollectionElement($element);
        }

        $raw = self::directText($element);
        if ($trim) {
            $raw = trim($raw);
        }

        return match ($type) {
            'string' => self::parseStringParameter($raw),
            'binary' => ['kind' => 'type', 'type' => 'string'],
            'constant' => ['kind' => 'constant', 'name' => ltrim(trim($raw), '\\')],
            'boolean', 'bool' => ['kind' => 'bool', 'value' => in_array(strtolower(trim($raw)), ['true', '1', 'yes', 'on'], true)],
            'int', 'integer' => ['kind' => 'int', 'value' => (int) trim($raw)],
            'float' => ['kind' => 'float', 'value' => (float) trim($raw)],
            'null' => ['kind' => 'null'],
            default => self::parseUntypedParameter($raw),
        };
    }

    /**
     * @return ParameterDescriptor
     */
    private static function parseCollectionElement(DOMElement $element): array
    {
        /** @var array<int|string, ParameterDescriptor> $items */
        $items = [];
        $isList = true;
        $nextIndex = 0;

        foreach ($element->childNodes as $child) {
            if (!$child instanceof DOMElement || $child->localName !== 'parameter') {
                continue;
            }

            $descriptor = self::parseParameterElement($child);
            if ($child->hasAttribute('key')) {
                $rawKey = $child->getAttribute('key');
                if (ctype_digit($rawKey) && ($rawKey === '0' || !str_starts_with($rawKey, '0'))) {
                    $intKey = (int) $rawKey;
                    if ($intKey !== $nextIndex) {
                        $isList = false;
                    }
                    $items[$intKey] = $descriptor;
                    $nextIndex = max($nextIndex, $intKey + 1);
                } else {
                    $isList = false;
                    $items[$rawKey] = $descriptor;
                }
            } else {
                $items[$nextIndex] = $descriptor;
                $nextIndex++;
            }
        }

        if ($isList) {
            return ['kind' => 'list', 'items' => array_values($items)];
        }

        return ['kind' => 'map', 'items' => $items];
    }

    /**
     * @return ParameterDescriptor
     */
    private static function parseStringParameter(string $raw): array
    {
        $env = self::parseEnvPlaceholder($raw);
        if ($env !== null) {
            return $env;
        }

        return ['kind' => 'string', 'value' => str_replace('%%', '%', $raw)];
    }

    /**
     * @return ParameterDescriptor
     */
    private static function parseUntypedParameter(string $raw): array
    {
        $env = self::parseEnvPlaceholder($raw);
        if ($env !== null) {
            return $env;
        }

        $lower = strtolower($raw);
        if ($lower === 'null') {
            return ['kind' => 'null'];
        }
        if ($lower === 'true') {
            return ['kind' => 'bool', 'value' => true];
        }
        if ($lower === 'false') {
            return ['kind' => 'bool', 'value' => false];
        }

        if (ctype_digit($raw) || (isset($raw[1]) && $raw[0] === '-' && ctype_digit(substr($raw, 1)))) {
            if (self::isOctal($raw)) {
                return ['kind' => 'int', 'value' => intval($raw, 8)];
            }
            $cast = (int) $raw;

            return $raw === (string) $cast
                ? ['kind' => 'int', 'value' => $cast]
                : ['kind' => 'string', 'value' => $raw];
        }

        if (isset($raw[1]) && $raw[0] === '0' && ($raw[1] === 'b' || $raw[1] === 'B') && preg_match('/^0[bB][01]+$/', $raw) === 1) {
            $value = bindec($raw);

            return is_int($value)
                ? ['kind' => 'int', 'value' => $value]
                : ['kind' => 'float', 'value' => (float) $value];
        }

        if (preg_match('/^0[xX][0-9a-fA-F]+$/', $raw) === 1) {
            $value = hexdec($raw);

            return is_int($value)
                ? ['kind' => 'int', 'value' => $value]
                : ['kind' => 'float', 'value' => (float) $value];
        }

        if (is_numeric($raw)) {
            return ['kind' => 'float', 'value' => (float) $raw];
        }

        return ['kind' => 'string', 'value' => str_replace('%%', '%', $raw)];
    }

    /**
     * @return null|ParameterDescriptor
     */
    private static function parseEnvPlaceholder(string $raw): ?array
    {
        if (!str_contains($raw, '%env(')) {
            return null;
        }

        if (preg_match('/^%env\(([^)]+)\)%$/', $raw, $match) === 1) {
            $spec = $match[1];
            $colonPos = strpos($spec, ':');
            if ($colonPos === false) {
                return ['kind' => 'type', 'type' => 'string'];
            }

            $processor = strtolower(substr($spec, 0, $colonPos));

            return match ($processor) {
                'int' => ['kind' => 'type', 'type' => 'int'],
                'float' => ['kind' => 'type', 'type' => 'float'],
                'bool' => ['kind' => 'type', 'type' => 'bool'],
                'string', 'base64', 'resolve', 'trim', 'file' => ['kind' => 'type', 'type' => 'string'],
                'json', 'csv', 'url', 'query_string' => ['kind' => 'type', 'type' => 'array'],
                default => ['kind' => 'type', 'type' => 'mixed'],
            };
        }

        return ['kind' => 'type', 'type' => 'non-empty-string'];
    }

    private static function directText(DOMElement $element): string
    {
        $text = '';
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMText) {
                $text .= $child->wholeText;
                break;
            }
        }

        return $text !== '' ? $text : $element->textContent;
    }

    private static function isOctal(string $value): bool
    {
        if ($value === '0') {
            return false;
        }

        if (str_starts_with($value, '-0') && strlen($value) > 2) {
            return preg_match('/^-0[0-7]+$/', $value) === 1;
        }

        return str_starts_with($value, '0') && strlen($value) > 1 && preg_match('/^0[0-7]+$/', $value) === 1;
    }
}

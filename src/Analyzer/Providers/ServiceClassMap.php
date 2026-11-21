<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

/**
 * Loads container service classes, factories, and parameters from either a
 * compiled Symfony container XML dump or a JSON map.
 *
 * Resolution order:
 * 1. MAGO_SYMFONY_CONTAINER_XML (compiled Symfony container XML)
 * 2. MAGO_SYMFONY_CONTAINER_MAP (XML if ending in .xml, otherwise JSON)
 * 3. .mago/symfony-container.json
 * 4. var/cache/dev/*Container.xml, then var/cache/test/*Container.xml
 *
 * @phpstan-import-type ServiceFactory from ContainerXmlLoader
 * @phpstan-import-type ParameterDescriptor from ContainerXmlLoader
 */
final class ServiceClassMap
{
    public const XML_ENVIRONMENT_VARIABLE = 'MAGO_SYMFONY_CONTAINER_XML';
    public const ENVIRONMENT_VARIABLE = 'MAGO_SYMFONY_CONTAINER_MAP';
    public const DEFAULT_PATH = '.mago/symfony-container.json';

    private const CACHE_XML_GLOBS = [
        'var/cache/dev/*Container.xml',
        'var/cache/test/*Container.xml',
    ];

    /** @var array<string, string> */
    private array $classes;

    /** @var array<string, ServiceFactory> */
    private array $factories;

    /** @var array<string, ParameterDescriptor> */
    private array $parameters;

    /**
     * @param array<string, string> $classes
     * @param array<string, ServiceFactory> $factories
     * @param array<string, ParameterDescriptor> $parameters
     */
    private function __construct(array $classes, array $factories = [], array $parameters = [])
    {
        $this->classes = $classes;
        $this->factories = $factories;
        $this->parameters = $parameters;
    }

    public static function fromEnvironment(): self
    {
        $xmlPath = getenv(self::XML_ENVIRONMENT_VARIABLE);
        if ($xmlPath !== false && $xmlPath !== '') {
            return self::fromXmlFile($xmlPath);
        }

        $mapPath = getenv(self::ENVIRONMENT_VARIABLE);
        if ($mapPath !== false && $mapPath !== '') {
            return self::fromFile($mapPath);
        }

        if (is_file(self::DEFAULT_PATH) && is_readable(self::DEFAULT_PATH)) {
            return self::fromJsonFile(self::DEFAULT_PATH);
        }

        foreach (self::CACHE_XML_GLOBS as $pattern) {
            $matches = glob($pattern);
            if (!is_array($matches) || $matches === []) {
                continue;
            }
            sort($matches);
            foreach ($matches as $candidate) {
                if (is_file($candidate) && is_readable($candidate)) {
                    return self::fromXmlFile($candidate);
                }
            }
        }

        return new self([]);
    }

    public static function fromFile(string $path): self
    {
        if (str_ends_with(strtolower($path), '.xml')) {
            return self::fromXmlFile($path);
        }

        return self::fromJsonFile($path);
    }

    public static function fromXmlFile(string $path): self
    {
        $loaded = ContainerXmlLoader::loadFile($path);

        return new self($loaded['classes'], $loaded['factories'], $loaded['parameters']);
    }

    public static function fromXmlString(string $xml): self
    {
        $loaded = ContainerXmlLoader::loadString($xml);

        return new self($loaded['classes'], $loaded['factories'], $loaded['parameters']);
    }

    public static function fromJsonFile(string $path): self
    {
        if (!is_file($path) || !is_readable($path)) {
            return new self([]);
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return new self([]);
        }

        $classes = [];
        $parameters = [];

        if (isset($decoded['services']) && is_array($decoded['services'])) {
            foreach ($decoded['services'] as $id => $class) {
                if (is_string($class) && $class !== '') {
                    $classes[(string) $id] = ltrim($class, '\\');
                }
            }
        }

        if (isset($decoded['parameters']) && is_array($decoded['parameters'])) {
            foreach ($decoded['parameters'] as $name => $value) {
                $parameters[(string) $name] = self::descriptorFromPhpValue($value);
            }
        }

        foreach ($decoded as $id => $class) {
            if (is_string($class) && $class !== '') {
                $classes[(string) $id] = ltrim($class, '\\');
            }
        }

        return new self($classes, [], $parameters);
    }

    public function classFor(string $id): ?string
    {
        return $this->classes[$id] ?? null;
    }

    /** @return null|ServiceFactory */
    public function factoryFor(string $id): ?array
    {
        return $this->factories[$id] ?? null;
    }

    /** @return null|ParameterDescriptor */
    public function parameterFor(string $name): ?array
    {
        return $this->parameters[$name] ?? null;
    }

    /** @return ParameterDescriptor */
    private static function descriptorFromPhpValue(mixed $value): array
    {
        if ($value === null) {
            return ['kind' => 'null'];
        }
        if (is_bool($value)) {
            return ['kind' => 'bool', 'value' => $value];
        }
        if (is_int($value)) {
            return ['kind' => 'int', 'value' => $value];
        }
        if (is_float($value)) {
            return ['kind' => 'float', 'value' => $value];
        }
        if (is_string($value)) {
            return ['kind' => 'string', 'value' => $value];
        }
        if (is_array($value)) {
            if (array_is_list($value)) {
                return [
                    'kind' => 'list',
                    'items' => array_map(self::descriptorFromPhpValue(...), $value),
                ];
            }

            $items = [];
            foreach ($value as $k => $v) {
                $items[$k] = self::descriptorFromPhpValue($v);
            }

            return ['kind' => 'map', 'items' => $items];
        }

        return ['kind' => 'type', 'type' => 'mixed'];
    }
}

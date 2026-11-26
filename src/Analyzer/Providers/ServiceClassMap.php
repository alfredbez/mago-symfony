<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use RuntimeException;

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
 *
 * @internal
 */
final class ServiceClassMap
{
    public const XML_ENVIRONMENT_VARIABLE = 'MAGO_SYMFONY_CONTAINER_XML';
    public const ENVIRONMENT_VARIABLE = 'MAGO_SYMFONY_CONTAINER_MAP';
    public const STRICT_UNKNOWN_SERVICES_VARIABLE = 'MAGO_SYMFONY_STRICT_CONTAINER_SERVICES';
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

    /** @var array<string, bool> */
    private array $visibility;

    private bool $fromXml;

    /**
     * @param array<string, string> $classes
     * @param array<string, ServiceFactory> $factories
     * @param array<string, ParameterDescriptor> $parameters
     * @param array<string, bool> $visibility
     */
    private function __construct(
        array $classes,
        array $factories = [],
        array $parameters = [],
        array $visibility = [],
        bool $fromXml = false,
    ) {
        $this->classes = $classes;
        $this->factories = $factories;
        $this->parameters = $parameters;
        $this->visibility = $visibility;
        $this->fromXml = $fromXml;
    }

    public static function fromEnvironment(): self
    {
        $xmlPath = getenv(self::XML_ENVIRONMENT_VARIABLE);
        if ($xmlPath !== false && $xmlPath !== '') {
            return self::fromExplicitXmlFile($xmlPath, self::XML_ENVIRONMENT_VARIABLE);
        }

        $mapPath = getenv(self::ENVIRONMENT_VARIABLE);
        if ($mapPath !== false && $mapPath !== '') {
            if (str_ends_with(strtolower($mapPath), '.xml')) {
                return self::fromExplicitXmlFile($mapPath, self::ENVIRONMENT_VARIABLE);
            }

            return self::fromExplicitJsonFile($mapPath, self::ENVIRONMENT_VARIABLE);
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

    private static function fromExplicitXmlFile(string $path, string $envVar): self
    {
        $loaded = ContainerXmlLoader::loadFileOrFail($path, $envVar);

        return new self(
            $loaded['classes'],
            $loaded['factories'],
            $loaded['parameters'],
            $loaded['visibility'],
            $loaded['visibility'] !== [],
        );
    }

    private static function fromExplicitJsonFile(string $path, string $envVar): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException(
                "mago-symfony: {$envVar} points to a missing or unreadable file: {$path}",
            );
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException(
                "mago-symfony: {$envVar} could not be read: {$path}",
            );
        }

        $decodedObject = json_decode($raw, false);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                "mago-symfony: {$envVar} contains malformed JSON (" . json_last_error_msg() . "): {$path}",
            );
        }

        if (!$decodedObject instanceof \stdClass) {
            throw new RuntimeException(
                "mago-symfony: {$envVar} must decode to a JSON object: {$path}",
            );
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException(
                "mago-symfony: {$envVar} must decode to a JSON object: {$path}",
            );
        }

        return self::fromDecodedJson($decoded);
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

        return new self(
            $loaded['classes'],
            $loaded['factories'],
            $loaded['parameters'],
            $loaded['visibility'],
            $loaded['visibility'] !== [],
        );
    }

    public static function fromXmlString(string $xml): self
    {
        $loaded = ContainerXmlLoader::loadString($xml);

        return new self(
            $loaded['classes'],
            $loaded['factories'],
            $loaded['parameters'],
            $loaded['visibility'],
            $loaded['visibility'] !== [],
        );
    }

    public static function strictUnknownServicesFromEnvironment(): bool
    {
        $value = getenv(self::STRICT_UNKNOWN_SERVICES_VARIABLE);
        if ($value === false) {
            return false;
        }

        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
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

        return self::fromDecodedJson($decoded);
    }

    /**
     * @param array<array-key, mixed> $decoded
     */
    private static function fromDecodedJson(array $decoded): self
    {
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

    public function hasFactories(): bool
    {
        return $this->factories !== [];
    }

    /** @return null|ServiceFactory */
    public function factoryFor(string $id): ?array
    {
        return $this->factories[$id] ?? null;
    }

    public function hasParameters(): bool
    {
        return $this->parameters !== [];
    }

    /** @return null|ParameterDescriptor */
    public function parameterFor(string $name): ?array
    {
        return $this->parameters[$name] ?? null;
    }

    public function isXmlLoaded(): bool
    {
        return $this->fromXml;
    }

    public function isKnownService(string $id): bool
    {
        return array_key_exists($id, $this->visibility);
    }

    public function isPrivateService(string $id): bool
    {
        return isset($this->visibility[$id]) && $this->visibility[$id] === false;
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

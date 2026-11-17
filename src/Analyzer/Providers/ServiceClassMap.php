<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

/**
 * Maps container service ids to class names for ContainerInterface::get().
 *
 * PHPStan reads the compiled container dump. Mago extensions have no config
 * section, so the map is a JSON file whose path comes from
 * MAGO_SYMFONY_CONTAINER_MAP, defaulting to .mago/symfony-container.json
 * relative to the worker's working directory (the project root).
 */
final class ServiceClassMap
{
    public const ENVIRONMENT_VARIABLE = 'MAGO_SYMFONY_CONTAINER_MAP';
    public const DEFAULT_PATH = '.mago/symfony-container.json';

    /** @var array<string, string> */
    private array $classes;

    /** @param array<string, string> $classes */
    private function __construct(array $classes)
    {
        $this->classes = $classes;
    }

    public static function fromEnvironment(): self
    {
        $path = getenv(self::ENVIRONMENT_VARIABLE);
        if ($path === false || $path === '') {
            $path = self::DEFAULT_PATH;
        }

        if (!is_file($path) || !is_readable($path)) {
            return new self([]);
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return new self([]);
        }

        $classes = [];
        foreach ($decoded as $id => $class) {
            if (is_string($id) && is_string($class) && $class !== '') {
                $classes[$id] = $class;
            }
        }

        return new self($classes);
    }

    public function classFor(string $id): ?string
    {
        return $this->classes[$id] ?? null;
    }
}

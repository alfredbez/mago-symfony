# mago-symfony

Symfony support for Mago 1.60.2 through its PHP extension SDK.

## Features

- Resolves literal service ids on PSR and Symfony containers, `ContainerBuilder`, `ServiceLocator`, and `ServiceProviderInterface`.
- Uses an explicit service map before the class-name fallback. Numeric string ids work; unknown ids keep the native return type.
- Preserves null for invalid-reference flags. Base `Container` can return null for flags `0`, `2`, `3`, and `4`. `ContainerBuilder` throws for `0` and `1`.
- Tracks Config node parents through `children()`, all seven built-in node types, `node()`, prototypes, validation, normalization, and `end()`.
- Reports invalid methods on literal non-array TreeBuilder roots within the same function scope.

## Install and configure a worker

Requires PHP 8.1 or later and exactly `carthage-software/mago` 1.60.2. The fixture suite uses Symfony 6.4. CI targets PHP 8.3 and 8.4.

```bash
composer config repositories.mago-symfony vcs https://github.com/alfredbez/mago-symfony
composer require --dev 'alfredbez/mago-symfony:dev-main'
```

Create `mago-symfony-worker.php` in your project root:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

(new \Mago\Sdk\Worker(\AlfredBez\MagoSymfony\SymfonyExtension::create()))->run();
```

Register the worker in your `mago.toml`:

```toml
[extension-hosts.symfony]
command = ["php", "mago-symfony-worker.php"]
workers = 1
```

Include your installed Symfony and PSR packages in Mago's source configuration so their classes and interfaces are available. This repository's [mago.toml](mago.toml) is a fixture-suite example.

Create `.mago/symfony-container.json` with service ids as keys and class or interface names as values:

```json
{
  "app.mailer": "App\\Service\\Mailer",
  "App\\Contract\\Greeter": "App\\Service\\ConcreteGreeter"
}
```

Set `MAGO_SYMFONY_CONTAINER_MAP` in the worker environment to use another path. Relative paths resolve against the worker's working directory. Without a readable JSON map, only the class-name fallback applies. Map targets must exist in Mago's codebase.

## Known gaps

- A class-name id is a typing convention, not proof that the container registers that service. Maps are explicit typing claims; private, removed, optional, scalar, and factory-specific services need richer metadata.
- The map does not parse Symfony container XML or infer parameters. `has()` retains its native bool type; it does not prove that an id is registered.
- Return-type providers cannot inspect TreeBuilder constructor syntax in this SDK. Root inference assumes an array; a separate hook reports invalid methods for local literal non-array constructors. Non-array-specific methods and exact root return types remain incomplete.
- The root hook does not follow branches, aliases, properties, or by-reference closure captures. Nested function scopes are isolated.
- Untracked NodeBuilder parents remain nullable. Exact null for standalone construction, explicit `setParent()` mutations, custom node mappings, and unions of different tracked parents remain incomplete.

## Run tests and verify harvested sources

```bash
composer install
php tests/check-spec.php
composer test
```

`composer test` checks real Symfony runtime contracts, validates the extension, and compares exact per-file diagnostic counts in both extension modes. New fixtures also pin diagnostic codes and locations. The committed test map makes the suite independent of a local `.mago` directory.

[spec/catalog.json](spec/catalog.json) records eight conformance cases and commit-pinned PHPStan, Psalm, and Symfony sources. Each case states how the upstream test was adapted and links positive and negative fixtures. The analyzer plugins themselves are not copied or executed.

To re-fetch and verify the source fingerprints, install and authenticate the GitHub CLI, then run:

```bash
php spec/harvest.php
```

The harvester reads only the pinned source files and compares SHA-256 hashes. It does not rewrite fixtures or require network access during normal tests.

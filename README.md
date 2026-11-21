# alfredbez/mago-symfony

Symfony support for [Mago](https://github.com/carthage-software/mago) 1.60.2 through its PHP extension SDK.

## Features

- **Compiled container XML and JSON service maps.** Parses Symfony's compiled container XML (`MAGO_SYMFONY_CONTAINER_XML`, with automatic discovery of `var/cache/dev/*Container.xml`) for public and private services, multi-hop aliases with cycle protection, and class or service factories refined through codebase return types. Falls back to a JSON service map (`MAGO_SYMFONY_CONTAINER_MAP` or `.mago/symfony-container.json`) and class-name resolution for `ContainerInterface`, `Container`, `ContainerBuilder`, `ServiceLocator`, and `ServiceProviderInterface`.
- **Container service visibility and strict ID checks.** When a compiled XML container is loaded, reports `alfredbez/symfony/private-service-access` for direct `ContainerInterface::get()` or `Container::get()` calls that fetch private services or aliases (skipping `ServiceLocator`, `ContainerBuilder`, `TestContainer`, and PHPUnit `TestCase` subclasses). When `MAGO_SYMFONY_STRICT_CONTAINER_SERVICES=1` is set, also reports `alfredbez/symfony/unknown-service-id` for unknown literal service IDs unless a non-throwing `invalidBehavior` flag is passed.
- **Typed container and `ParameterBag` parameters.** Resolves literal keys on `ParameterBagInterface::get()`, `ContainerInterface::getParameter()`, and `AbstractController::getParameter()` from compiled XML into concrete scalar, list, keyed-array, constant, binary, and `%env(...)%` processor types.
- **Invalid-reference flag nullability.** Preserves nullable returns for flags `0`, `2`, `3`, and `4` on `Container`, and treats flags `0` and `1` as non-null on `ContainerBuilder`.
- **Config `TreeBuilder` fluent chains and custom `NodeBuilder` mappings.** Tracks parent chains across `getRootNode()`, `children()`, all built-in node types, `node()`, prototypes, `validate()`, `beforeNormalization()`, and `end()`. Tracks `NodeBuilder::setNodeClass()`, `NodeBuilder::setParent()`, custom `NodeBuilder` subclasses across `end()` and nested `arrayNode()->children()`, and custom prototype mappings. Untracked `NodeDefinition::end()` delegates to Symfony's declared return type on Symfony 6.4/7.2 instead of fabricating `null`, while tracked parents and Symfony 7 `ArrayNodeDefinition<null>` stay exact.
- **Non-array `TreeBuilder` root validation and Symfony 7 return filter.** Reports `alfredbez/symfony/invalid-root-node-method` for invalid calls on literal non-array `TreeBuilder` roots within the same function scope, and filters Symfony 7's `TreeBuilder<string>` vs `TreeBuilder<'array'>` default-parameter false positive.
- **Console command argument, option, and helper support.** Reports `alfredbez/symfony/unknown-console-argument` and `alfredbez/symfony/unknown-console-option` when `Command` subclasses request undeclared literal names from `InputInterface::getArgument()` or `getOption()`. Reports `alfredbez/symfony/invalid-console-input-definition` for runtime-throwing `InputArgument` and `InputOption` mode/default combinations in `configure()`. Narrows `HelperSet::get()` and `Command::getHelper()` for Symfony Console's built-in `formatter`, `debug_formatter`, `process`, and `question` helpers.
- **HTTP Foundation return-type providers.** Narrows `HeaderBag::get($key, $default)` to `string` for non-null string defaults, narrows `Request::getRequestFormat()` and `Request::getPreferredFormat()` to `string` when `$default` is omitted or non-null string, and types `ResponseHeaderBag::getCookies()` as `list<Cookie>` for `COOKIES_FLAT` and `array<string, array<string, array<string, Cookie>>>` for `COOKIES_ARRAY`.
- **`FormInterface::getErrors()` generic element type.** Types `Symfony\Component\Form\FormInterface::getErrors($deep = false, $flatten = true)` as `FormErrorIterator<FormError>` whenever `$deep` is `false` or `$flatten` is `true`, and `FormErrorIterator<FormError|FormErrorIterator>` when `$deep` may be `true` while `$flatten` may be `false`.
- **`SerializerInterface::deserialize()` and `DenormalizerInterface::denormalize()` return types.** Resolves literal class-string `$type` arguments and `'Foo[]'` / `'Foo[][]'` array notation to `Foo`, `array<array-key, Foo>`, and nested array types when `Foo` exists in the codebase.
- **Configurable Security `getUser()` narrowing.** When `MAGO_SYMFONY_USER_CLASS` is set to a class or interface implementing `UserInterface`, narrows `AbstractController::getUser()`, `Symfony\Bundle\SecurityBundle\Security::getUser()`, `Symfony\Component\Security\Core\Security::getUser()`, and `TokenInterface::getUser()` to `ConfiguredUser|null`. Leaves zero-config behavior unchanged.
- **`AbstractController` and `#[Required]` autowiring initializers.** Recognizes `AbstractController::setContainer()` and methods carrying `#[Symfony\Contracts\Service\Attribute\Required]` as class initializers when `check-property-initialization = true` is enabled.

## Supported versions

- PHP `~8.1.0 || ~8.2.0 || ~8.3.0 || ~8.4.0 || ~8.5.0 || ~8.6.0`
- Mago (`carthage-software/mago`) `~1.60.2`
- Symfony `^6.4 || ^7.0` (CI tests PHP 8.3 and 8.4 against Symfony 6.4 and the locked Symfony 7.4 suite)

## Installation and worker setup

`alfredbez/mago-symfony` is not published on Packagist yet. Register the GitHub repository and require `dev-main`:

```shell
composer config repositories.mago-symfony vcs https://github.com/alfredbez/mago-symfony
composer require --dev alfredbez/mago-symfony:dev-main
```

Create `.mago/symfony-worker.php`:

```php
<?php

declare(strict_types=1);

use AlfredBez\MagoSymfony\SymfonyExtension;
use Mago\Sdk\Worker;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Worker(SymfonyExtension::create()))->run();
```

Register the worker in `mago.toml` and include your installed Symfony and PSR packages in `[source].includes`:

```toml
[extension-hosts.symfony]
command = ["php", ".mago/symfony-worker.php"]
workers = 1
```

### Environment variables

- `MAGO_SYMFONY_CONTAINER_XML`: path to Symfony's compiled container XML dump (for example `var/cache/dev/App_KernelDevDebugContainer.xml`). When unset, the extension searches `var/cache/dev/*Container.xml` relative to the working directory.
- `MAGO_SYMFONY_CONTAINER_MAP`: path to a JSON file mapping service ids to class or interface names (defaults to `.mago/symfony-container.json` when present). Map targets must exist in Mago's codebase.
- `MAGO_SYMFONY_USER_CLASS`: optional FQCN implementing `Symfony\Component\Security\Core\User\UserInterface` used to narrow Security `getUser()` calls to `ConfiguredUser|null`.
- `MAGO_SYMFONY_STRICT_CONTAINER_SERVICES`: set to `1` alongside a compiled container XML dump to report `alfredbez/symfony/unknown-service-id` for unknown literal service IDs fetched with throwing invalid-reference behavior.

Validate the extension host and run the analyzer:

```shell
vendor/bin/mago extension validate
vendor/bin/mago analyze
```

## Known limits (Mago 1.60.2)

- Without a compiled container XML or JSON map, class-name service ids are a typing convention rather than proof of container registration. `ContainerInterface::has()` cannot narrow subsequent `get()` calls because Mago 1.60.2 `InvocationAssertions` only narrows parameter variables and `$this`.
- Return-type providers cannot inspect enclosing `Command` class context or `TreeBuilder` constructor AST syntax in Mago 1.60.2. As a result, per-command `InputInterface::getArgument()` and `getOption()` return-type narrowing (`SYMFONY-K002`) is blocked by the Mago 1.60.2 SDK, while `ConsoleCommandArgumentsHook` validates argument/option names and invalid mode/default definitions. `RootNodeReturnTypeProvider` infers `ArrayNodeDefinition`, and `TreeBuilderRootNodeHook` separately reports invalid calls on local literal non-array roots.
- `SessionInterface::get()` and `InputBag::get()` are intentionally not narrowed on non-null defaults because stored `null` or arbitrary values bypass defaults at runtime.
- On Symfony 6.4/7.2 (before Symfony's `@template TParent = null`), an untracked `NodeBuilder` stays nullable (`ArrayNodeDefinition|null`) and an untracked `NodeDefinition` parameter delegates to Symfony's declared `end()` return type.
- On Symfony 7.4, passing a custom (non-built-in) type name to `ArrayNodeDefinition::prototype()` triggers Symfony's own `@template T` constraint on `prototype()`; overriding built-in types via `setNodeClass('scalar', ...)` works on both Symfony 6.4 and 7.x. Fluent `setNodeClass()` mappings propagate downward into nested `children()` and prototypes, not backward across `end()` to a pre-existing root variable.
- `ConsoleCommandArgumentsHook` checks static `addArgument()`, `addOption()`, and literal `setDefinition([new InputArgument(...), new InputOption(...)])` declarations in `configure()`, and skips commands that call `parent::configure()` or dynamic definition helpers.

## Tests, corpus checks, and benchmarks

```shell
composer install
php tests/check-spec.php
composer test
```

`composer test` verifies real Symfony runtime contracts (`tests/runtime.php`), validates the extension host, and checks exact per-file diagnostics in both extension modes (with JSON map and compiled XML).

Run pinned OSS corpus snapshot checks (`symfony-demo`, `symfony`, `shopware`, `drupal`) against `tools/corpus-baseline.json`:

```shell
php tools/corpus-run.php --check
php tools/corpus-run.php --target=symfony-demo,symfony --check
php tools/corpus-run.php --update-baseline
```

Run same-host local benchmarks (`--save` / `--compare`):

```shell
php bench/run-local.php --iterations=10000 --runs=5
```

Re-verify pinned upstream conformance source hashes with `php spec/harvest.php`.

## License

MIT. See [LICENSE](LICENSE).

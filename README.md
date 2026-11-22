# alfredbez/mago-symfony

Symfony support for [Mago](https://github.com/carthage-software/mago) 1.60.2 through its PHP extension SDK.

## Supported versions

- PHP `~8.1.0 || ~8.2.0 || ~8.3.0 || ~8.4.0 || ~8.5.0 || ~8.6.0`
- Mago (`carthage-software/mago`) `~1.60.2`
- Symfony `^6.4 || ^7.0` (CI tests PHP 8.3 and 8.4 against Symfony 6.4 and Symfony 7.4)

## Installation and quickstart

Until `0.1.0` is published on Packagist, register the GitHub repository and install `dev-main`:

```shell
composer config repositories.mago-symfony vcs https://github.com/alfredbez/mago-symfony
composer require --dev alfredbez/mago-symfony:dev-main
```

Once tagged on Packagist, install the package directly:

```shell
composer require --dev alfredbez/mago-symfony:^0.1
```

Register the bundled worker binary in `mago.toml` and include your installed Symfony and PSR packages in `[source].includes`:

```toml
[source]
includes = ["vendor/symfony", "vendor/psr"]

[extension-hosts.symfony]
command = ["php", "vendor/bin/mago-symfony-worker"]
workers = 1
```

Validate the extension host and run the analyzer:

```shell
vendor/bin/mago extension validate
vendor/bin/mago analyze
```

### Configuration environment variables

Set any of these variables in the environment that invokes `vendor/bin/mago`:

- `MAGO_SYMFONY_CONTAINER_XML` sets the path to Symfony's compiled container XML dump (for example `var/cache/dev/App_KernelDevDebugContainer.xml`). When unset, the extension searches `var/cache/dev/*Container.xml` relative to the working directory.
- `MAGO_SYMFONY_CONTAINER_MAP` sets the path to a JSON file mapping service IDs to class or interface names (defaults to `.mago/symfony-container.json` when present). Map targets must exist in Mago's codebase.
- `MAGO_SYMFONY_USER_CLASS` sets an optional FQCN implementing `Symfony\Component\Security\Core\User\UserInterface` to narrow Security `getUser()` calls to `ConfiguredUser|null`.
- `MAGO_SYMFONY_STRICT_CONTAINER_SERVICES` reports `alfredbez/symfony/unknown-service-id` when set to `1` alongside a compiled container XML dump and a throwing `get()` call requests an unknown literal service ID.

## Features

- **Compiled container XML and JSON service maps.** Parses Symfony's compiled container XML for public and private services, multi-hop aliases with cycle protection, and class or service factories refined through codebase return types. Falls back to a JSON service map and class-name resolution for `ContainerInterface`, `Container`, `ContainerBuilder`, `ServiceLocator`, and `ServiceProviderInterface`.
- **Container service visibility and strict ID checks.** When a compiled XML container is loaded, reports `alfredbez/symfony/private-service-access` for direct throwing `ContainerInterface::get()` or `Container::get()` calls that fetch private services or aliases (skipping `ServiceLocator`, `ContainerBuilder`, `TestContainer`, PHPUnit `TestCase` subclasses, and non-throwing `invalidBehavior` flags). Reports `alfredbez/symfony/unknown-service-id` when `MAGO_SYMFONY_STRICT_CONTAINER_SERVICES=1` is enabled.
- **Typed container and `ParameterBag` parameters.** Resolves literal keys on `ParameterBagInterface::get()`, `ContainerInterface::getParameter()`, and `AbstractController::getParameter()` from compiled XML into concrete scalar, list, keyed-array, constant, binary, and `%env(...)%` processor types.
- **Invalid-reference flag nullability.** Preserves nullable returns for flags `0`, `2`, `3`, and `4` on `Container`, and treats flags `0` and `1` as non-null on `ContainerBuilder`.
- **Config `TreeBuilder` fluent chains and custom `NodeBuilder` mappings.** Tracks parent chains across `getRootNode()`, `children()`, all built-in node types, `node()`, prototypes, `validate()`, `beforeNormalization()`, and `end()`. Tracks `NodeBuilder::setNodeClass()`, `NodeBuilder::setParent()`, custom `NodeBuilder` subclasses across `end()` and nested `arrayNode()->children()`, and custom prototype mappings.
- **Non-array `TreeBuilder` root validation and Symfony 7 return filter.** Reports `alfredbez/symfony/invalid-root-node-method` for invalid calls on literal non-array `TreeBuilder` roots within the same function scope, and filters Symfony 7's `TreeBuilder<string>` vs `TreeBuilder<'array'>` default-parameter false positive.
- **Console command input validation and helper narrowing.** Reports `alfredbez/symfony/unknown-console-argument` and `alfredbez/symfony/unknown-console-option` when `Command` subclasses request undeclared literal names from `InputInterface::getArgument()` or `getOption()`. Reports `alfredbez/symfony/invalid-console-input-definition` for runtime-throwing `InputArgument` and `InputOption` mode and default combinations in `configure()`. Narrows `HelperSet::get()` and `Command::getHelper()` for Symfony Console's built-in `formatter`, `debug_formatter`, `process`, and `question` helpers.
- **HTTP Foundation return-type providers.** Narrows `HeaderBag::get($key, $default)` to `string` for non-null string defaults, narrows `ParameterBag::getEnum()` and `InputBag::getEnum()` to the target `BackedEnum` when a matching non-null default is supplied, narrows `Request::getRequestFormat()` and `Request::getPreferredFormat()` to `string` for omitted or non-null string defaults, narrows `Request::getPreferredLanguage()` to `string` for non-empty 0-indexed string lists, and types `ResponseHeaderBag::getCookies()` as `list<Cookie>` for `COOKIES_FLAT` and `array<array-key, array<array-key, array<array-key, Cookie>>>` for `COOKIES_ARRAY`.
- **HttpKernel controller attribute narrowing.** Narrows `ControllerEvent::getAttributes($className)` and `ControllerArgumentsEvent::getAttributes($className)` to `list<T>` when `$className` resolves to an existing class or interface (closing Symfony 6.4's `list<object>` return type).
- **`FormInterface::getErrors()` generic element type.** Types `Symfony\Component\Form\FormInterface::getErrors($deep = false, $flatten = true)` as `FormErrorIterator<FormError>` whenever `$deep` is `false` or `$flatten` is `true`, and `FormErrorIterator<FormError|FormErrorIterator>` when `$deep` may be `true` while `$flatten` may be `false`.
- **`SerializerInterface::deserialize()` and `DenormalizerInterface::denormalize()` return types.** Resolves literal class-string `$type` arguments and `'Foo[]'` or `'Foo[][]'` array notation to `Foo`, `array<array-key, Foo>`, and nested array types when `Foo` exists in the codebase.
- **Messenger wrapped exception filtering.** Narrows `WrappedExceptionsInterface::getWrappedExceptions($class)` and `WrappedExceptionsTrait::getWrappedExceptions($class)` to `array<array-key, T>` for concrete class or interface filters (closing Symfony 6.4's untemplated `Throwable[]` return type).
- **Configurable Security `getUser()` narrowing.** When `MAGO_SYMFONY_USER_CLASS` is set to a class or interface implementing `UserInterface`, narrows `AbstractController::getUser()`, `Symfony\Bundle\SecurityBundle\Security::getUser()`, `Symfony\Component\Security\Core\Security::getUser()`, and `TokenInterface::getUser()` to `ConfiguredUser|null`.
- **Framework lifecycle and `#[Required]` class initializers.** Recognizes `AbstractController::setContainer()`, `ConstraintValidator::initialize()`, `InputAwareHelper::setInput()`, and methods carrying `#[Symfony\Contracts\Service\Attribute\Required]` as class initializers when `check-property-initialization = true` is enabled.

## Known Mago 1.60.2 SDK boundaries

- Without a compiled container XML or JSON map, class-name service IDs are a typing convention rather than proof of container registration. `ContainerInterface::has()` cannot narrow subsequent `get()` calls because Mago 1.60.2 `InvocationAssertions` only narrows parameter variables and `$this`.
- Return-type providers cannot inspect enclosing `Command` class context or `TreeBuilder` constructor AST syntax in Mago 1.60.2. Per-command `InputInterface::getArgument()` and `getOption()` return-type narrowing (`SYMFONY-K002`) is blocked by the Mago 1.60.2 SDK, while `ConsoleCommandArgumentsHook` validates argument and option names plus invalid mode and default definitions.
- `SessionInterface::get()`, `InputBag::get()`, `Route::getDefault()`, and `FormConfigInterface::getOption()` are not narrowed on defaults because stored `null` or user-configured values can bypass defaults at runtime.
- `Envelope::last()`, `Envelope::all()`, `Request::getContent()`, `FileLocatorInterface::locate()`, `EventDispatcherInterface::dispatch()`, `CacheInterface::get()`, `ValidatorInterface::validate()`, `RouteCollection`, `FormView`, and Twig rendering helpers are already typed natively by Mago 1.60.2 from Symfony 6.4 and 7.4 vendor signatures. `HandledStamp::getResult()` stays `mixed` because compiled container XML does not provide a message-to-handler return graph.
- `HelperSet::get()` and `Command::getHelper()` narrowing assumes Symfony Console's four default helper names (`formatter`, `debug_formatter`, `process`, `question`) are not remapped to unrelated classes at runtime.
- On Symfony 6.4 and 7.2 (before Symfony's `@template TParent = null`), an untracked `NodeBuilder` stays nullable (`ArrayNodeDefinition|null`) and an untracked `NodeDefinition` parameter delegates to Symfony's declared `end()` return type.
- `ConsoleCommandArgumentsHook` checks static `addArgument()`, `addOption()`, and literal `setDefinition([new InputArgument(...), new InputOption(...)])` declarations in `configure()`, and skips commands that call `parent::configure()` or dynamic definition helpers.

## Tests, corpus checks, and benchmarks

```shell
composer install
php tests/check-spec.php
composer test
```

`composer test` verifies Symfony runtime contracts (`tests/runtime.php`), validates the `bin/mago-symfony-worker` extension host, and checks exact per-file diagnostics in both extension modes (JSON map, compiled XML, and Security user configuration).

Run pinned OSS corpus snapshot checks (`symfony-demo`, `symfony`, `shopware`, `drupal`) against `tools/corpus-baseline.json`:

```shell
php tools/corpus-run.php --check
php tools/corpus-run.php --target=symfony-demo,symfony --check
php tools/corpus-run.php --update-baseline
```

Run same-host local benchmarks and verify pinned conformance hashes:

```shell
php bench/run-local.php --iterations=10000 --runs=5
php spec/harvest.php
```

## License

MIT. See [LICENSE](LICENSE).

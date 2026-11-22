# alfredbez/mago-symfony

[![tests](https://github.com/alfredbez/mago-symfony/actions/workflows/ci.yml/badge.svg)](https://github.com/alfredbez/mago-symfony/actions/workflows/ci.yml)
[![Packagist version](https://img.shields.io/packagist/v/alfredbez/mago-symfony.svg)](https://packagist.org/packages/alfredbez/mago-symfony)
[![Packagist downloads](https://img.shields.io/packagist/dt/alfredbez/mago-symfony.svg)](https://packagist.org/packages/alfredbez/mago-symfony)
![PHP](https://img.shields.io/badge/php-8.1%20to%208.6-777BB4)
[![Mago](https://img.shields.io/badge/mago-%5E1.60.2-blue)](https://github.com/carthage-software/mago)
[![Symfony](https://img.shields.io/badge/symfony-6.4%20%7C%207.x-black)](https://symfony.com)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Symfony support for [Mago](https://github.com/carthage-software/mago). It teaches Mago the types behind Symfony's container, config builders, console commands, and HTTP, Form, Serializer, Messenger, and Security APIs.

- Reports private service access and console argument or option typos that Symfony only rejects at runtime.
- Turns `ContainerInterface::get()` and parameter lookups into concrete types.
- Types `TreeBuilder` and `NodeBuilder` chains, so node methods and `end()` return values resolve.
- Narrows `Request`, `HeaderBag`, `Form`, `Serializer`, and Security calls that return `mixed` or a broad type on Symfony 6.4 and 7.x.

A console typo Mago reports before the command runs:

```php
$this->addArgument('username', InputArgument::REQUIRED);
// ...
$input->getArgument('user_name'); // alfredbez/symfony/unknown-console-argument
```

## Setup

Requires PHP 8.1 or newer, Mago `^1.60.2`, and Symfony `^6.4 || ^7.0`.

```shell
composer require --dev alfredbez/mago-symfony
```

Register the bundled worker in `mago.toml`:

```toml
[source]
includes = ["vendor/symfony", "vendor/psr"]

[extension-hosts.symfony]
command = ["php", "-d", "xdebug.mode=off", "-d", "pcov.enabled=0", "vendor/bin/mago-symfony-worker"]
workers = 1
```

- `[source] includes` must contain the Symfony packages your app uses and the PSR container contracts. Mago reads those files to resolve method signatures. Listing `vendor/symfony` covers every installed Symfony package. If you list individual packages instead, keep the ones your app calls.
- The `-d` flags keep Xdebug and PCOV out of the worker. Both hooks add startup cost and coverage bookkeeping to a run that only reads code.

Check the worker and run the analyzer:

```shell
vendor/bin/mago extension validate
vendor/bin/mago analyze
```

## Container data

Service and parameter types come from Symfony's compiled container. Point the extension at the dump:

| Variable | What it does |
| --- | --- |
| `MAGO_SYMFONY_CONTAINER_XML` | Path to the compiled container XML, for example `var/cache/dev/App_KernelDevDebugContainer.xml`. When unset, the extension searches `var/cache/dev/*Container.xml` from the working directory. |
| `MAGO_SYMFONY_CONTAINER_MAP` | Path to a JSON file that maps service IDs to class or interface names. Defaults to `.mago/symfony-container.json` when that file exists. Targets must exist in Mago's codebase. |
| `MAGO_SYMFONY_USER_CLASS` | FQCN of a class or interface that implements `UserInterface`. Narrows Security `getUser()` to `ConfiguredUser` or `null`. |
| `MAGO_SYMFONY_STRICT_CONTAINER_SERVICES` | Set to `1` to report `alfredbez/symfony/unknown-service-id` for literal IDs that are missing from the compiled XML. Requires a loaded XML dump. |

Without a compiled dump or JSON map, a class-name service ID is a typing convention, not proof that the container registered it.

## Checks

| Code | Level | What it reports |
| --- | --- | --- |
| `alfredbez/symfony/private-service-access` | error | A throwing `get()` fetches a private service or alias from a public container. Requires a compiled XML dump. |
| `alfredbez/symfony/unknown-service-id` | error | A literal service ID is missing from the compiled XML. Opt-in with `MAGO_SYMFONY_STRICT_CONTAINER_SERVICES=1`. |
| `alfredbez/symfony/invalid-root-node-method` | error | A `TreeBuilder` with a literal non-array root, such as `new TreeBuilder('root', 'scalar')`, calls a node method that needs an array root. |
| `alfredbez/symfony/unknown-console-argument` | error | `getArgument('name')` asks for an argument that `configure()` does not declare. |
| `alfredbez/symfony/unknown-console-option` | error | `getOption('name')` asks for an option that `configure()` does not declare. |
| `alfredbez/symfony/invalid-console-input-definition` | error | `InputArgument` or `InputOption` uses a mode and default combination that Symfony rejects, such as `VALUE_NONE` with a non-null default. |

`private-service-access` skips `ServiceLocator`, `ContainerBuilder`, `TestContainer`, PHPUnit `TestCase` subclasses, and calls with a non-throwing invalid-reference flag. `unknown-console-*` skips `parent::configure()`, definitions built by dynamic helper methods, and the default application options (`help`, `quiet`, `silent`, `verbose`, `version`, `ansi`, `no-ansi`, `no-interaction`, `env`, `no-debug`, `profile`).

To silence a code in one project:

```toml
[analyzer]
ignore = ["alfredbez/symfony/private-service-access"]
```

To silence one call:

```php
// @mago-ignore analysis:alfredbez/symfony/private-service-access
$container->get('app.private_service');
```

## What it types

| Feature | What Mago knows |
| --- | --- |
| `ContainerInterface::get('id')` and `Container::get('id')` | The service class from the compiled XML or JSON map, including multi-hop aliases, class and service factories, and class-name IDs. `ContainerBuilder`, `ServiceLocator`, and `ServiceProviderInterface` fall back to class-name resolution. |
| `ParameterBagInterface::get()`, `ContainerInterface::getParameter()`, `Container::getParameter()`, `AbstractController::getParameter()` | The parameter's literal scalar, list, keyed array, constant, or `%env(...)%` type. |
| `Container::get()` and `ContainerBuilder::get()` with invalid-reference flags | Nullable returns for flags `0`, `2`, `3`, and `4` on `Container`; non-null for flags `0` and `1` on `ContainerBuilder`. |
| `TreeBuilder` and `NodeDefinition` chains | The node type after `children()`, `node()`, prototypes, `validate()`, `beforeNormalization()`, and `end()`. Tracks custom `NodeBuilder` classes through `setNodeClass()` and `setParent()`. |
| `HelperSet::get()` and `Command::getHelper()` | `FormatterHelper`, `DebugFormatterHelper`, `ProcessHelper`, or `QuestionHelper` for the four default names. |
| `HeaderBag::get($key, $default)` | `string` when the default is a non-null string. |
| `ParameterBag::getEnum()` and `InputBag::getEnum()` | The target `BackedEnum` when a matching non-null default is supplied. |
| `Request::getRequestFormat()` and `getPreferredFormat()` | `string` when the default is omitted or is a non-null string. |
| `Request::getPreferredLanguage()` | `string` for a non-empty 0-indexed list of strings. |
| `ResponseHeaderBag::getCookies()` | `list<Cookie>` for `COOKIES_FLAT`, nested `Cookie` arrays for `COOKIES_ARRAY`. |
| `ControllerEvent::getAttributes()` and `ControllerArgumentsEvent::getAttributes()` | `list<T>` when the class-string argument names an existing class or interface. |
| `FormInterface::getErrors()` | `FormErrorIterator<FormError>`, or an iterator of `FormError` and nested iterators when `$deep` may be true and `$flatten` may be false. |
| `SerializerInterface::deserialize()` and `DenormalizerInterface::denormalize()` | `Foo`, `array<array-key, Foo>`, or nested arrays for literal `Foo`, `Foo[]`, and `Foo[][]` types. |
| `WrappedExceptionsInterface::getWrappedExceptions()` | `array<array-key, T>` for a concrete class or interface filter. |
| Security `getUser()` | `ConfiguredUser` or `null` when `MAGO_SYMFONY_USER_CLASS` is set. Covers `AbstractController`, `SecurityBundle\Security`, `Security\Core\Security`, and `TokenInterface`. |
| Lifecycle methods | `AbstractController::setContainer()`, `ConstraintValidator::initialize()`, `InputAwareHelper::setInput()`, and methods with `#[Required]` initialize properties when `check-property-initialization = true`. |

## Known limitations

- `ContainerInterface::has()` cannot narrow a later `get()`. Mago `1.60.2` through `1.61.0` narrows only parameter variables and `$this` in invocation assertions.
- Return-type providers cannot read the enclosing `Command` class or the `TreeBuilder` constructor syntax. Per-command narrowing of `getArgument()` and `getOption()` stays blocked in the Mago SDK. The extension validates argument and option names instead.
- `SessionInterface::get()`, `InputBag::get()`, `Route::getDefault()`, and `FormConfigInterface::getOption()` are not narrowed on defaults. A stored `null` or a configured value can bypass the default at runtime.
- `HandledStamp::getResult()` stays `mixed` because compiled container XML has no message-to-handler return graph.
- `Envelope::last()`, `Envelope::all()`, `Request::getContent()`, `FileLocatorInterface::locate()`, `EventDispatcherInterface::dispatch()`, `CacheInterface::get()`, `ValidatorInterface::validate()`, `RouteCollection`, `FormView`, and Twig helpers are already typed by Mago from Symfony 6.4 and 7.x vendor signatures.
- `HelperSet::get()` and `Command::getHelper()` narrowing assumes the four default helper names are not remapped to unrelated classes.
- `ControllerEvent::getAttributes()` narrowing assumes the attribute map passed to `setController()` matches its class keys.
- `WrappedExceptionsInterface::getWrappedExceptions()` narrows only `Throwable` subtypes, so non-`Throwable` marker interfaces keep `Throwable` methods.
- On Symfony 6.4 and 7.2, an untracked `NodeBuilder` stays nullable (`ArrayNodeDefinition|null`), and an untracked `NodeDefinition` falls back to Symfony's declared `end()` return type.
- `ConsoleCommandArgumentsHook` reads static `addArgument()`, `addOption()`, and literal `setDefinition([new InputArgument(...), new InputOption(...)])` calls in `configure()`.

## Development

```shell
composer install
php tests/check-spec.php
composer test
```

`composer test` runs Symfony runtime contract tests, validates `bin/mago-symfony-worker`, and checks exact diagnostics in JSON map, compiled XML, and Security user modes.

Run pinned OSS corpus checks (`symfony-demo`, `symfony`, `shopware`, `drupal`):

```shell
php tools/corpus-run.php --check
php tools/corpus-run.php --target=symfony-demo,symfony --check
php tools/corpus-run.php --update-baseline
```

Run local benchmarks and verify conformance hashes:

```shell
php bench/run-local.php --iterations=10000 --runs=5
php spec/harvest.php
```

## License

MIT. See [LICENSE](LICENSE).

# Changelog

All notable changes to `alfredbez/mago-symfony` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-11-26

First stable release targeting PHP `8.1` through `8.6`, Mago `^1.60.2` (verified on `1.60.2` and `1.61.0`), and Symfony `^6.4 || ^7.0`.

### Added

- `Symfony\Component\Console\Command\Command::initialize()` class initializer support for `check-property-initialization = true`.
- Property initialization support for non-readonly public writable properties carrying `#[Symfony\Contracts\Service\Attribute\Required]` (`AutowireRequiredPropertiesPass`), while keeping private, protected, readonly, static, and non-`#[Required]` properties checked.
- Method-level Symfony entry points for `find-unused-definitions = true`: method attributes (`#[Route]`, `#[AsEventListener]`, `#[AsMessageHandler]`, `#[Callback]`, `#[Required]`) and standard lifecycle methods on `Command`, `AbstractType`, `ConstraintValidator`, `Voter`, `ConfigurationInterface`, `ExtensionInterface`, `CompilerPassInterface`, `EventSubscriberInterface`, and `ServiceSubscriberInterface`.
- Explicit PHP extension requirements (`ext-dom`, `ext-libxml`, `ext-tokenizer`, `ext-xmlreader`) in `composer.json`.
- Compatibility and SemVer policy in `README.md`, `@internal` markers on internal analyzer plugins and providers, and `[extension-hosts.symfony] environment` configuration docs.
- Bounded self-analysis gate (`mago-self.toml` and `tools/self-analyze-gate.php`) and floor-compatible PHP `8.1`/`8.2` plus pinned Mago `1.60.2` and `1.61.0` CI lanes.

### Changed

- Explicitly configured invalid `MAGO_SYMFONY_CONTAINER_XML` or `MAGO_SYMFONY_CONTAINER_MAP` paths now report a clear error to stderr and fail worker registration cleanly without corrupting the worker stdout protocol. Invalid `MAGO_SYMFONY_USER_CLASS` values now warn on stderr while leaving `getUser()` unnarrowed. Absent auto-discovery files remain silent.
- `tools/corpus-run.php` runs the production `bin/mago-symfony-worker` binary with Xdebug and PCOV disabled.

### Fixed

- `SerializerInterface::deserialize()` and `DenormalizerInterface::denormalize()` resolve non-literal `class-string<T>` type arguments to `T`.
- `Request::getPreferredLanguage()` narrows keyed arrays containing a required `0` string entry to `string` instead of reading a non-existent SDK property.
- `TreeBuilderReturnIssueFilter` suppresses `ArrayNodeDefinition<TreeBuilder<...>>` vs `ArrayNodeDefinition<null>` parent mismatches when the enclosing method or function has a proven unparameterized native `: ArrayNodeDefinition` return type, while keeping explicit `@return ArrayNodeDefinition<null>`, inherited generic mismatches, wrong concrete node classes, and conditional rebindings reported.
- `TreeBuilderRootNodeIndex` resolves workspace-relative files when the worker runs from a subdirectory containing a Mago config file.

## [0.1.3] - 2026-11-25

### Fixed

- Console command declaration validation checks literal `InputArgument` (`1..7`) and `InputOption` (`1..31`) bitmask bounds, signed/base-prefixed integer literals, and `setDefinition()` definitions against Symfony runtime rules while keeping dynamic modes conservative.
- Console option declarations mirror Symfony's single leading `--` strip instead of `ltrim(..., '-')`, keeping single-dash option names such as `-dash` aligned with exact `getOption()` lookups, reporting stripped mismatches and empty option names, and preserving `VALUE_NEGATABLE` (`no-*`) and `--known` checks.
- Default application console options are derived from the analyzed `Symfony\Component\Console\Application` class (plus `Symfony\Bundle\FrameworkBundle\Console\Application` when present in the codebase) rather than unconditionally whitelisting `silent`, `env`, `no-debug`, and `profile`, while preserving command-local declarations.
- Symfony 7 `TreeBuilder` return filtering uses path-sensitive control-flow analysis so conditional or exception-path rebindings with a reachable incompatible root type keep Mago's native `invalid-return-statement` diagnostic, while dominated compatible returns stay clean.

## [0.1.2] - 2026-11-25

### Fixed

- Console command validation inspects non-overridable local constructor helpers recursively instead of skipping validation whenever `__construct()` calls any method on `$this`, while preserving conservative skips for constructor-configured inputs, custom parents, trait overrides, anonymous-class constructor arguments, and overridable or unresolved helpers.
- `$input->getOption()` validates exact runtime option keys instead of stripping leading dashes, reporting dashed lookups such as `--known` and `--verbose` while keeping normalized `addOption()` and `InputOption` declarations working with plain lookup keys.

## [0.1.1] - 2026-11-24

### Fixed

- Console command validation reports undeclared `getArgument()` calls on option-only commands and undeclared `getOption()` calls on argument-only commands while preserving default `command` and application options and skipping constructor-configured commands.
- `TreeBuilder::getRootNode()` resolves concrete non-array root node types (`ScalarNodeDefinition`, `EnumNodeDefinition`, `IntegerNodeDefinition`, and siblings) from constructor literals and template parameters while avoiding duplicate diagnostics with `invalid-root-node-method` and leaving ambiguous or rebound spans conservative.
- Documented the compiled container XML and JSON map boundary for class-name aliases, decorators, and raw service IDs.

## [0.1.0] - 2026-11-22

Version `0.1.0` targets Mago `^1.60.2` (verified on `1.60.2` and `1.61.0`) and Symfony `^6.4 || ^7.0`.

### Added

- Production worker entrypoint `bin/mago-symfony-worker` registered under Composer `bin`, with automatic `vendor/autoload.php` discovery for both installed-dependency and local-checkout execution.
- Dist archive hygiene via `.gitattributes` (`export-ignore`) and Packagist-ready `composer.json` metadata.
- Compiled container XML loader (`MAGO_SYMFONY_CONTAINER_XML` and `var/cache/dev/*Container.xml` auto-discovery) plus JSON service map fallback (`MAGO_SYMFONY_CONTAINER_MAP` and `.mago/symfony-container.json`) for `ContainerInterface`, `Container`, `ContainerBuilder`, `ServiceLocator`, and `ServiceProviderInterface`.
- Container service visibility and strict ID checks (`alfredbez/symfony/private-service-access` and opt-in `MAGO_SYMFONY_STRICT_CONTAINER_SERVICES=1` `alfredbez/symfony/unknown-service-id`), respecting invalid-reference flags and test containers.
- Typed parameter lookup resolution for `ParameterBagInterface::get()`, `ContainerInterface::getParameter()`, and `AbstractController::getParameter()`.
- Config `TreeBuilder`, `NodeBuilder`, `NodeDefinition::end()`, prototypes, `setNodeClass()` custom node mappings, custom `NodeBuilder` subclasses, non-array root validation (`alfredbez/symfony/invalid-root-node-method`), and Symfony 7 `TreeBuilder` return issue filtering.
- Console command argument and option validation (`alfredbez/symfony/unknown-console-argument`, `alfredbez/symfony/unknown-console-option`, `alfredbez/symfony/invalid-console-input-definition`) and built-in helper narrowing on `HelperSet::get()` and `Command::getHelper()`.
- HTTP Foundation return-type providers for `HeaderBag::get()`, `ParameterBag::getEnum()`, `InputBag::getEnum()`, `Request::getRequestFormat()`, `Request::getPreferredFormat()`, `Request::getPreferredLanguage()`, and `ResponseHeaderBag::getCookies()`.
- HttpKernel `ControllerEvent::getAttributes()` and `ControllerArgumentsEvent::getAttributes()` return-type narrowing for concrete attribute class names.
- Form `FormInterface::getErrors()` generic `FormErrorIterator<FormError>` return-type provider.
- Serializer `SerializerInterface::deserialize()` and `DenormalizerInterface::denormalize()` return-type resolution, including `Foo[]` and `Foo[][]` array notation.
- Messenger `WrappedExceptionsInterface::getWrappedExceptions()` and `WrappedExceptionsTrait::getWrappedExceptions()` return-type narrowing for concrete class filters.
- Configurable Security `getUser()` narrowing via `MAGO_SYMFONY_USER_CLASS`.
- Lifecycle class initializers for `AbstractController::setContainer()`, `ConstraintValidator::initialize()`, `InputAwareHelper::setInput()`, and methods carrying `#[Symfony\Contracts\Service\Attribute\Required]`.
- Pinned conformance catalog (`spec/catalog.json` with 27 cases), Symfony 6.4 and 7.4 runtime tests, pinned OSS corpus regression harness, and CodSpeed benchmarks.

### Changed

- Documentation rewritten for first-time setup: badges, copy-paste worker configuration, container data table, check and feature tables, and suppression examples.

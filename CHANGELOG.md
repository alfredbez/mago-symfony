# Changelog

All notable changes to `alfredbez/mago-symfony` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

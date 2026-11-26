<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\MethodCallAnalysisHook;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\SourceLocation;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

/**
 * Validates literal service IDs fetched from compiled Symfony containers:
 * - reports private services fetched directly from public containers
 * - reports unknown literal service IDs when MAGO_SYMFONY_STRICT_CONTAINER_SERVICES=1
 *
 * Skips ServiceLocator, ContainerBuilder, TestContainer, and PHPUnit TestCase subclasses
 * where private or test-only services are legitimately accessible.
 *
 * @internal
 */
final class ContainerGetAccessHook implements MethodCallAnalysisHook
{
    public const PRIVATE_SERVICE_ISSUE = 'private-service-access';
    public const UNKNOWN_SERVICE_ISSUE = 'unknown-service-id';

    private const EXCEPTION_ON_INVALID_REFERENCE = 1;

    private const EXEMPT_RECEIVERS = [
        'symfony\\component\\dependencyinjection\\containerbuilder' => true,
        'symfony\\component\\dependencyinjection\\servicelocator' => true,
        'symfony\\contracts\\service\\serviceproviderinterface' => true,
        'symfony\\bundle\\frameworkbundle\\test\\testcontainer' => true,
    ];

    private const TEST_CASE = 'phpunit\\framework\\testcase';

    /** @var array<string, bool> */
    private array $exemptReceiverCache = [];

    /** @var array<string, bool> */
    private array $testCaseClassCache = [];

    public function __construct(
        private readonly ServiceClassMap $services,
        private readonly bool $strictUnknownServices = false,
    ) {}

    public function getTargets(): array
    {
        return [
            MethodTarget::exact('Symfony\\Component\\DependencyInjection\\ContainerInterface', 'get'),
            MethodTarget::exact('Symfony\\Component\\DependencyInjection\\Container', 'get'),
        ];
    }

    public function getRequirements(): array
    {
        return [
            FileAnalysisRequirement::ReceiverType,
            FileAnalysisRequirement::ArgumentTypes,
            FileAnalysisRequirement::SourceText,
            FileAnalysisRequirement::TargetSubtree,
        ];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        if (!$this->services->isXmlLoaded() || !$context->receiverType instanceof Type) {
            return;
        }

        if ($this->hasExemptReceiver($context, $context->receiverType)) {
            return;
        }

        if ($this->isInsideTestCase($context)) {
            return;
        }

        $idIndex = $this->findArgumentIndex($context->source, $context->node, 0, 'id');
        if ($idIndex === null) {
            return;
        }

        $idType = $context->argumentTypes[$idIndex] ?? null;
        $id = $idType?->getLiteralString();
        if ($id === null || $id === '') {
            return;
        }

        $behaviorIndex = $this->findArgumentIndex($context->source, $context->node, 1, 'invalidBehavior');
        if ($behaviorIndex !== null) {
            $behavior = $context->argumentTypes[$behaviorIndex]?->getLiteralInt();
            if ($behavior !== self::EXCEPTION_ON_INVALID_REFERENCE) {
                return;
            }
        }

        if ($this->services->isPrivateService($id)) {
            $context->report(
                Level::Error,
                self::PRIVATE_SERVICE_ISSUE,
                Issue::at(
                    "Service `{$id}` is private in the compiled container and cannot be fetched directly via Container::get().",
                    new SourceLocation($context->analysis->file, $context->node->span),
                ),
            );

            return;
        }

        if (!$this->strictUnknownServices || $this->services->isKnownService($id)) {
            return;
        }

        $context->report(
            Level::Error,
            self::UNKNOWN_SERVICE_ISSUE,
            Issue::at(
                "Service `{$id}` is not defined in the compiled container.",
                new SourceLocation($context->analysis->file, $context->node->span),
            ),
        );
    }

    private function hasExemptReceiver(NodeAnalysisContext $context, Type $receiverType): bool
    {
        foreach ($receiverType->atomicTypes as $atomic) {
            if (!$atomic instanceof NamedObjectType) {
                return true;
            }

            $names = [$atomic->name];
            foreach ($atomic->intersections ?? [] as $intersection) {
                if ($intersection instanceof NamedObjectType) {
                    $names[] = $intersection->name;
                }
            }

            foreach ($names as $name) {
                if ($this->isExemptClass($context, $name)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isExemptClass(NodeAnalysisContext $context, string $name): bool
    {
        $lower = strtolower($name);
        if (isset(self::EXEMPT_RECEIVERS[$lower])) {
            return true;
        }

        if (isset($this->exemptReceiverCache[$lower])) {
            return $this->exemptReceiverCache[$lower];
        }

        foreach ($context->codebase->getClassAncestors($name) as $ancestor) {
            if (isset(self::EXEMPT_RECEIVERS[strtolower($ancestor)])) {
                return $this->exemptReceiverCache[$lower] = true;
            }
        }

        return $this->exemptReceiverCache[$lower] = false;
    }

    private function isInsideTestCase(NodeAnalysisContext $context): bool
    {
        $source = $context->source;
        $node = $source->getParent($context->node);
        while ($node !== null) {
            if ($node->kind === NodeKind::Class_) {
                $className = $this->resolveClassName($source, $node);
                if ($className === null) {
                    return false;
                }

                $lower = strtolower($className);
                if (isset($this->testCaseClassCache[$lower])) {
                    return $this->testCaseClassCache[$lower];
                }

                if ($lower === self::TEST_CASE) {
                    return $this->testCaseClassCache[$lower] = true;
                }

                foreach ($context->codebase->getClassAncestors($className) as $ancestor) {
                    if (strtolower($ancestor) === self::TEST_CASE) {
                        return $this->testCaseClassCache[$lower] = true;
                    }
                }

                return $this->testCaseClassCache[$lower] = false;
            }
            $node = $source->getParent($node);
        }

        return false;
    }

    private function resolveClassName(SourceFile $source, Node $classNode): ?string
    {
        foreach ($source->getChildren($classNode) as $child) {
            if ($child->kind === NodeKind::LocalIdentifier) {
                return $source->getResolvedName($child)?->name ?? $source->getText($child);
            }
        }

        return null;
    }

    private function findArgumentIndex(SourceFile $source, Node $call, int $position, string $name): ?int
    {
        $argumentList = null;
        foreach ($source->getChildren($call) as $child) {
            if ($child->kind === NodeKind::ArgumentList) {
                $argumentList = $child;
                break;
            }
        }
        if ($argumentList === null) {
            return null;
        }

        $sourceIndex = 0;
        $positionalIndex = 0;
        foreach ($source->getChildren($argumentList) as $argument) {
            $variant = $source->getChildren($argument)[0] ?? null;
            if ($variant === null) {
                continue;
            }

            if ($variant->kind === NodeKind::PositionalArgument) {
                if ($positionalIndex === $position) {
                    return $sourceIndex;
                }
                $positionalIndex++;
                $sourceIndex++;
            } elseif ($variant->kind === NodeKind::NamedArgument) {
                $parts = $source->getChildren($variant);
                if (isset($parts[0]) && $source->getText($parts[0]) === $name) {
                    return $sourceIndex;
                }
                $sourceIndex++;
            }
        }

        return null;
    }
}

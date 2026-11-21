<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\ClassInitializerProvider;
use Mago\Sdk\Analyzer\ClassInitializerProviderContext;
use Mago\Sdk\Analyzer\ClassTarget;

/**
 * Recognizes Symfony lifecycle initializers (AbstractController::setContainer,
 * ConstraintValidator::initialize, InputAwareHelper::setInput) and methods
 * marked with #[Symfony\Contracts\Service\Attribute\Required].
 */
final class RequiredInitializerProvider implements ClassInitializerProvider
{
    private const REQUIRED_ATTRIBUTE = 'Symfony\\Contracts\\Service\\Attribute\\Required';

    /** @var array<string, string> */
    private const CLASS_INITIALIZERS = [
        'symfony\\bundle\\frameworkbundle\\controller\\abstractcontroller' => 'setContainer',
        'symfony\\component\\validator\\constraintvalidator' => 'initialize',
        'symfony\\component\\console\\helper\\inputawarehelper' => 'setInput',
    ];

    public function getTargets(): array
    {
        return [
            ClassTarget::any(),
        ];
    }

    public function getClassInitializers(ClassInitializerProviderContext $context): array
    {
        if ($context->class->properties === []) {
            return [];
        }

        $initializers = [];
        $className = $context->class->name;

        $selfInitializer = self::CLASS_INITIALIZERS[strtolower($className)] ?? null;
        if ($selfInitializer !== null) {
            $initializers[] = $selfInitializer;
        }

        foreach ($context->class->parentClasses as $parent) {
            $parentInitializer = self::CLASS_INITIALIZERS[strtolower($parent)] ?? null;
            if ($parentInitializer !== null) {
                $initializers[] = $parentInitializer;
            }
        }

        $requiredMethods = $context->codebase->findMethods(
            class: $className,
            withAnyAttribute: [self::REQUIRED_ATTRIBUTE],
            fields: 0,
        );
        foreach ($requiredMethods as $method) {
            $initializers[] = $method->method->member;
        }

        return array_values(array_unique($initializers));
    }
}

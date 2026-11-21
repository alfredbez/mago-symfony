<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\ClassInitializerProvider;
use Mago\Sdk\Analyzer\ClassInitializerProviderContext;
use Mago\Sdk\Analyzer\ClassTarget;

/**
 * Recognizes Symfony AbstractController::setContainer() and methods marked with
 * #[Symfony\Contracts\Service\Attribute\Required] as class initializers.
 */
final class RequiredInitializerProvider implements ClassInitializerProvider
{
    private const ABSTRACT_CONTROLLER = 'Symfony\\Bundle\\FrameworkBundle\\Controller\\AbstractController';
    private const REQUIRED_ATTRIBUTE = 'Symfony\\Contracts\\Service\\Attribute\\Required';

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

        $isAbstractController = strtolower($className) === strtolower(self::ABSTRACT_CONTROLLER);
        if (!$isAbstractController) {
            foreach ($context->class->parentClasses as $parent) {
                if (strtolower($parent) === strtolower(self::ABSTRACT_CONTROLLER)) {
                    $isAbstractController = true;
                    break;
                }
            }
        }

        if ($isAbstractController) {
            $initializers[] = 'setContainer';
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

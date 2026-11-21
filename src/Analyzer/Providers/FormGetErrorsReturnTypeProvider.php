<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

/**
 * Narrows FormInterface::getErrors($deep = false, $flatten = true) to
 * FormErrorIterator<FormError> unless $deep can be true while $flatten can be false.
 */
final class FormGetErrorsReturnTypeProvider implements MethodReturnTypeProvider
{
    private const FORM_INTERFACE = 'Symfony\\Component\\Form\\FormInterface';
    private const FORM_ERROR_ITERATOR = 'Symfony\\Component\\Form\\FormErrorIterator';
    private const FORM_ERROR = 'Symfony\\Component\\Form\\FormError';

    private ?Type $flatIteratorType = null;
    private ?Type $nestedIteratorType = null;

    public function getTargets(): array
    {
        return [
            MethodTarget::exact(self::FORM_INTERFACE, 'getErrors'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $deepArg = $context->invocation->getArgument(0, 'deep');
        $flattenArg = $context->invocation->getArgument(1, 'flatten');

        $deepLiteral = $deepArg === null ? false : $deepArg->type?->getLiteralBool();
        $flattenLiteral = $flattenArg === null ? true : $flattenArg->type?->getLiteralBool();

        if ($deepLiteral === false || $flattenLiteral === true) {
            return $this->flatIteratorType ??= Type::namedObject(
                self::FORM_ERROR_ITERATOR,
                Type::namedObject(self::FORM_ERROR),
            );
        }

        return $this->nestedIteratorType ??= Type::namedObject(
            self::FORM_ERROR_ITERATOR,
            Type::union(
                Type::namedObject(self::FORM_ERROR),
                Type::namedObject(self::FORM_ERROR_ITERATOR),
            ),
        );
    }
}

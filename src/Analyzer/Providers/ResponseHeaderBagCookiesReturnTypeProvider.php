<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

/**
 * Narrows ResponseHeaderBag::getCookies($format) based on COOKIES_FLAT vs COOKIES_ARRAY.
 */
final class ResponseHeaderBagCookiesReturnTypeProvider implements MethodReturnTypeProvider
{
    private const RESPONSE_HEADER_BAG = 'Symfony\\Component\\HttpFoundation\\ResponseHeaderBag';
    private const COOKIE = 'Symfony\\Component\\HttpFoundation\\Cookie';

    private ?Type $flatType = null;
    private ?Type $arrayType = null;

    public function getTargets(): array
    {
        return [
            MethodTarget::exact(self::RESPONSE_HEADER_BAG, 'getCookies'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $formatArg = $context->invocation->getArgument(0, 'format');
        $format = $formatArg === null ? 'flat' : $formatArg->type?->getLiteralString();
        if ($format === 'flat') {
            return $this->flatType ??= Type::list(Type::namedObject(self::COOKIE));
        }

        if ($format === 'array') {
            return $this->arrayType ??= Type::array(
                Type::string(),
                Type::array(
                    Type::string(),
                    Type::array(
                        Type::string(),
                        Type::namedObject(self::COOKIE),
                    ),
                ),
            );
        }

        return null;
    }
}

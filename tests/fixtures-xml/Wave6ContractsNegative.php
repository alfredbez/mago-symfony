<?php

declare(strict_types=1);

namespace SymfonyFixtureXml;

use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\WrappedExceptionsInterface;

final class CustomNullableRequest extends Request
{
    /** @param string[]|null $locales */
    public function getPreferredLanguage(?array $locales = null): ?string
    {
        return null;
    }
}

final class CustomNullableParameterBag extends ParameterBag
{
    /**
     * @template T of \BackedEnum
     * @param class-string<T> $class
     * @param ?T $default
     * @return ?T
     */
    public function getEnum(string $key, string $class, ?\BackedEnum $default = null): ?\BackedEnum
    {
        return null;
    }
}

final class CustomWrappedExceptions extends \RuntimeException implements WrappedExceptionsInterface
{
    /** @return \Throwable[] */
    public function getWrappedExceptions(?string $class = null, bool $recursive = false): array
    {
        return [];
    }
}

final class Wave6ContractsNegative
{
    /** @param list<string> $maybeEmpty */
    public function nullablePreferredLanguages(Request $request, array $maybeEmpty): string
    {
        $a = $request->getPreferredLanguage();
        $b = $request->getPreferredLanguage([]);
        $c = $request->getPreferredLanguage($maybeEmpty);
        $d = $request->getPreferredLanguage(['a' => 'en', 'b' => 'de']);

        return $a . $b . $c . $d;
    }

    public function nullableEnums(ParameterBag $bag, InputBag $input, ?Wave6Status $maybeFallback): string
    {
        $a = $bag->getEnum('status', Wave6Status::class);
        $b = $input->getEnum('status', Wave6Status::class, $maybeFallback);

        return $a->value . $b->value;
    }

    public function attributeWrongMethod(ControllerEvent $event): void
    {
        foreach ($event->getAttributes(Wave6MarkerAttribute::class) as $attr) {
            $attr->missingAttributeMethod();
        }
    }

    public function wrappedExceptionWrongMethod(HandlerFailedException $failed): void
    {
        foreach ($failed->getWrappedExceptions(Wave6DomainException::class) as $ex) {
            $ex->missingDomainExceptionMethod();
        }
    }

    public function customOverrideDeclined(
        CustomNullableRequest $request,
        CustomNullableParameterBag $bag,
        CustomWrappedExceptions $wrapped,
    ): string {
        $lang = $request->getPreferredLanguage(['en', 'de']);
        $enum = $bag->getEnum('status', Wave6Status::class, Wave6Status::Draft);
        foreach ($wrapped->getWrappedExceptions(Wave6DomainException::class) as $ex) {
            $ex->domainCode();
        }

        return $lang . $enum->value;
    }

    public function nonThrowableMarkerKeepsThrowable(HandlerFailedException $failed): int
    {
        $total = 0;
        foreach ($failed->getWrappedExceptions(Wave6MarkerOnlyInterface::class) as $ex) {
            $total += strlen($ex->getMessage());
        }

        return $total;
    }
}

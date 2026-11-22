<?php

declare(strict_types=1);

namespace SymfonyFixtureXml;

use Attribute;
use RuntimeException;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\WrappedExceptionsInterface;

enum Wave6Status: string
{
    case Draft = 'draft';
    case Published = 'published';
}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Wave6MarkerAttribute
{
    public function __construct(public readonly string $tag) {}

    public function describe(): string
    {
        return $this->tag;
    }
}

final class Wave6DomainException extends RuntimeException
{
    public function domainCode(): int
    {
        return 422;
    }
}

interface Wave6MarkerOnlyInterface
{
}

interface Wave6ThrowableMarkerInterface extends \Throwable
{
    public function markerTag(): string;
}

final class Wave6Contracts
{
    /** @param non-empty-list<string> $dynamicLocales */
    public function preferredLanguages(Request $request, array $dynamicLocales): string
    {
        $a = $request->getPreferredLanguage(['en', 'de']);
        $b = $request->getPreferredLanguage(locales: ['fr']);
        $c = $request->getPreferredLanguage($dynamicLocales);

        return $a . ':' . $b . ':' . $c;
    }

    public function bagEnums(ParameterBag $bag, InputBag $input, Wave6Status $fallback): string
    {
        $a = $bag->getEnum('status', Wave6Status::class, Wave6Status::Draft);
        $b = $input->getEnum('status', Wave6Status::class, Wave6Status::Published);
        $c = $bag->getEnum('status', Wave6Status::class, $fallback);

        return $a->value . ':' . $b->value . ':' . $c->value;
    }

    public function controllerAttributes(ControllerEvent $event, ControllerArgumentsEvent $argsEvent): string
    {
        $tags = [];
        foreach ($event->getAttributes(Wave6MarkerAttribute::class) as $attr) {
            $tags[] = $attr->describe();
        }
        foreach ($argsEvent->getAttributes(className: Wave6MarkerAttribute::class) as $attr) {
            $tags[] = $attr->describe();
        }

        return implode(',', $tags);
    }

    public function wrappedExceptions(HandlerFailedException $failed, WrappedExceptionsInterface $wrapped): int
    {
        $sum = 0;
        foreach ($failed->getWrappedExceptions(Wave6DomainException::class) as $ex) {
            $sum += $ex->domainCode();
        }
        foreach ($wrapped->getWrappedExceptions(class: Wave6DomainException::class) as $ex) {
            $sum += $ex->domainCode();
        }
        foreach ($failed->getWrappedExceptions(Wave6ThrowableMarkerInterface::class) as $ex) {
            $sum += strlen($ex->markerTag()) + strlen($ex->getMessage());
        }

        return $sum;
    }
}

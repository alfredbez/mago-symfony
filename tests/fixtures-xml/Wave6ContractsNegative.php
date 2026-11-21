<?php

declare(strict_types=1);

namespace SymfonyFixtureXml;

use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

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
}

<?php

declare(strict_types=1);

namespace SymfonyFixtureXml;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Service\Attribute\Required;
use SymfonyFixture\ConcreteGreeter;
use SymfonyFixture\Mailer;

final class UninitializedServiceNegative
{
    private ConcreteGreeter $initializedByRequired;
    private Mailer $forgottenProperty;

    #[Required]
    public function autowireGreeter(ConcreteGreeter $greeter): void
    {
        $this->initializedByRequired = $greeter;
    }

    public function getGreeter(): ConcreteGreeter
    {
        return $this->initializedByRequired;
    }

    public function getForgotten(): Mailer
    {
        return $this->forgottenProperty;
    }
}

final class Wave4ContractsNegative
{
    public function invalidHeaderDefault(HeaderBag $headers): string
    {
        $nullableHeader = $headers->get('X-Missing');

        return $nullableHeader;
    }

    public function invalidUnflattenedDeepFormErrors(FormInterface $form): string
    {
        foreach ($form->getErrors(true, false) as $entry) {
            return $entry->getMessage();
        }

        return '';
    }

    public function invalidDeserializedMethod(
        SerializerInterface $serializer,
        DenormalizerInterface $denormalizer,
    ): void {
        $event = $serializer->deserialize('{}', DomainEvent::class, 'json');
        $event->nonExistentMethodOnDomainEvent();

        $denormList = $denormalizer->denormalize([], DomainEvent::class . '[]');
        foreach ($denormList as $item) {
            $item->anotherMissingMethod();
        }
    }
}

<?php

declare(strict_types=1);

namespace SymfonyFixtureXml;

use Psr\Container\ContainerInterface as PsrContainer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class ContainerXmlNegative extends AbstractController
{
    public function invalidXmlServices(PsrContainer $psr, ContainerInterface $sf, Container $concrete): void
    {
        $psr->get('xml.factory.class')->send('wrong@example.com');
        $psr->get('xml.factory.service')->implementationOnly();
        $psr->get('xml.alias.chain')->send('wrong@example.com');
        $psr->get('xml.cycle.a')->implementationOnly();
        $sf->get('xml.private.greeter')->implementationOnly();
        $concrete->get('xml.alias.chain')->implementationOnly();
        $sf->get('xml.unknown.service', ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $sf->get('xml.unknown.service');
    }

    public function invalidXmlParameters(ParameterBagInterface $bag): int
    {
        $retries = $bag->get('app.retries');
        $name = $bag->get('app.name');
        $nullable = $this->getParameter('app.nullable');
        $unknown = $bag->get('app.does_not_exist');

        return strlen($retries) + intdiv($name, 2) + strlen($nullable) + strlen($unknown);
    }
}

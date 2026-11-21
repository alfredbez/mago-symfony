<?php

declare(strict_types=1);

namespace SymfonyFixtureXml;

use Psr\Container\ContainerInterface as PsrContainer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use SymfonyFixture\ConcreteGreeter;
use SymfonyFixture\Mailer;

final class ContainerXmlUsage extends AbstractController
{
    public function xmlServices(PsrContainer $psr, ContainerInterface $sf, Container $concrete): void
    {
        $psr->get('xml.public.greeter')->implementationOnly();
        $sf->get('xml.private.greeter')->implementationOnly();
        $concrete->get('xml.synthetic.mailer')->send('ops@example.com');
        $psr->get('xml.alias.chain')->implementationOnly();
        $psr->get('xml.factory.class')->implementationOnly();
        $psr->get('xml.factory.subtype')->implementationOnly();
        $psr->get('xml.factory.service')->send('factory@example.com');
    }

    public function returnsTypedServices(PsrContainer $psr): ConcreteGreeter
    {
        return $psr->get('xml.factory.class');
    }

    public function returnsServiceFactory(ContainerInterface $sf): Mailer
    {
        return $sf->get('xml.factory.service');
    }

    public function xmlParameters(ParameterBagInterface $bag, ContainerInterface $sf, Container $concrete): int
    {
        $name = $bag->get('app.name');
        $numericStr = $bag->get('app.numeric_str');
        $debug = $sf->getParameter('app.debug');
        $retries = $concrete->getParameter('app.retries');
        $ratio = $this->getParameter('app.ratio');
        $nullable = $bag->get('app.nullable');
        $list = $bag->get('app.list');
        $map = $bag->get('app.map');
        $nested = $bag->get('app.nested');
        $apiVersion = $bag->get('app.api_version');
        $binary = $bag->get('app.binary');
        $envStr = $bag->get('app.env_str');
        $envInt = $bag->get('app.env_int');

        $len = strlen($name)
            + strlen($numericStr)
            + strlen($list[0])
            + strlen($map['host'])
            + strlen($binary)
            + strlen($envStr);

        $sum = $retries + $map['port'] + $nested['items'][0] + $apiVersion + $envInt;

        return $this->consumeScalars($debug, $nullable, $ratio, $nested['meta']['ok'], $len + $sum);
    }

    private function consumeScalars(bool $debug, null $nullable, float $ratio, bool $nestedOk, int $total): int
    {
        return $debug && $ratio > 1.0 && !$nestedOk ? $total : (int) $nullable;
    }
}

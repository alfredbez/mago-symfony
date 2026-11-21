<?php

declare(strict_types=1);

namespace SymfonyBench;

use Psr\Container\ContainerInterface as PsrContainer;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface as SfContainerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Contracts\Service\ServiceProviderInterface;

final class ContainerWorkload0
{
    /** @param 0|1 $builderBehavior */
    public function scenario0(
        PsrContainer $psr,
        SfContainerInterface $contract,
        Container $container,
        ContainerBuilder $builder,
        ServiceLocator $locator,
        ServiceProviderInterface $provider,
        int $builderBehavior,
    ): int {
        $a = $psr->get(BenchService00::class);
        $b = $container->get(BenchService03::class);
        $mapped = $contract->get('app.service.000');
        $numeric = $locator->get('100');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.000', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.000', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService00::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService00::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.000', Container::NULL_ON_INVALID_REFERENCE);
        $nullableIgnore = $container->get(BenchService03::class, Container::IGNORE_ON_INVALID_REFERENCE);
        $nullableUninit = $container->get('app.mailer', Container::IGNORE_ON_UNINITIALIZED_REFERENCE);

        $logger->info($a->handle00('ok'));
        $mailer->send('dev@example.com', $b->handle03('ok'));
        $override->queuedCount();
        $named->send('ops@example.com', 'alert');

        return $a->count()
            + $b->count()
            + $mapped->count()
            + $numeric->count()
            + $thrown->count()
            + $builderRuntime->count()
            + $builderUnion->count()
            + ($nullableRuntime?->count() ?? 0)
            + ($nullableNull?->count() ?? 0)
            + ($nullableIgnore?->count() ?? 0)
            + ($nullableUninit?->queuedCount() ?? 0)
            + 0
            + 4;
    }

    /** @param 0|1 $builderBehavior */
    public function scenario1(
        PsrContainer $psr,
        SfContainerInterface $contract,
        Container $container,
        ContainerBuilder $builder,
        ServiceLocator $locator,
        ServiceProviderInterface $provider,
        int $builderBehavior,
    ): int {
        $a = $psr->get(BenchService01::class);
        $b = $container->get(BenchService04::class);
        $mapped = $contract->get('app.service.001');
        $numeric = $locator->get('101');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.001', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.001', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService01::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService01::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.001', Container::NULL_ON_INVALID_REFERENCE);
        $nullableIgnore = $container->get(BenchService04::class, Container::IGNORE_ON_INVALID_REFERENCE);
        $nullableUninit = $container->get('app.mailer', Container::IGNORE_ON_UNINITIALIZED_REFERENCE);

        $logger->info($a->handle01('ok'));
        $mailer->send('dev@example.com', $b->handle04('ok'));
        $override->queuedCount();
        $named->send('ops@example.com', 'alert');

        return $a->count()
            + $b->count()
            + $mapped->count()
            + $numeric->count()
            + $thrown->count()
            + $builderRuntime->count()
            + $builderUnion->count()
            + ($nullableRuntime?->count() ?? 0)
            + ($nullableNull?->count() ?? 0)
            + ($nullableIgnore?->count() ?? 0)
            + ($nullableUninit?->queuedCount() ?? 0)
            + 1
            + 5;
    }

    /** @param 0|1 $builderBehavior */
    public function scenario2(
        PsrContainer $psr,
        SfContainerInterface $contract,
        Container $container,
        ContainerBuilder $builder,
        ServiceLocator $locator,
        ServiceProviderInterface $provider,
        int $builderBehavior,
    ): int {
        $a = $psr->get(BenchService02::class);
        $b = $container->get(BenchService05::class);
        $mapped = $contract->get('app.service.002');
        $numeric = $locator->get('102');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.002', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.002', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService02::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService02::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.002', Container::NULL_ON_INVALID_REFERENCE);
        $nullableIgnore = $container->get(BenchService05::class, Container::IGNORE_ON_INVALID_REFERENCE);
        $nullableUninit = $container->get('app.mailer', Container::IGNORE_ON_UNINITIALIZED_REFERENCE);

        $logger->info($a->handle02('ok'));
        $mailer->send('dev@example.com', $b->handle05('ok'));
        $override->queuedCount();
        $named->send('ops@example.com', 'alert');

        return $a->count()
            + $b->count()
            + $mapped->count()
            + $numeric->count()
            + $thrown->count()
            + $builderRuntime->count()
            + $builderUnion->count()
            + ($nullableRuntime?->count() ?? 0)
            + ($nullableNull?->count() ?? 0)
            + ($nullableIgnore?->count() ?? 0)
            + ($nullableUninit?->queuedCount() ?? 0)
            + 2
            + 6;
    }

    /** @param 0|1 $builderBehavior */
    public function scenario3(
        PsrContainer $psr,
        SfContainerInterface $contract,
        Container $container,
        ContainerBuilder $builder,
        ServiceLocator $locator,
        ServiceProviderInterface $provider,
        int $builderBehavior,
    ): int {
        $a = $psr->get(BenchService03::class);
        $b = $container->get(BenchService06::class);
        $mapped = $contract->get('app.service.003');
        $numeric = $locator->get('103');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.003', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.003', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService03::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService03::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.003', Container::NULL_ON_INVALID_REFERENCE);
        $nullableIgnore = $container->get(BenchService06::class, Container::IGNORE_ON_INVALID_REFERENCE);
        $nullableUninit = $container->get('app.mailer', Container::IGNORE_ON_UNINITIALIZED_REFERENCE);

        $logger->info($a->handle03('ok'));
        $mailer->send('dev@example.com', $b->handle06('ok'));
        $override->queuedCount();
        $named->send('ops@example.com', 'alert');

        return $a->count()
            + $b->count()
            + $mapped->count()
            + $numeric->count()
            + $thrown->count()
            + $builderRuntime->count()
            + $builderUnion->count()
            + ($nullableRuntime?->count() ?? 0)
            + ($nullableNull?->count() ?? 0)
            + ($nullableIgnore?->count() ?? 0)
            + ($nullableUninit?->queuedCount() ?? 0)
            + 3
            + 7;
    }

    /** @param 0|1 $builderBehavior */
    public function scenario4(
        PsrContainer $psr,
        SfContainerInterface $contract,
        Container $container,
        ContainerBuilder $builder,
        ServiceLocator $locator,
        ServiceProviderInterface $provider,
        int $builderBehavior,
    ): int {
        $a = $psr->get(BenchService04::class);
        $b = $container->get(BenchService07::class);
        $mapped = $contract->get('app.service.004');
        $numeric = $locator->get('104');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.004', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.004', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService04::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService04::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.004', Container::NULL_ON_INVALID_REFERENCE);
        $nullableIgnore = $container->get(BenchService07::class, Container::IGNORE_ON_INVALID_REFERENCE);
        $nullableUninit = $container->get('app.mailer', Container::IGNORE_ON_UNINITIALIZED_REFERENCE);

        $logger->info($a->handle04('ok'));
        $mailer->send('dev@example.com', $b->handle07('ok'));
        $override->queuedCount();
        $named->send('ops@example.com', 'alert');

        return $a->count()
            + $b->count()
            + $mapped->count()
            + $numeric->count()
            + $thrown->count()
            + $builderRuntime->count()
            + $builderUnion->count()
            + ($nullableRuntime?->count() ?? 0)
            + ($nullableNull?->count() ?? 0)
            + ($nullableIgnore?->count() ?? 0)
            + ($nullableUninit?->queuedCount() ?? 0)
            + 4
            + 8;
    }

    /** @param 0|1 $builderBehavior */
    public function scenario5(
        PsrContainer $psr,
        SfContainerInterface $contract,
        Container $container,
        ContainerBuilder $builder,
        ServiceLocator $locator,
        ServiceProviderInterface $provider,
        int $builderBehavior,
    ): int {
        $a = $psr->get(BenchService05::class);
        $b = $container->get(BenchService08::class);
        $mapped = $contract->get('app.service.005');
        $numeric = $locator->get('105');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.005', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.005', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService05::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService05::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.005', Container::NULL_ON_INVALID_REFERENCE);
        $nullableIgnore = $container->get(BenchService08::class, Container::IGNORE_ON_INVALID_REFERENCE);
        $nullableUninit = $container->get('app.mailer', Container::IGNORE_ON_UNINITIALIZED_REFERENCE);

        $logger->info($a->handle05('ok'));
        $mailer->send('dev@example.com', $b->handle08('ok'));
        $override->queuedCount();
        $named->send('ops@example.com', 'alert');

        return $a->count()
            + $b->count()
            + $mapped->count()
            + $numeric->count()
            + $thrown->count()
            + $builderRuntime->count()
            + $builderUnion->count()
            + ($nullableRuntime?->count() ?? 0)
            + ($nullableNull?->count() ?? 0)
            + ($nullableIgnore?->count() ?? 0)
            + ($nullableUninit?->queuedCount() ?? 0)
            + 5
            + 9;
    }

    /** @param 0|1 $builderBehavior */
    public function scenario6(
        PsrContainer $psr,
        SfContainerInterface $contract,
        Container $container,
        ContainerBuilder $builder,
        ServiceLocator $locator,
        ServiceProviderInterface $provider,
        int $builderBehavior,
    ): int {
        $a = $psr->get(BenchService06::class);
        $b = $container->get(BenchService09::class);
        $mapped = $contract->get('app.service.006');
        $numeric = $locator->get('106');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.006', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.006', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService06::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService06::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.006', Container::NULL_ON_INVALID_REFERENCE);
        $nullableIgnore = $container->get(BenchService09::class, Container::IGNORE_ON_INVALID_REFERENCE);
        $nullableUninit = $container->get('app.mailer', Container::IGNORE_ON_UNINITIALIZED_REFERENCE);

        $logger->info($a->handle06('ok'));
        $mailer->send('dev@example.com', $b->handle09('ok'));
        $override->queuedCount();
        $named->send('ops@example.com', 'alert');

        return $a->count()
            + $b->count()
            + $mapped->count()
            + $numeric->count()
            + $thrown->count()
            + $builderRuntime->count()
            + $builderUnion->count()
            + ($nullableRuntime?->count() ?? 0)
            + ($nullableNull?->count() ?? 0)
            + ($nullableIgnore?->count() ?? 0)
            + ($nullableUninit?->queuedCount() ?? 0)
            + 6
            + 10;
    }

    /** @param 0|1 $builderBehavior */
    public function scenario7(
        PsrContainer $psr,
        SfContainerInterface $contract,
        Container $container,
        ContainerBuilder $builder,
        ServiceLocator $locator,
        ServiceProviderInterface $provider,
        int $builderBehavior,
    ): int {
        $a = $psr->get(BenchService07::class);
        $b = $container->get(BenchService10::class);
        $mapped = $contract->get('app.service.007');
        $numeric = $locator->get('107');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.007', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.007', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService07::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService07::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.007', Container::NULL_ON_INVALID_REFERENCE);
        $nullableIgnore = $container->get(BenchService10::class, Container::IGNORE_ON_INVALID_REFERENCE);
        $nullableUninit = $container->get('app.mailer', Container::IGNORE_ON_UNINITIALIZED_REFERENCE);

        $logger->info($a->handle07('ok'));
        $mailer->send('dev@example.com', $b->handle10('ok'));
        $override->queuedCount();
        $named->send('ops@example.com', 'alert');

        return $a->count()
            + $b->count()
            + $mapped->count()
            + $numeric->count()
            + $thrown->count()
            + $builderRuntime->count()
            + $builderUnion->count()
            + ($nullableRuntime?->count() ?? 0)
            + ($nullableNull?->count() ?? 0)
            + ($nullableIgnore?->count() ?? 0)
            + ($nullableUninit?->queuedCount() ?? 0)
            + 7
            + 11;
    }

    /** @param 0|1 $builderBehavior */
    public function scenario8(
        PsrContainer $psr,
        SfContainerInterface $contract,
        Container $container,
        ContainerBuilder $builder,
        ServiceLocator $locator,
        ServiceProviderInterface $provider,
        int $builderBehavior,
    ): int {
        $a = $psr->get(BenchService08::class);
        $b = $container->get(BenchService11::class);
        $mapped = $contract->get('app.service.008');
        $numeric = $locator->get('108');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.008', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.008', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService08::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService08::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.008', Container::NULL_ON_INVALID_REFERENCE);
        $nullableIgnore = $container->get(BenchService11::class, Container::IGNORE_ON_INVALID_REFERENCE);
        $nullableUninit = $container->get('app.mailer', Container::IGNORE_ON_UNINITIALIZED_REFERENCE);

        $logger->info($a->handle08('ok'));
        $mailer->send('dev@example.com', $b->handle11('ok'));
        $override->queuedCount();
        $named->send('ops@example.com', 'alert');

        return $a->count()
            + $b->count()
            + $mapped->count()
            + $numeric->count()
            + $thrown->count()
            + $builderRuntime->count()
            + $builderUnion->count()
            + ($nullableRuntime?->count() ?? 0)
            + ($nullableNull?->count() ?? 0)
            + ($nullableIgnore?->count() ?? 0)
            + ($nullableUninit?->queuedCount() ?? 0)
            + 8
            + 12;
    }

    /** @param 0|1 $builderBehavior */
    public function scenario9(
        PsrContainer $psr,
        SfContainerInterface $contract,
        Container $container,
        ContainerBuilder $builder,
        ServiceLocator $locator,
        ServiceProviderInterface $provider,
        int $builderBehavior,
    ): int {
        $a = $psr->get(BenchService09::class);
        $b = $container->get(BenchService12::class);
        $mapped = $contract->get('app.service.009');
        $numeric = $locator->get('109');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.009', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.009', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService09::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService09::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.009', Container::NULL_ON_INVALID_REFERENCE);
        $nullableIgnore = $container->get(BenchService12::class, Container::IGNORE_ON_INVALID_REFERENCE);
        $nullableUninit = $container->get('app.mailer', Container::IGNORE_ON_UNINITIALIZED_REFERENCE);

        $logger->info($a->handle09('ok'));
        $mailer->send('dev@example.com', $b->handle12('ok'));
        $override->queuedCount();
        $named->send('ops@example.com', 'alert');

        return $a->count()
            + $b->count()
            + $mapped->count()
            + $numeric->count()
            + $thrown->count()
            + $builderRuntime->count()
            + $builderUnion->count()
            + ($nullableRuntime?->count() ?? 0)
            + ($nullableNull?->count() ?? 0)
            + ($nullableIgnore?->count() ?? 0)
            + ($nullableUninit?->queuedCount() ?? 0)
            + 9
            + 13;
    }

    /** @param 0|1 $builderBehavior */
    public function scenario10(
        PsrContainer $psr,
        SfContainerInterface $contract,
        Container $container,
        ContainerBuilder $builder,
        ServiceLocator $locator,
        ServiceProviderInterface $provider,
        int $builderBehavior,
    ): int {
        $a = $psr->get(BenchService10::class);
        $b = $container->get(BenchService13::class);
        $mapped = $contract->get('app.service.010');
        $numeric = $locator->get('110');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.010', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.010', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService10::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService10::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.010', Container::NULL_ON_INVALID_REFERENCE);
        $nullableIgnore = $container->get(BenchService13::class, Container::IGNORE_ON_INVALID_REFERENCE);
        $nullableUninit = $container->get('app.mailer', Container::IGNORE_ON_UNINITIALIZED_REFERENCE);

        $logger->info($a->handle10('ok'));
        $mailer->send('dev@example.com', $b->handle13('ok'));
        $override->queuedCount();
        $named->send('ops@example.com', 'alert');

        return $a->count()
            + $b->count()
            + $mapped->count()
            + $numeric->count()
            + $thrown->count()
            + $builderRuntime->count()
            + $builderUnion->count()
            + ($nullableRuntime?->count() ?? 0)
            + ($nullableNull?->count() ?? 0)
            + ($nullableIgnore?->count() ?? 0)
            + ($nullableUninit?->queuedCount() ?? 0)
            + 10
            + 14;
    }

    /** @param 0|1 $builderBehavior */
    public function scenario11(
        PsrContainer $psr,
        SfContainerInterface $contract,
        Container $container,
        ContainerBuilder $builder,
        ServiceLocator $locator,
        ServiceProviderInterface $provider,
        int $builderBehavior,
    ): int {
        $a = $psr->get(BenchService11::class);
        $b = $container->get(BenchService14::class);
        $mapped = $contract->get('app.service.011');
        $numeric = $locator->get('111');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.011', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.011', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService11::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService11::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.011', Container::NULL_ON_INVALID_REFERENCE);
        $nullableIgnore = $container->get(BenchService14::class, Container::IGNORE_ON_INVALID_REFERENCE);
        $nullableUninit = $container->get('app.mailer', Container::IGNORE_ON_UNINITIALIZED_REFERENCE);

        $logger->info($a->handle11('ok'));
        $mailer->send('dev@example.com', $b->handle14('ok'));
        $override->queuedCount();
        $named->send('ops@example.com', 'alert');

        return $a->count()
            + $b->count()
            + $mapped->count()
            + $numeric->count()
            + $thrown->count()
            + $builderRuntime->count()
            + $builderUnion->count()
            + ($nullableRuntime?->count() ?? 0)
            + ($nullableNull?->count() ?? 0)
            + ($nullableIgnore?->count() ?? 0)
            + ($nullableUninit?->queuedCount() ?? 0)
            + 11
            + 15;
    }

    /** @param 0|1 $builderBehavior */
    public function scenario12(
        PsrContainer $psr,
        SfContainerInterface $contract,
        Container $container,
        ContainerBuilder $builder,
        ServiceLocator $locator,
        ServiceProviderInterface $provider,
        int $builderBehavior,
    ): int {
        $a = $psr->get(BenchService12::class);
        $b = $container->get(BenchService15::class);
        $mapped = $contract->get('app.service.012');
        $numeric = $locator->get('112');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.012', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.012', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService12::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService12::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.012', Container::NULL_ON_INVALID_REFERENCE);
        $nullableIgnore = $container->get(BenchService15::class, Container::IGNORE_ON_INVALID_REFERENCE);
        $nullableUninit = $container->get('app.mailer', Container::IGNORE_ON_UNINITIALIZED_REFERENCE);

        $logger->info($a->handle12('ok'));
        $mailer->send('dev@example.com', $b->handle15('ok'));
        $override->queuedCount();
        $named->send('ops@example.com', 'alert');

        return $a->count()
            + $b->count()
            + $mapped->count()
            + $numeric->count()
            + $thrown->count()
            + $builderRuntime->count()
            + $builderUnion->count()
            + ($nullableRuntime?->count() ?? 0)
            + ($nullableNull?->count() ?? 0)
            + ($nullableIgnore?->count() ?? 0)
            + ($nullableUninit?->queuedCount() ?? 0)
            + 12
            + 0;
    }

    /** @param 0|1 $builderBehavior */
    public function scenario13(
        PsrContainer $psr,
        SfContainerInterface $contract,
        Container $container,
        ContainerBuilder $builder,
        ServiceLocator $locator,
        ServiceProviderInterface $provider,
        int $builderBehavior,
    ): int {
        $a = $psr->get(BenchService13::class);
        $b = $container->get(BenchService00::class);
        $mapped = $contract->get('app.service.013');
        $numeric = $locator->get('113');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.013', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.013', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService13::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService13::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.013', Container::NULL_ON_INVALID_REFERENCE);
        $nullableIgnore = $container->get(BenchService00::class, Container::IGNORE_ON_INVALID_REFERENCE);
        $nullableUninit = $container->get('app.mailer', Container::IGNORE_ON_UNINITIALIZED_REFERENCE);

        $logger->info($a->handle13('ok'));
        $mailer->send('dev@example.com', $b->handle00('ok'));
        $override->queuedCount();
        $named->send('ops@example.com', 'alert');

        return $a->count()
            + $b->count()
            + $mapped->count()
            + $numeric->count()
            + $thrown->count()
            + $builderRuntime->count()
            + $builderUnion->count()
            + ($nullableRuntime?->count() ?? 0)
            + ($nullableNull?->count() ?? 0)
            + ($nullableIgnore?->count() ?? 0)
            + ($nullableUninit?->queuedCount() ?? 0)
            + 13
            + 1;
    }

    /** @param 0|1 $builderBehavior */
    public function scenario14(
        PsrContainer $psr,
        SfContainerInterface $contract,
        Container $container,
        ContainerBuilder $builder,
        ServiceLocator $locator,
        ServiceProviderInterface $provider,
        int $builderBehavior,
    ): int {
        $a = $psr->get(BenchService14::class);
        $b = $container->get(BenchService01::class);
        $mapped = $contract->get('app.service.014');
        $numeric = $locator->get('114');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.014', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.014', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService14::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService14::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.014', Container::NULL_ON_INVALID_REFERENCE);
        $nullableIgnore = $container->get(BenchService01::class, Container::IGNORE_ON_INVALID_REFERENCE);
        $nullableUninit = $container->get('app.mailer', Container::IGNORE_ON_UNINITIALIZED_REFERENCE);

        $logger->info($a->handle14('ok'));
        $mailer->send('dev@example.com', $b->handle01('ok'));
        $override->queuedCount();
        $named->send('ops@example.com', 'alert');

        return $a->count()
            + $b->count()
            + $mapped->count()
            + $numeric->count()
            + $thrown->count()
            + $builderRuntime->count()
            + $builderUnion->count()
            + ($nullableRuntime?->count() ?? 0)
            + ($nullableNull?->count() ?? 0)
            + ($nullableIgnore?->count() ?? 0)
            + ($nullableUninit?->queuedCount() ?? 0)
            + 14
            + 2;
    }

    /** @param 0|1 $builderBehavior */
    public function scenario15(
        PsrContainer $psr,
        SfContainerInterface $contract,
        Container $container,
        ContainerBuilder $builder,
        ServiceLocator $locator,
        ServiceProviderInterface $provider,
        int $builderBehavior,
    ): int {
        $a = $psr->get(BenchService15::class);
        $b = $container->get(BenchService02::class);
        $mapped = $contract->get('app.service.015');
        $numeric = $locator->get('115');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.015', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.015', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService15::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService15::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.015', Container::NULL_ON_INVALID_REFERENCE);
        $nullableIgnore = $container->get(BenchService02::class, Container::IGNORE_ON_INVALID_REFERENCE);
        $nullableUninit = $container->get('app.mailer', Container::IGNORE_ON_UNINITIALIZED_REFERENCE);

        $logger->info($a->handle15('ok'));
        $mailer->send('dev@example.com', $b->handle02('ok'));
        $override->queuedCount();
        $named->send('ops@example.com', 'alert');

        return $a->count()
            + $b->count()
            + $mapped->count()
            + $numeric->count()
            + $thrown->count()
            + $builderRuntime->count()
            + $builderUnion->count()
            + ($nullableRuntime?->count() ?? 0)
            + ($nullableNull?->count() ?? 0)
            + ($nullableIgnore?->count() ?? 0)
            + ($nullableUninit?->queuedCount() ?? 0)
            + 15
            + 3;
    }
}

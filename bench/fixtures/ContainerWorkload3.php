<?php

declare(strict_types=1);

namespace SymfonyBench;

use Psr\Container\ContainerInterface as PsrContainer;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface as SfContainerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Contracts\Service\ServiceProviderInterface;

final class ContainerWorkload3
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
        $mapped = $contract->get('app.service.048');
        $numeric = $locator->get('108');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.048', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.048', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService00::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService00::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.048', Container::NULL_ON_INVALID_REFERENCE);
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
            + 12;
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
        $mapped = $contract->get('app.service.049');
        $numeric = $locator->get('109');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.049', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.049', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService01::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService01::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.049', Container::NULL_ON_INVALID_REFERENCE);
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
            + 13;
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
        $mapped = $contract->get('app.service.050');
        $numeric = $locator->get('110');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.050', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.050', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService02::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService02::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.050', Container::NULL_ON_INVALID_REFERENCE);
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
            + 14;
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
        $mapped = $contract->get('app.service.051');
        $numeric = $locator->get('111');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.051', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.051', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService03::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService03::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.051', Container::NULL_ON_INVALID_REFERENCE);
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
            + 15;
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
        $mapped = $contract->get('app.service.052');
        $numeric = $locator->get('112');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.052', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.052', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService04::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService04::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.052', Container::NULL_ON_INVALID_REFERENCE);
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
            + 0;
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
        $mapped = $contract->get('app.service.053');
        $numeric = $locator->get('113');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.053', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.053', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService05::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService05::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.053', Container::NULL_ON_INVALID_REFERENCE);
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
            + 1;
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
        $mapped = $contract->get('app.service.054');
        $numeric = $locator->get('114');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.054', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.054', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService06::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService06::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.054', Container::NULL_ON_INVALID_REFERENCE);
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
            + 2;
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
        $mapped = $contract->get('app.service.055');
        $numeric = $locator->get('115');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.055', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.055', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService07::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService07::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.055', Container::NULL_ON_INVALID_REFERENCE);
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
            + 3;
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
        $mapped = $contract->get('app.service.056');
        $numeric = $locator->get('116');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.056', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.056', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService08::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService08::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.056', Container::NULL_ON_INVALID_REFERENCE);
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
            + 4;
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
        $mapped = $contract->get('app.service.057');
        $numeric = $locator->get('117');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.057', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.057', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService09::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService09::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.057', Container::NULL_ON_INVALID_REFERENCE);
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
            + 5;
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
        $mapped = $contract->get('app.service.058');
        $numeric = $locator->get('118');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.058', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.058', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService10::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService10::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.058', Container::NULL_ON_INVALID_REFERENCE);
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
            + 6;
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
        $mapped = $contract->get('app.service.059');
        $numeric = $locator->get('119');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.059', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.059', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService11::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService11::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.059', Container::NULL_ON_INVALID_REFERENCE);
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
            + 7;
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
        $mapped = $contract->get('app.service.060');
        $numeric = $locator->get('120');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.060', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.060', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService12::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService12::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.060', Container::NULL_ON_INVALID_REFERENCE);
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
            + 8;
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
        $mapped = $contract->get('app.service.061');
        $numeric = $locator->get('121');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.061', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.061', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService13::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService13::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.061', Container::NULL_ON_INVALID_REFERENCE);
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
            + 9;
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
        $mapped = $contract->get('app.service.062');
        $numeric = $locator->get('122');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.062', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.062', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService14::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService14::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.062', Container::NULL_ON_INVALID_REFERENCE);
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
            + 10;
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
        $mapped = $contract->get('app.service.063');
        $numeric = $locator->get('123');
        $mailer = $provider->get('app.mailer');
        $override = $psr->get(MailerInterface::class);
        $logger = $contract->get('app.logger', SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE);

        $thrown = $container->get('app.service.063', Container::EXCEPTION_ON_INVALID_REFERENCE);
        $named = $contract->get(invalidBehavior: SfContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, id: 'app.mailer');
        $builderRuntime = $builder->get('app.service.063', SfContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $builderUnion = $builder->get(BenchService15::class, $builderBehavior);

        $nullableRuntime = $container->get(BenchService15::class, Container::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE);
        $nullableNull = $container->get('app.service.063', Container::NULL_ON_INVALID_REFERENCE);
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
            + 11;
    }
}

<?php

declare(strict_types=1);

namespace SymfonyFixtureXml;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Service\Attribute\Required;
use SymfonyFixture\ConcreteGreeter;
use SymfonyFixture\Mailer;

final class DomainEvent
{
    public function __construct(public readonly string $name) {}
}

final class RequiredAutowiredService
{
    private ConcreteGreeter $greeter;
    private Mailer $mailer;

    #[Required]
    public function autowireDependencies(ConcreteGreeter $greeter, Mailer $mailer): void
    {
        $this->greeter = $greeter;
        $this->mailer = $mailer;
    }

    public function run(): string
    {
        $this->mailer->send('team@example.com');

        return $this->greeter->implementationOnly();
    }
}

class ValidatedCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED)
            ->addOption('dry-run', null, InputOption::VALUE_NONE)
            ->addOption('--bypass-locking', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $input->getArgument('username') !== null
            && $input->getOption('dry-run') !== null
            && $input->getOption('bypass-locking') !== null
            && $input->getOption('verbose') !== null
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}

final class SetDefinitionCommand extends Command
{
    protected function configure(): void
    {
        $this->setDefinition([
            new InputArgument('target', InputArgument::REQUIRED),
            new InputOption('--force', 'f', InputOption::VALUE_NONE),
        ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $input->getArgument('target') !== null
            && $input->getOption('force') !== null
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}

final class InheritedCommand extends ValidatedCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('extra', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $input->getArgument('username') !== null
            && $input->getOption('extra') !== null
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}

final class ConstructorConfiguredCommand extends Command
{
    public function __construct()
    {
        parent::__construct('demo');
        $this->addArgument('id', InputArgument::REQUIRED);
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $input->getArgument('id') !== null
            && $input->getOption('dry-run') !== null
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}

final class SymfonyComponentsController extends AbstractController
{
    public function exercise(
        ContainerBuilder $builder,
        EventDispatcherInterface $dispatcher,
        MessageBusInterface $bus,
        Request $request,
        HeaderBag $headers,
    ): RedirectResponse {
        $builder->getDefinition('app.service')->setPublic(true);
        $builder->findDefinition('app.service')->setPublic(false);

        $dispatched = $dispatcher->dispatch(new DomainEvent('created'));
        $envelope = $bus->dispatch($dispatched);
        $handled = $envelope->last(HandledStamp::class);
        $allHandled = $envelope->all(HandledStamp::class);

        $payloadValue = $request->getPayload()->getString('title');
        $headerList = $headers->all('X-Request-Id');
        $user = $this->getUser();
        $url = $this->generateUrl('app_home');

        if ($handled !== null && $allHandled !== [] && $user !== null && $headerList !== [] && $payloadValue !== '' && $url !== '') {
            return $this->redirectToRoute('app_home');
        }

        return $this->redirectToRoute('app_home');
    }

    public function exerciseWave4Contracts(
        HeaderBag $headers,
        FormInterface $form,
        SerializerInterface $serializer,
        DenormalizerInterface $denormalizer,
    ): string {
        $fallbackHeader = $headers->get('X-Correlation-Id', 'default-correlation-id');
        $emptyDefaultHeader = $headers->get('X-Empty-Default', '');

        $messages = [
            $fallbackHeader,
            $emptyDefaultHeader,
        ];

        foreach ($form->getErrors() as $errDefault) {
            $messages[] = $errDefault->getMessage();
        }
        foreach ($form->getErrors(true) as $errDeepFlat) {
            $messages[] = $errDeepFlat->getMessage();
        }
        foreach ($form->getErrors(false, false) as $errShallowUnflat) {
            $messages[] = $errShallowUnflat->getMessage();
        }
        foreach ($form->getErrors(true, true) as $errExplicit) {
            $messages[] = $errExplicit->getMessage();
        }

        $dto = $serializer->deserialize('{"name":"ev"}', DomainEvent::class, 'json');
        $messages[] = $dto->name;

        $dtoList = $serializer->deserialize('[{"name":"ev"}]', DomainEvent::class . '[]', 'json');
        foreach ($dtoList as $key => $item) {
            $messages[$key] = $item->name;
        }

        $denormalized = $denormalizer->denormalize(['name' => 'ev'], DomainEvent::class);
        $messages[] = $denormalized->name;

        $denormalizedMatrix = $denormalizer->denormalize(['primary' => ['first' => ['name' => 'ev']]], DomainEvent::class . '[][]');
        foreach ($denormalizedMatrix as $rowKey => $row) {
            foreach ($row as $cellKey => $cell) {
                $messages[$rowKey . ':' . $cellKey] = $cell->name;
            }
        }

        return $fallbackHeader;
    }
}

final class PartialDynamicSetDefinitionCommand extends Command
{
    /** @param list<InputOption> $extraOptions */
    protected function configureWithExtra(array $extraOptions = []): void
    {
    }

    protected function configure(): void
    {
        $extra = new InputOption('dynamic-extra', null, InputOption::VALUE_NONE);
        $this->setDefinition([
            new InputArgument('known', InputArgument::REQUIRED),
            $extra,
        ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $input->getArgument('known') !== null && $input->getOption('dynamic-extra') !== null
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}

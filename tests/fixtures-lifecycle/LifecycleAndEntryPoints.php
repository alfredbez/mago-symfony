<?php

declare(strict_types=1);

namespace SymfonyFixtureLifecycle;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

final class InitializedCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(private readonly string $appName)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('app:demo');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $this->io->note($this->appName);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->success($this->appName);

        return self::SUCCESS;
    }
}

final class RequiredPropertyService
{
    #[Required]
    #[Autowire('%app.name%')]
    public string $injectedName;

    #[Required]
    #[Autowire('%app.debug%')]
    public bool $injectedDebug;

    #[Required]
    public \stdClass $injectedService;

    #[Required]
    public ?\stdClass $nullableInjected;

    // @mago-ignore analysis:non-existent-class-like
    #[Required]
    public \UnloadedVendor\EntityManagerInterface $externalService;

    public function readInjected(): string
    {
        $service = $this->injectedService;
        $external = $this->externalService;
        unset($service, $external);

        return $this->nullableInjected !== null && $this->injectedDebug
            ? $this->injectedName
            : $this->injectedName;
    }
}

final class AttributedEntryPoints
{
    private \stdClass $wiredService;

    #[Route('/ping')]
    private function pingAction(): Response
    {
        return new Response('pong');
    }

    #[AsEventListener(event: 'kernel.request')]
    private function onRequest(): void
    {
    }

    #[AsMessageHandler]
    private function onMessage(object $message): void
    {
        unset($message);
    }

    #[Callback]
    private function validateState(ExecutionContextInterface $context): void
    {
        unset($context);
    }

    #[Required]
    public function wireDependency(\stdClass $service): void
    {
        $this->wiredService = $service;
    }

    public function readWired(): \stdClass
    {
        return $this->wiredService;
    }
}

final class LifecycleFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        unset($builder, $options);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        unset($view, $form, $options);
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        unset($view, $form, $options);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        unset($resolver);
    }
}

final class LifecycleValidator extends ConstraintValidator
{
    private string $tag;

    public function initialize(ExecutionContextInterface $context): void
    {
        parent::initialize($context);
        $this->tag = 'initialized';
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        unset($value, $constraint);
        $this->context->addViolation($this->tag);
    }
}

/** @extends Voter<string, object> */
final class LifecycleVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === 'VIEW' && is_object($subject);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        unset($attribute, $subject, $token);

        return true;
    }
}

final class LifecycleConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        return new TreeBuilder('demo');
    }
}

final class LifecycleExtension implements ExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        unset($configs, $container);
    }

    public function getNamespace(): string
    {
        return 'http://example.com/schema/dic/demo';
    }

    public function getXsdValidationBasePath(): string|false
    {
        return false;
    }

    public function getAlias(): string
    {
        return 'demo';
    }
}

final class LifecycleCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        unset($container);
    }
}

final class LifecycleEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [];
    }
}

final class LifecycleServiceSubscriber implements ServiceSubscriberInterface
{
    public static function getSubscribedServices(): array
    {
        return [];
    }
}

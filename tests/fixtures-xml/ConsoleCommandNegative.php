<?php

declare(strict_types=1);

namespace SymfonyFixtureXml;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class TypoCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED)
            ->addOption('dry-run', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $input->getArgument('user_name') !== null
            && $input->getOption('dryrun') !== null
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}

final class SetDefinitionTypoCommand extends Command
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
        return $input->getArgument('missing_target') !== null
            && $input->getOption('missing_force') !== null
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}

final class OptionOnlyCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $input->getArgument('command') !== null
            && $input->getArgument('undeclared_arg') !== null
            && $input->getOption('dry-run') !== null
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}

final class ArgumentOnlyCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('target', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $input->getOption('verbose') !== null
            && $input->getOption('undeclared-opt') !== null
            && $input->getArgument('target') !== null
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}

final class UnrelatedConstructorHelperCommand extends Command
{
    private bool $initialized = false;

    public function __construct()
    {
        parent::__construct('demo:unrelated-helper');
        $this->setDescription('Has an unrelated private initializer');
        $this->markInitialized();
    }

    private function markInitialized(): void
    {
        $this->initialized = true;
    }

    protected function configure(): void
    {
        $this->addArgument('known', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->initialized
            && $input->getArgument('known') !== null
            && $input->getArgument('missing_after_helper') !== null
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}

class FinalProtectedHelperCommand extends Command
{
    private bool $initialized = false;

    public function __construct()
    {
        parent::__construct('demo:final-protected-helper');
        $this->markReady();
    }

    final protected function markReady(): void
    {
        $this->initialized = true;
    }

    protected function configure(): void
    {
        $this->addArgument('known', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->initialized
            && $input->getArgument('known') !== null
            && $input->getArgument('missing_after_final_helper') !== null
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}

final class ParentOnlyConstructorInvalidCommand extends Command
{
    public function __construct()
    {
        parent::__construct('demo:parent-only');
    }

    protected function configure(): void
    {
        $this->addArgument('known', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $input->getArgument('known') !== null
            && $input->getArgument('missing_parent_only') !== null
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}

final class CyclicConstructorHelperCommand extends Command
{
    private int $depth = 0;

    public function __construct()
    {
        parent::__construct('demo:cyclic-helper');
        $this->cycleA();
    }

    private function cycleA(): void
    {
        if ($this->depth < 1) {
            $this->depth++;
            $this->cycleB();
        }
    }

    private function cycleB(): void
    {
        $this->cycleA();
    }

    protected function configure(): void
    {
        $this->addArgument('known', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $input->getArgument('known') !== null
            && $input->getArgument('missing_after_cycle') !== null
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}

final class NestedAnonymousClassConstructorCommand extends Command
{
    public function __construct()
    {
        parent::__construct('demo:anon-in-ctor');
        $helper = new class {
            public function addArgument(string $name): void {}

            public function init(): void
            {
                $this->addArgument('ignored_anon_arg');
            }
        };
        $helper->init();
    }

    protected function configure(): void
    {
        $this->addArgument('known', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $input->getArgument('known') !== null
            && $input->getArgument('missing_after_anon') !== null
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}

final class DashedOptionLookupCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('target', InputArgument::REQUIRED)
            ->addOption('--known', 'k', InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dynamicOpt = $this->dynamicName('known');

        return $input->getArgument('target') !== null
            && $input->getArgument('--target') !== null
            && $input->getOption('known') !== null
            && $input->getOption($dynamicOpt) !== null
            && $input->getOption('verbose') !== null
            && $input->getOption('env') !== null
            && $input->getOption('--known') !== null
            && $input->getOption('-known') !== null
            && $input->getOption('-k') !== null
            && $input->getOption('--verbose') !== null
            && $input->getOption('-v') !== null
            && $input->getOption('unknown_plain') !== null
            ? Command::SUCCESS
            : Command::FAILURE;
    }

    private function dynamicName(string $name): string
    {
        return $name;
    }
}

final class SetDefinitionDashedOptionLookupCommand extends Command
{
    protected function configure(): void
    {
        $this->setDefinition([
            new InputOption('--known', 'k', InputOption::VALUE_NONE),
        ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $input->getOption('known') !== null
            && $input->getOption('--known') !== null
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}

final class SingleDashOptionLookupNegativeCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('-arg', InputArgument::OPTIONAL)
            ->addOption('-dash', null, InputOption::VALUE_NONE)
            ->addOption('---triple', null, InputOption::VALUE_NONE)
            ->addOption('--known', 'k', InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $input->getArgument('-arg') !== null
            && $input->getArgument('arg') !== null
            && $input->getOption('-dash') !== null
            && $input->getOption('dash') !== null
            && $input->getOption('-triple') !== null
            && $input->getOption('triple') !== null
            && $input->getOption('---triple') !== null
            && $input->getOption('known') !== null
            && $input->getOption('--known') !== null
            && $input->getOption('') !== null
            && $input->getOption('--') !== null
            && !$input->hasOption('dash')
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}

final class SetDefinitionSingleDashNegativeCommand extends Command
{
    protected function configure(): void
    {
        $this->setDefinition([
            new InputOption('-set-dash', null, InputOption::VALUE_NONE),
        ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $input->getOption('-set-dash') !== null
            && $input->getOption('set-dash') !== null
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}

final class FrameworkSilentOptionCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('local', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $input->getOption('silent') !== null
            && $input->getOption('env') !== null
            && $input->getOption('no-debug') !== null
            && $input->getOption('profile') !== null
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}

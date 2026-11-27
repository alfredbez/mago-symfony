<?php

declare(strict_types=1);

namespace SymfonyFixtureConsoleContracts;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument as Arg;
use Symfony\Component\Console\Input\InputDefinition as Def;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption as Opt;
use Symfony\Component\Console\Output\OutputInterface;

final class ClassConstantCommand extends Command
{
    private const ARG_USER = 'username';
    private const OPT_DRY = 'dry-run';

    protected function configure(): void
    {
        $this
            ->addArgument(self::ARG_USER, Arg::REQUIRED)
            ->addOption(self::OPT_DRY, 'd', Opt::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $input->getArgument(self::ARG_USER) !== null
            && $input->getArgument('username') !== null
            && $input->getOption(self::OPT_DRY) !== null
            && $input->getOption('dry-run') !== null
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}

final class GetNameAddUsageCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('demo:usage')
            ->setDescription('Demo command')
            ->addUsage((string) $this->getName() . ' --flag')
            ->addOption('flag', null, Opt::VALUE_NONE);

        $_ = [
            $this->getDescription(),
            $this->getHelp(),
            $this->getAliases(),
            $this->getUsages(),
            $this->isHidden(),
        ];
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $input->getOption('flag') !== null ? Command::SUCCESS : Command::FAILURE;
    }
}

final class SetDefinitionInputDefinitionAndAliasCommand extends Command
{
    private const ARG_ID = 'target-id';
    private const OPT_FORCE = 'force';

    protected function configure(): void
    {
        $this->setDefinition(new Def([
            new Arg(self::ARG_ID, Arg::REQUIRED),
            new Opt(self::OPT_FORCE, 'f', Opt::VALUE_NONE),
        ]));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $input->getArgument(self::ARG_ID) !== null
            && $input->getOption(self::OPT_FORCE) !== null
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}

final class HasOptionAndHasArgumentGuardCommand extends Command
{
    private const OPT_GUARDED = 'const-guarded';

    protected function configure(): void
    {
        $this
            ->addArgument('known_arg', Arg::OPTIONAL)
            ->addOption('known', null, Opt::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->hasOption('extra')) {
            $_ = $input->getOption('extra');
        }
        if ($input->hasOption('extra') && $input->getOption('known') !== null) {
            $_ = $input->getOption('extra');
        }
        $ternary = $input->hasOption('extra') ? ($input->getOption('extra') !== null) : false;
        $shortCircuit = $input->hasOption('extra') && $input->getOption('extra') !== null;

        if ($input->hasArgument('extra_arg')) {
            $_ = $input->getArgument('extra_arg');
        }
        if ($input->hasOption(self::OPT_GUARDED)) {
            $_ = $input->getOption(self::OPT_GUARDED);
        }

        return $ternary || $shortCircuit || $this->earlyReturnGuard($input)
            ? Command::SUCCESS
            : Command::FAILURE;
    }

    private function earlyReturnGuard(InputInterface $input): bool
    {
        if (!$input->hasOption('early-opt')) {
            return false;
        }

        return $input->getOption('early-opt') !== null;
    }
}

final class ValidShortcutsAndSuggestedValuesCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('ok1', '', Opt::VALUE_NONE)
            ->addOption('ok2', [], Opt::VALUE_NONE)
            ->addOption('ok3', '-f|-x', Opt::VALUE_NONE)
            ->addOption('ok4', null, Opt::VALUE_NONE, '', null, [])
            ->addOption('ok5', null, Opt::VALUE_REQUIRED, '', null, ['a', 'b'])
            ->addOption('ok6', null, Opt::VALUE_OPTIONAL, '', null, static fn (): array => ['a']);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $input->getOption('ok1') !== null ? Command::SUCCESS : Command::FAILURE;
    }
}

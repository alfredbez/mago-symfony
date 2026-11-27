<?php

declare(strict_types=1);

namespace SymfonyFixtureConsoleContracts;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument as Arg;
use Symfony\Component\Console\Input\InputDefinition as Def;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption as Opt;
use Symfony\Component\Console\Output\OutputInterface;

final class ClassConstantTypoCommand extends Command
{
    private const ARG_USER = 'username';
    private const ARG_MISSING = 'missing_user';
    private const OPT_DRY = 'dry-run';
    private const OPT_BAD = 'bad-flag';
    private const OPT_MISSING = 'missing-flag';

    protected function configure(): void
    {
        $this
            ->addArgument(self::ARG_USER, Arg::REQUIRED)
            ->addOption(self::OPT_DRY, 'd', Opt::VALUE_NONE)
            ->addOption(self::OPT_BAD, null, Opt::VALUE_NONE, '', 'invalid-default');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $input->getArgument(self::ARG_MISSING) !== null
            && $input->getOption(self::OPT_MISSING) !== null
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}

final class GetNameAddUsageTypoCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('demo:typo')
            ->addUsage((string) $this->getName() . ' --known')
            ->addOption('known', null, Opt::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $input->getOption('missing_after_getname') !== null
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}

final class InvalidEmptyAndZeroOptionNamesCommand extends Command
{
    private const EMPTY_NAME = '';

    protected function configure(): void
    {
        $this
            ->addOption('', null, Opt::VALUE_NONE)
            ->addOption('--', null, Opt::VALUE_NONE)
            ->addOption('0', null, Opt::VALUE_NONE)
            ->addOption('--0', null, Opt::VALUE_NONE)
            ->addOption(self::EMPTY_NAME, null, Opt::VALUE_NONE);
    }
}

final class InvalidShortcutsAndSuggestedValuesCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('dash_shortcut', '-', Opt::VALUE_NONE)
            ->addOption('double_dash_shortcut', '--', Opt::VALUE_NONE)
            ->addOption('pipe_dash_shortcut', '-|-', Opt::VALUE_NONE)
            ->addOption('array_dash_shortcut', ['-'], Opt::VALUE_NONE)
            ->addOption('none_with_suggested_array', null, Opt::VALUE_NONE, '', null, ['a'])
            ->addOption('none_with_suggested_closure', null, Opt::VALUE_NONE, '', null, static fn (): array => ['a'])
            ->addOption('negatable_with_suggested', null, Opt::VALUE_NEGATABLE, '', null, ['a']);
    }
}

final class EmptyGetArgumentCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('known', Arg::OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $input->getArgument('') !== null ? Command::SUCCESS : Command::FAILURE;
    }
}

final class SetDefinitionReplacementNegativeCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('replaced_arg', Arg::OPTIONAL)
            ->addOption('replaced_opt', null, Opt::VALUE_NONE)
            ->setDefinition(new Def([
                new Arg('kept_arg', Arg::OPTIONAL),
                new Opt('kept_opt', null, Opt::VALUE_NONE),
            ]));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $input->getArgument('kept_arg') !== null
            && $input->getOption('kept_opt') !== null
            && $input->getArgument('replaced_arg') !== null
            && $input->getOption('replaced_opt') !== null
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}

final class UnguardedAndMismatchedGuardNegativeCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('known', null, Opt::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $other = $input;
        $flag = $output->isVerbose();

        if ($input->hasOption('a')) {
            $_ = $input->getOption('b');
        }
        if ($input->hasOption('a') || $flag) {
            $_ = $input->getOption('a');
        }
        if (!$input->hasOption('a')) {
            $_ = $input->getOption('a');
        }
        if ($input->hasOption('a')) {
            $_ = true;
        } else {
            $_ = $input->getOption('a');
        }
        if ($input->hasOption('a')) {
            $_ = true;
        }
        $_ = $input->getOption('a');

        if ($input->hasArgument('a')) {
            $_ = $input->getOption('a');
        }
        if ($input->hasOption('a')) {
            $_ = $other->getOption('a');
        }
        if ($input->hasOption('a')) {
            $input = $other;
            $_ = $input->getOption('a');
        }

        return Command::SUCCESS;
    }
}

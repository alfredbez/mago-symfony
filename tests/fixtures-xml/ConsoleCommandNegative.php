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

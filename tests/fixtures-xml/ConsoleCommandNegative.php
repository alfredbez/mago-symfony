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

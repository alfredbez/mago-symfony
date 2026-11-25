<?php

declare(strict_types=1);

namespace SymfonyFixtureConsolePlain;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class PlainApplicationContextNegativeCommand extends Command
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

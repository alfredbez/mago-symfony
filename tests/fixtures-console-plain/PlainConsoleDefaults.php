<?php

declare(strict_types=1);

namespace SymfonyFixtureConsolePlain;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class PlainBuiltinOptionsCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('local', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $input->getOption('local') !== null
            && $input->getOption('help') !== null
            && $input->getOption('quiet') !== null
            && $input->getOption('verbose') !== null
            && $input->getOption('version') !== null
            && $input->getOption('ansi') !== null
            && $input->getOption('no-ansi') !== null
            && $input->getOption('no-interaction') !== null
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}

final class LocalRedeclaredApplicationOptionsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('silent', null, InputOption::VALUE_NONE)
            ->addOption('env', 'e', InputOption::VALUE_REQUIRED)
            ->addOption('no-debug', null, InputOption::VALUE_NONE)
            ->addOption('profile', null, InputOption::VALUE_NONE);
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

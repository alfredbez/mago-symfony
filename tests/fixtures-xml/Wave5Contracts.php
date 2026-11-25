<?php

declare(strict_types=1);

namespace SymfonyFixtureXml;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\DebugFormatterHelper;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

final class CustomModes
{
    public const REQUIRED = InputArgument::OPTIONAL;
}

final class Wave5HelperCommand extends Command
{
    protected function configure(): void
    {
        $dynamicMode = $this->dynamicMode();

        $this
            ->addArgument('name', InputArgument::REQUIRED)
            ->addArgument('alias_opt', CustomModes::REQUIRED, '', 'valid-default')
            ->addArgument('tags', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, '', ['default'])
            ->addArgument('array_only', InputArgument::IS_ARRAY)
            ->addArgument('req_and_opt', InputArgument::REQUIRED | InputArgument::OPTIONAL)
            ->addArgument('dynamic_arg', $dynamicMode)
            ->addOption('flag', null, InputOption::VALUE_NONE)
            ->addOption('items', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, '', ['a'])
            ->addOption('color', null, InputOption::VALUE_NEGATABLE, '', true)
            ->addOption('literal_negatable', null, 16)
            ->addOption('dynamic_opt', null, $dynamicMode);
    }

    private function dynamicMode(): int
    {
        return InputOption::VALUE_NONE;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $question = $this->getHelper('question');
        $formatter = $this->getHelper('formatter');
        $debug = $this->getHelper('debug_formatter');
        $process = $this->getHelper('process');

        $this->acceptHelpers($question, $formatter, $debug, $process);

        return Command::SUCCESS;
    }

    private function acceptHelpers(
        QuestionHelper $question,
        FormatterHelper $formatter,
        DebugFormatterHelper $debug,
        ProcessHelper $process,
    ): void {
        $_ = [$question, $formatter, $debug, $process];
    }
}

final class Wave5Contracts
{
    public function requestFormats(Request $request): string
    {
        $a = $request->getRequestFormat();
        $b = $request->getRequestFormat('json');
        $c = $request->getPreferredFormat();
        $d = $request->getPreferredFormat('xml');

        return $a . ':' . $b . ':' . $c . ':' . $d;
    }

    public function responseCookies(ResponseHeaderBag $headers): string
    {
        $names = [];
        foreach ($headers->getCookies() as $cookie) {
            $names[] = $this->readCookie($cookie);
        }
        foreach ($headers->getCookies(ResponseHeaderBag::COOKIES_FLAT) as $cookie) {
            $names[] = $this->readCookie($cookie);
        }
        foreach ($headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY) as $domain => $paths) {
            foreach ($paths as $path => $cookies) {
                foreach ($cookies as $name => $cookie) {
                    $names[] = $domain . $path . $name . ':' . $this->readCookie($cookie);
                }
            }
        }

        return implode(',', $names);
    }

    public function helperSet(HelperSet $helpers): QuestionHelper
    {
        return $helpers->get('question');
    }

    private function readCookie(Cookie $cookie): string
    {
        return $cookie->getName();
    }
}

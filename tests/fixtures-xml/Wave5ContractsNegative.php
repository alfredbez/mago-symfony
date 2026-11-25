<?php

declare(strict_types=1);

namespace SymfonyFixtureXml;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

final class InvalidConsoleDefinitionsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('required_with_default', InputArgument::REQUIRED, '', 'oops')
            ->addArgument('array_with_scalar_default', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, '', 'not-an-array')
            ->addArgument('arg_mode_zero', 0)
            ->addArgument('arg_mode_negative', -1)
            ->addArgument('arg_mode_high', 8)
            ->addOption('flag_with_default', null, InputOption::VALUE_NONE, '', false)
            ->addOption('array_without_value', null, InputOption::VALUE_NONE | InputOption::VALUE_IS_ARRAY)
            ->addOption('negatable_with_value', null, InputOption::VALUE_NEGATABLE | InputOption::VALUE_REQUIRED)
            ->addOption('array_opt_scalar_default', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, '', 'bad')
            ->addOption('opt_mode_zero', null, 0)
            ->addOption('opt_mode_negative', null, -1)
            ->addOption('opt_mode_high', null, 32)
            ->addOption('opt_array_bare', null, InputOption::VALUE_IS_ARRAY)
            ->addOption('opt_negatable_array', null, InputOption::VALUE_NEGATABLE | InputOption::VALUE_IS_ARRAY);
    }
}

final class SetDefinitionInvalidModesCommand extends Command
{
    protected function configure(): void
    {
        $this->setDefinition([
            new InputArgument('set_bad_arg', 0),
            new InputOption('set_bad_opt', null, 32),
            new InputOption('set_ok_negatable', null, InputOption::VALUE_NEGATABLE),
        ]);
    }
}

final class Wave5ContractsNegative
{
    public function nullableRequestFormat(Request $request): string
    {
        return $request->getRequestFormat(null);
    }

    public function nullablePreferredFormat(Request $request): string
    {
        return $request->getPreferredFormat(null);
    }

    public function flatCookieWrongMethod(ResponseHeaderBag $headers): void
    {
        foreach ($headers->getCookies(ResponseHeaderBag::COOKIES_FLAT) as $cookie) {
            $cookie->nonExistentCookieMethod();
        }
    }

    public function arrayCookieWrongMethod(ResponseHeaderBag $headers): void
    {
        foreach ($headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY) as $paths) {
            foreach ($paths as $cookies) {
                foreach ($cookies as $cookie) {
                    $cookie->nonExistentNestedCookieMethod();
                }
            }
        }
    }

    public function helperWrongMethod(HelperSet $helpers): void
    {
        $helpers->get('question')->nonExistentQuestionHelperMethod();
    }
}

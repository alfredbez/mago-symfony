<?php

declare(strict_types=1);

namespace SymfonyFixtureLifecycle;

use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Contracts\Service\Attribute\Required;

enum SampleRequiredEnum: string
{
    case Alpha = 'alpha';
}

final class RequiredPropertyNegative
{
    #[Required]
    private string $privateRequired;

    #[Required]
    protected string $protectedRequired;

    #[Required]
    public readonly string $readonlyRequired;

    #[Required]
    public \stdClass|\Countable $unionRequired;

    #[Required]
    public \Iterator&\Countable $intersectionRequired;

    #[Required]
    public string $bareScalarRequired;

    /** @var array<string, mixed> */
    #[Required]
    public array $bareArrayRequired;

    #[Required]
    public mixed $bareMixedRequired;

    #[Required]
    public SampleRequiredEnum $enumRequired;

    #[Required]
    #[AutowireDecorated]
    public string $decoratedScalarRequired;

    public string $plainPublic;

    public function __construct()
    {
    }

    public function readAll(): string
    {
        $u = $this->unionRequired;
        $i = $this->intersectionRequired;
        $a = $this->bareArrayRequired;
        $e = $this->enumRequired;
        unset($u, $i, $a, $e);
        if ($this->bareMixedRequired === null) {
            return $this->plainPublic;
        }

        return $this->privateRequired
            . $this->protectedRequired
            . $this->readonlyRequired
            . $this->bareScalarRequired
            . $this->decoratedScalarRequired
            . $this->plainPublic;
    }

    private function genuinelyUnusedHelper(): void
    {
    }
}

final class NonPublicRequiredMethodNegative
{
    private string $fromPrivateRequired;
    private string $fromProtectedRequired;

    public function __construct()
    {
    }

    #[Required]
    private function initPrivateRequired(): void
    {
        $this->fromPrivateRequired = 'private';
    }

    #[Required]
    protected function initProtectedRequired(): void
    {
        $this->fromProtectedRequired = 'protected';
    }

    #[Required]
    public static function initStaticRequired(): void
    {
    }

    public function readInitialized(): string
    {
        return $this->fromPrivateRequired . $this->fromProtectedRequired;
    }
}

<?php

declare(strict_types=1);

namespace SymfonyFixtureLifecycle;

use Symfony\Contracts\Service\Attribute\Required;

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

    public string $plainPublic;

    public function __construct()
    {
    }

    public function readAll(): string
    {
        $u = $this->unionRequired;
        $i = $this->intersectionRequired;
        unset($u, $i);

        return $this->privateRequired
            . $this->protectedRequired
            . $this->readonlyRequired
            . $this->plainPublic;
    }

    private function genuinelyUnusedHelper(): void
    {
    }
}

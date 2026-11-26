<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

/**
 * Narrows Symfony Security getUser() return types to a configured UserInterface
 * implementation when MAGO_SYMFONY_USER_CLASS is explicitly set.
 */
final class SecurityUserReturnTypeProvider implements MethodReturnTypeProvider
{
    public const ENVIRONMENT_VARIABLE = 'MAGO_SYMFONY_USER_CLASS';

    private const USER_INTERFACE = 'Symfony\\Component\\Security\\Core\\User\\UserInterface';
    private const LOWER_USER_INTERFACE = 'symfony\\component\\security\\core\\user\\userinterface';

    private bool $validated = false;
    private ?Type $nullableUserType = null;

    public function __construct(private readonly string $userClass) {}

    public static function configuredUserClass(): ?string
    {
        $configured = getenv(self::ENVIRONMENT_VARIABLE);
        if ($configured === false) {
            return null;
        }

        $normalized = ltrim(trim($configured), '\\');

        return $normalized !== '' ? $normalized : null;
    }

    public function getTargets(): array
    {
        return [
            MethodTarget::exact('Symfony\\Bundle\\FrameworkBundle\\Controller\\AbstractController', 'getUser'),
            MethodTarget::exact('Symfony\\Bundle\\SecurityBundle\\Security', 'getUser'),
            MethodTarget::exact('Symfony\\Component\\Security\\Core\\Security', 'getUser'),
            MethodTarget::exact('Symfony\\Component\\Security\\Core\\Authentication\\Token\\TokenInterface', 'getUser'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        if ($this->validated) {
            return $this->nullableUserType;
        }

        if (!$context->codebase->classOrInterfaceExists($this->userClass)) {
            fwrite(
                STDERR,
                "mago-symfony: " . self::ENVIRONMENT_VARIABLE . " `{$this->userClass}` does not exist in the analyzed codebase; Security getUser() will not be narrowed.\n",
            );
            $this->validated = true;

            return null;
        }

        if (!$this->implementsUserInterface($context)) {
            fwrite(
                STDERR,
                "mago-symfony: " . self::ENVIRONMENT_VARIABLE . " `{$this->userClass}` does not implement " . self::USER_INTERFACE . "; Security getUser() will not be narrowed.\n",
            );
            $this->validated = true;

            return null;
        }

        $resolved = Type::union(
            Type::namedObject($this->userClass),
            Type::null(),
        );
        $this->nullableUserType = $resolved;
        $this->validated = true;

        return $resolved;
    }

    private function implementsUserInterface(ReturnTypeProviderContext $context): bool
    {
        if (strtolower($this->userClass) === self::LOWER_USER_INTERFACE) {
            return true;
        }

        foreach ($context->codebase->getClassAncestors($this->userClass) as $ancestor) {
            if (strtolower($ancestor) === self::LOWER_USER_INTERFACE) {
                return true;
            }
        }

        return false;
    }
}

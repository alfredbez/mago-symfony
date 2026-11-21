<?php

declare(strict_types=1);

namespace SymfonyFixtureSecurity;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class AppUser implements UserInterface
{
    public function __construct(private readonly string $displayName) {}

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void {}

    public function getUserIdentifier(): string
    {
        return $this->displayName !== '' ? $this->displayName : 'anonymous';
    }
}

final class NotAUser
{
    public function getDisplayName(): string
    {
        return 'not-a-user';
    }
}

final class SecurityUserController extends AbstractController
{
    public function readConfiguredUsers(Security $security, TokenInterface $token): string
    {
        $fromController = $this->getUser();
        $fromSecurity = $security->getUser();
        $fromToken = $token->getUser();

        $parts = [];
        if ($fromController !== null) {
            $parts[] = $fromController->getDisplayName();
        }
        if ($fromSecurity !== null) {
            $parts[] = $fromSecurity->getDisplayName();
        }
        if ($fromToken !== null) {
            $parts[] = $fromToken->getDisplayName();
        }

        return implode(',', $parts);
    }

    public function negativeChecks(Security $security): void
    {
        $unguarded = $security->getUser();
        $_ = $unguarded->getDisplayName();

        $checked = $this->getUser();
        if ($checked !== null) {
            $checked->nonExistentMethodOnAppUser();
        }
    }
}

<?php

declare(strict_types=1);

namespace SymfonyFixtureSecurity;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;

final class SecurityUserNegative extends AbstractController
{
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

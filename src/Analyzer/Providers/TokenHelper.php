<?php

declare(strict_types=1);

namespace AlfredBez\MagoSymfony\Analyzer\Providers;

use function in_array;

use const T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG;
use const T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG;

/**
 * Shared tokenizer helpers for PHP 8.1+ token compatibility.
 *
 * @internal
 */
final class TokenHelper
{
    public static function isAmpersand(int|string $tokenId): bool
    {
        return in_array(
            $tokenId,
            ['&', T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG, T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG],
            true,
        );
    }
}

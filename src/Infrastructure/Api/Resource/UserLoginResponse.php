<?php

declare(strict_types=1);

namespace App\Infrastructure\Api\Resource;

/**
 * Response DTO for the login endpoint.
 *
 * Contains the JWT token returned after successful authentication.
 */
final readonly class UserLoginResponse
{
    public function __construct(
        public string $token,
    ) {
    }
}

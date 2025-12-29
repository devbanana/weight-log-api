<?php

declare(strict_types=1);

namespace App\Application\User\Query;

/**
 * Data transfer object for user information.
 */
final readonly class UserInfo
{
    /**
     * @param non-empty-string $id
     * @param non-empty-string $email
     * @param non-empty-string $displayName
     * @param non-empty-string $registeredAt ISO 8601 format
     */
    public function __construct(
        public string $id,
        public string $email,
        public string $displayName,
        public string $registeredAt,
    ) {
    }
}

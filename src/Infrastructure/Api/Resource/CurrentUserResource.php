<?php

declare(strict_types=1);

namespace App\Infrastructure\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\Infrastructure\Api\State\GetCurrentUserInfoProvider;

/**
 * API resource for the current user's info.
 *
 * Returns the authenticated user's profile information.
 */
#[ApiResource(
    shortName: 'CurrentUser',
    operations: [
        new Get(
            uriTemplate: '/me',
            provider: GetCurrentUserInfoProvider::class,
            openapi: new Operation(
                summary: 'Get current user info',
                description: 'Returns the authenticated user\'s profile information.',
                security: [['bearerAuth' => []]],
            ),
        ),
    ],
)]
final readonly class CurrentUserResource
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

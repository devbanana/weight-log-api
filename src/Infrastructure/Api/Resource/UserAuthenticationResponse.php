<?php

declare(strict_types=1);

namespace App\Infrastructure\Api\Resource;

use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Response DTO for the authentication endpoint.
 *
 * Contains the JWT token and expiration details after successful authentication.
 * Follows OAuth2 conventions with snake_case field names. Note that `expires_at`
 * is a non-standard extension for client convenience.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6749#section-5.1 OAuth2 Access Token Response
 */
final readonly class UserAuthenticationResponse
{
    public function __construct(
        #[SerializedName('access_token')]
        public string $accessToken,
        #[SerializedName('token_type')]
        public string $tokenType,
        #[SerializedName('expires_in')]
        public int $expiresIn,
        #[SerializedName('expires_at')]
        public string $expiresAt,
    ) {
    }
}

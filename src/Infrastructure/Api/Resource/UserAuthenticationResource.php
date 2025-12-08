<?php

declare(strict_types=1);

namespace App\Infrastructure\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Infrastructure\Api\State\AuthenticateUserProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * API resource for user authentication.
 *
 * This is an input DTO for the authentication endpoint.
 * It accepts credentials and returns a JWT token.
 */
#[ApiResource(
    shortName: 'Authentication',
    operations: [
        new Post(
            uriTemplate: '/tokens',
            status: 200,
            processor: AuthenticateUserProcessor::class,
            output: UserAuthenticationResponse::class,
            openapi: new Operation(
                summary: 'Authenticate a user',
                description: 'Authenticates a user and returns a JWT token.',
            ),
        ),
    ],
)]
final readonly class UserAuthenticationResource
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,
        #[Assert\NotBlank]
        public string $password,
    ) {
    }
}

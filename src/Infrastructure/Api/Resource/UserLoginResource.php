<?php

declare(strict_types=1);

namespace App\Infrastructure\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Infrastructure\Api\State\LoginUserProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * API resource for user login.
 *
 * This is an input DTO for the login endpoint.
 * It accepts credentials and returns a JWT token.
 */
#[ApiResource(
    shortName: 'Login',
    operations: [
        new Post(
            uriTemplate: '/auth/login',
            status: 200,
            processor: LoginUserProcessor::class,
            output: UserLoginResponse::class,
            openapi: new Operation(
                summary: 'Log in a user',
                description: 'Authenticates a user and returns a JWT token.',
            ),
        ),
    ],
)]
final readonly class UserLoginResource
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

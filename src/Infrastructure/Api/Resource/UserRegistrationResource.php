<?php

declare(strict_types=1);

namespace App\Infrastructure\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Infrastructure\Api\State\RegisterUserProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * API resource for user registration.
 *
 * This is an input DTO for the registration endpoint.
 * It's not a database entity - it represents the registration request.
 */
#[ApiResource(
    shortName: 'Registration',
    operations: [
        new Post(
            uriTemplate: '/auth/register',
            status: 201,
            processor: RegisterUserProcessor::class,
            output: false,
            openapi: new Operation(
                summary: 'Register a new user',
                description: 'Creates a new user account with the provided email.',
            ),
        ),
    ],
)]
final class UserRegistrationResource
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public readonly string $email,
    ) {
    }
}

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
            uriTemplate: '/users',
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
final readonly class UserRegistrationResource
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,
        #[Assert\NotBlank]
        #[Assert\Date]
        public string $dateOfBirth,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 50, maxMessage: 'Display name cannot exceed {{ limit }} characters')]
        public string $displayName,
        #[Assert\NotBlank]
        #[Assert\Length(min: 8, minMessage: 'Password must be at least {{ limit }} characters long')]
        public string $password,
    ) {
    }
}

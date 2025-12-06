<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Minimal Symfony UserInterface implementation for JWT token generation.
 *
 * This is used by Lexik JWT bundle to create tokens.
 * It's intentionally simple - just enough for token generation.
 */
final readonly class SecurityUser implements UserInterface
{
    /**
     * @param non-empty-string $userId
     * @param list<string>     $roles
     */
    public function __construct(
        private string $userId,
        private array $roles = ['ROLE_USER'],
    ) {
    }

    /**
     * @return non-empty-string
     */
    #[\Override]
    public function getUserIdentifier(): string
    {
        return $this->userId;
    }

    /**
     * @return list<string>
     */
    #[\Override]
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * @codeCoverageIgnore Framework boilerplate; nothing to test here.
     */
    #[\Override]
    public function eraseCredentials(): void
    {
        // Nothing to erase - we don't store credentials
    }
}

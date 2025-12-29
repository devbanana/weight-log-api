<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * User provider for JWT authentication.
 *
 * This provider creates SecurityUser instances from the JWT payload.
 * The user identifier in the JWT is the user ID.
 *
 * @implements UserProviderInterface<SecurityUser>
 */
final class JwtUserProvider implements UserProviderInterface
{
    #[\Override]
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // The identifier from JWT is the user ID
        // We create a minimal SecurityUser - the profile endpoint will fetch full data
        assert($identifier !== '');

        return new SecurityUser($identifier);
    }

    #[\Override]
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof SecurityUser) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        // JWT is stateless - return the same user
        return $user;
    }

    #[\Override]
    public function supportsClass(string $class): bool
    {
        return SecurityUser::class === $class;
    }
}

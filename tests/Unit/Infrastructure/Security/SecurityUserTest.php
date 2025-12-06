<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security;

use App\Infrastructure\Security\SecurityUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SecurityUser.
 *
 * @internal
 */
#[CoversClass(SecurityUser::class)]
final class SecurityUserTest extends TestCase
{
    public function testItReturnsUserIdentifier(): void
    {
        $user = new SecurityUser('user-123');

        self::assertSame('user-123', $user->getUserIdentifier());
    }

    public function testItReturnsDefaultRoles(): void
    {
        $user = new SecurityUser('user-123');

        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testItReturnsCustomRoles(): void
    {
        $user = new SecurityUser('user-123', ['ROLE_ADMIN', 'ROLE_USER']);

        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
    }
}

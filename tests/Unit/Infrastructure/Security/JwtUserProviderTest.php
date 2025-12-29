<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security;

use App\Infrastructure\Security\JwtUserProvider;
use App\Infrastructure\Security\SecurityUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @internal
 */
#[CoversClass(JwtUserProvider::class)]
#[UsesClass(SecurityUser::class)]
final class JwtUserProviderTest extends TestCase
{
    private JwtUserProvider $provider;

    #[\Override]
    protected function setUp(): void
    {
        $this->provider = new JwtUserProvider();
    }

    public function testItLoadsUserByIdentifier(): void
    {
        $userId = '019412ab-cdef-7890-abcd-ef1234567890';

        $user = $this->provider->loadUserByIdentifier($userId);

        self::assertInstanceOf(SecurityUser::class, $user);
        self::assertSame($userId, $user->getUserIdentifier());
    }

    public function testItRefreshesSecurityUser(): void
    {
        $userId = '019412ab-cdef-7890-abcd-ef1234567890';
        $originalUser = new SecurityUser($userId);

        $refreshedUser = $this->provider->refreshUser($originalUser);

        self::assertSame($originalUser, $refreshedUser);
    }

    public function testItThrowsWhenRefreshingUnsupportedUser(): void
    {
        $unsupportedUser = $this->createMock(UserInterface::class);

        $this->expectException(UnsupportedUserException::class);

        $this->provider->refreshUser($unsupportedUser);
    }

    public function testItSupportsSecurityUserClass(): void
    {
        self::assertTrue($this->provider->supportsClass(SecurityUser::class));
    }

    public function testItDoesNotSupportOtherClasses(): void
    {
        self::assertFalse($this->provider->supportsClass(UserInterface::class));
        self::assertFalse($this->provider->supportsClass(\stdClass::class));
    }
}

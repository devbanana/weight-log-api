<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Api;

use App\Application\MessageBus\QueryBusInterface;
use App\Application\User\Query\GetUserInfoQuery;
use App\Application\User\Query\UserInfo;
use App\Domain\User\Exception\CouldNotFindUser;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\Api\EventListener\TokenResponseHeadersListener;
use App\Infrastructure\Api\Resource\CurrentUserResource;
use App\Infrastructure\Api\State\GetCurrentUserInfoProvider;
use App\Infrastructure\Security\SecurityUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Driving tests for the current user info API endpoint.
 *
 * These tests verify that the incoming HTTP adapter (API Platform provider)
 * correctly transforms HTTP requests into queries and returns user info.
 *
 * Note: 401 Unauthorized when not authenticated is handled by Symfony's
 * security firewall and tested via E2E tests.
 *
 * @internal
 */
#[CoversClass(CurrentUserResource::class)]
#[CoversClass(GetCurrentUserInfoProvider::class)]
#[UsesClass(SecurityUser::class)]
#[UsesClass(TokenResponseHeadersListener::class)]
#[UsesClass(UserId::class)]
final class GetCurrentUserInfoEndpointTest extends WebTestCase
{
    use HttpHelper;

    private KernelBrowser $client;

    /**
     * @var MockObject&QueryBusInterface
     */
    private QueryBusInterface $queryBus;

    #[\Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();

        $this->queryBus = $this->createMock(QueryBusInterface::class);
        self::getContainer()->set(QueryBusInterface::class, $this->queryBus);
    }

    public function testItReturnsCurrentUserInfoWhenAuthenticated(): void
    {
        $userId = '019412ab-cdef-7890-abcd-ef1234567890';

        $this->queryBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (GetUserInfoQuery $query) use ($userId): bool {
                self::assertSame($userId, $query->userId);

                return true;
            }))
            ->willReturn(new UserInfo(
                id: $userId,
                email: 'alice@example.com',
                displayName: 'Alice',
                registeredAt: '2024-01-15T10:30:00+00:00',
            ))
        ;

        $this->getJsonAsUser('/api/me', $userId);

        self::assertResponseStatusCodeSame(200);

        $data = $this->getJsonResponse();

        self::assertSame($userId, $data['id']);
        self::assertSame('alice@example.com', $data['email']);
        self::assertSame('Alice', $data['displayName']);
        self::assertSame('2024-01-15T10:30:00+00:00', $data['registeredAt']);
    }

    public function testItReturns404WhenUserNoLongerExists(): void
    {
        $userId = '019412ab-cdef-7890-abcd-ef1234567890';

        $this->queryBus
            ->expects(self::once())
            ->method('dispatch')
            ->willThrowException(CouldNotFindUser::withId(UserId::fromString($userId)))
        ;

        $this->getJsonAsUser('/api/me', $userId);

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * Makes a GET request as an authenticated user.
     *
     * @param non-empty-string $userId
     */
    private function getJsonAsUser(string $uri, string $userId): void
    {
        $this->client->loginUser(new SecurityUser($userId));

        $this->client->request('GET', $uri, server: [
            'HTTP_ACCEPT' => 'application/json',
        ]);
    }
}

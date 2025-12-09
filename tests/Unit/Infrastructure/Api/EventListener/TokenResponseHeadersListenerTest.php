<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Api\EventListener;

use App\Infrastructure\Api\EventListener\TokenResponseHeadersListener;
use App\Infrastructure\Api\Resource\UserAuthenticationResource;
use App\Infrastructure\Api\Resource\UserRegistrationResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Unit tests for TokenResponseHeadersListener.
 *
 * Verifies RFC 6749 compliance: token responses must include Cache-Control: no-store
 * to prevent caching of sensitive authentication tokens.
 *
 * @internal
 */
#[CoversClass(TokenResponseHeadersListener::class)]
final class TokenResponseHeadersListenerTest extends TestCase
{
    private TokenResponseHeadersListener $listener;

    #[\Override]
    protected function setUp(): void
    {
        $this->listener = new TokenResponseHeadersListener();
    }

    public function testItAddsCacheControlHeaderForSuccessfulTokenResponse(): void
    {
        $event = $this->createResponseEvent(
            resourceClass: UserAuthenticationResource::class,
            statusCode: 200,
        );

        $this->listener->onKernelResponse($event);

        $cacheControl = $event->getResponse()->headers->get('Cache-Control');
        self::assertIsString($cacheControl);
        self::assertStringContainsString('no-store', $cacheControl);
    }

    public function testItDoesNotAddHeaderForOtherResources(): void
    {
        $event = $this->createResponseEvent(
            resourceClass: UserRegistrationResource::class,
            statusCode: 200,
        );

        $this->listener->onKernelResponse($event);

        self::assertStringNotContainsString('no-store', (string) $event->getResponse()->headers->get('Cache-Control'));
    }

    public function testItDoesNotAddHeaderForNonApiPlatformRequests(): void
    {
        $event = $this->createResponseEvent(
            resourceClass: null,
            statusCode: 200,
        );

        $this->listener->onKernelResponse($event);

        self::assertStringNotContainsString('no-store', (string) $event->getResponse()->headers->get('Cache-Control'));
    }

    public function testItDoesNotAddHeaderForNon200Responses(): void
    {
        $event = $this->createResponseEvent(
            resourceClass: UserAuthenticationResource::class,
            statusCode: 401,
        );

        $this->listener->onKernelResponse($event);

        self::assertStringNotContainsString('no-store', (string) $event->getResponse()->headers->get('Cache-Control'));
    }

    private function createResponseEvent(?string $resourceClass, int $statusCode): ResponseEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/tokens', 'POST');

        if ($resourceClass !== null) {
            $request->attributes->set('_api_resource_class', $resourceClass);
        }

        $response = new Response(status: $statusCode);

        return new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }
}

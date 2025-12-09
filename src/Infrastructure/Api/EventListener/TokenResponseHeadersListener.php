<?php

declare(strict_types=1);

namespace App\Infrastructure\Api\EventListener;

use App\Infrastructure\Api\Resource\UserAuthenticationResource;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds RFC 6749 required headers to token responses.
 *
 * Per OAuth2 spec (RFC 6749 Section 5.1), token responses must include
 * Cache-Control: no-store to prevent caching of sensitive tokens.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class TokenResponseHeadersListener
{
    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if ($request->attributes->get('_api_resource_class') !== UserAuthenticationResource::class) {
            return;
        }

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            return;
        }

        $response->headers->set('Cache-Control', 'no-store');
    }
}

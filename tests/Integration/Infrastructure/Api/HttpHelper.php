<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Api;

/**
 * Shared HTTP utilities for API integration tests.
 *
 * @internal
 */
trait HttpHelper
{
    /**
     * @param array<string, mixed>|string $payload Array to JSON-encode, or raw string body
     */
    private function postJson(string $uri, array|string $payload): void
    {
        $body = is_array($payload)
            ? json_encode($payload, JSON_THROW_ON_ERROR)
            : $payload;

        $this->client->request('POST', $uri, server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: $body);
    }

    /**
     * @return array<mixed>
     */
    private function getJsonResponse(): array
    {
        $content = $this->client->getResponse()->getContent();
        assert($content !== false);

        $data = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        assert(is_array($data));

        return $data;
    }
}

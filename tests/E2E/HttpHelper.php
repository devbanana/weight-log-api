<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

/**
 * Shared HTTP utilities for E2E test contexts.
 *
 * @internal
 */
trait HttpHelper
{
    /**
     * @param array<string, mixed> $data
     */
    private function makeJsonRequest(string $method, string $uri, array $data): Response
    {
        $request = Request::create(
            uri: $uri,
            method: $method,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode($data, JSON_THROW_ON_ERROR)
        );

        return $this->kernel->handle($request);
    }

    private static function assertResponseStatusCode(?Response $response, int $expected, string $description): void
    {
        Assert::notNull($response, 'No response received');
        Assert::same(
            $response->getStatusCode(),
            $expected,
            sprintf('Expected %d %s. Response: %s', $expected, $description, self::getResponseContent($response))
        );
    }

    private static function getResponseContent(?Response $response): string
    {
        $content = $response?->getContent();

        if ($content === false || $content === null) {
            return 'No content';
        }

        return $content;
    }

    private static function assertResponseContainsToken(?Response $response): void
    {
        $content = self::getResponseContent($response);
        $data = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        assert(is_array($data));
        Assert::keyExists($data, 'token', 'Response should contain a token');
        Assert::notEmpty($data['token'], 'Token should not be empty');
    }
}

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
            content: json_encode($data, JSON_THROW_ON_ERROR),
        );

        return $this->kernel->handle($request);
    }

    private static function assertResponseStatusCode(?Response $response, int $expected, string $description): void
    {
        Assert::notNull($response, 'No response received');
        Assert::same(
            $response->getStatusCode(),
            $expected,
            sprintf('Expected %d %s. Response: %s', $expected, $description, self::getResponseContent($response)),
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

        // OAuth2-style response (RFC 6749)
        Assert::keyExists($data, 'access_token', 'Response should contain access_token');
        Assert::notEmpty($data['access_token'], 'access_token should not be empty');
        Assert::keyExists($data, 'token_type', 'Response should contain token_type');
        Assert::same($data['token_type'], 'Bearer', 'token_type should be Bearer');
        Assert::keyExists($data, 'expires_in', 'Response should contain expires_in');
        Assert::integer($data['expires_in'], 'expires_in should be an integer');
        Assert::greaterThan($data['expires_in'], 0, 'expires_in should be positive');
        Assert::keyExists($data, 'expires_at', 'Response should contain expires_at');
        Assert::string($data['expires_at'], 'expires_at should be a string (ISO 8601)');

        // Verify expires_at is a valid ISO 8601 date in the future
        $expiresAt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $data['expires_at']);
        Assert::isInstanceOf($expiresAt, \DateTimeImmutable::class, 'expires_at should be a valid ISO 8601 date');
        Assert::greaterThan($expiresAt->getTimestamp(), time(), 'expires_at should be in the future');
    }
}

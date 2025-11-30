<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

use Webmozart\Assert\Assert;

/**
 * User identifier value object.
 */
final readonly class UserId implements \Stringable
{
    private function __construct(
        private string $value,
    ) {
        Assert::uuid($value);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function asString(): string
    {
        return $this->value;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->asString();
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

use Webmozart\Assert\Assert;

final readonly class PlainPassword
{
    private function __construct(
        private string $value,
    ) {
        Assert::notEmpty($this->value, 'Password cannot be empty');

        if (mb_trim($this->value) === '') {
            throw new \InvalidArgumentException('Password cannot contain only whitespace');
        }

        Assert::minLength($this->value, 8, 'Password must be at least 8 characters');
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function asString(): string
    {
        return $this->value;
    }
}

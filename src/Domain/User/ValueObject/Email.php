<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

use Webmozart\Assert\Assert;

final readonly class Email implements \Stringable
{
    private function __construct(
        private string $value
    ) {
        Assert::notEmpty($this->value, 'Email cannot be empty');
        Assert::email($this->value, 'Invalid email address');
    }

    public static function fromString(string $value): self
    {
        return new self(trim(strtolower($value)));
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

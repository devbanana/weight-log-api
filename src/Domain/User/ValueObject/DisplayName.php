<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

use Webmozart\Assert\Assert;

final readonly class DisplayName
{
    private function __construct(
        private string $value,
    ) {
        Assert::notEmpty($this->value, 'Display name cannot be empty');
        Assert::maxLength($this->value, 50, 'Display name cannot exceed 50 characters');
    }

    public static function fromString(string $value): self
    {
        return new self(mb_trim($value));
    }
}

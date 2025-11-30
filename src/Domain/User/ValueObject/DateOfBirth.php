<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

final readonly class DateOfBirth
{
    private function __construct(
        private string $value
    ) {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $this->value);

        if ($date === false || $date->format('Y-m-d') !== $this->value) {
            throw new \InvalidArgumentException('Invalid date format. Expected Y-m-d format.');
        }
    }

    public static function fromString(string $value): self
    {
        return new self(trim($value));
    }

    public static function fromDateTime(\DateTimeImmutable $dateTime): self
    {
        return new self($dateTime->format('Y-m-d'));
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

final readonly class DateOfBirth
{
    private function __construct(
        private string $value,
    ) {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $this->value);

        if ($date === false || $date->format('Y-m-d') !== $this->value) {
            throw new \InvalidArgumentException('Invalid date format. Expected Y-m-d format.');
        }
    }

    public static function fromString(string $value): self
    {
        return new self(mb_trim($value));
    }

    public function asString(): string
    {
        return $this->value;
    }

    public function asDateTime(): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->value, new \DateTimeZone('UTC'));
        assert($date instanceof \DateTimeImmutable);

        return $date;
    }

    public function calculateAgeAt(\DateTimeImmutable $referenceDate): int
    {
        return $this->asDateTime()->diff($referenceDate)->y;
    }

    public function isAfter(\DateTimeImmutable $referenceDate): bool
    {
        return $this->asDateTime() > $referenceDate;
    }
}

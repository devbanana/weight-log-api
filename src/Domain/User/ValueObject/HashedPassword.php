<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

use Webmozart\Assert\Assert;

final readonly class HashedPassword
{
    private function __construct(
        private string $hash
    ) {
        Assert::notEmpty($this->hash, 'Hash cannot be empty');
    }

    public static function fromHash(string $hash): self
    {
        return new self($hash);
    }

    public function verify(PlainPassword $plainPassword): bool
    {
        return password_verify($plainPassword->asString(), $this->hash);
    }
}

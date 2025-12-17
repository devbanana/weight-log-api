<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\User\ValueObject;

use App\Domain\User\ValueObject\DateOfBirth;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DateOfBirth::class)]
final class DateOfBirthTest extends TestCase
{
    public function testItCanBeConvertedFromValidDateString(): void
    {
        $dateOfBirth = DateOfBirth::fromString('1990-05-15');

        self::assertInstanceOf(DateOfBirth::class, $dateOfBirth);
    }

    #[DataProvider('provideItFailsWithInvalidDateStringCases')]
    public function testItFailsWithInvalidDateString(string $invalidDate): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DateOfBirth::fromString($invalidDate);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideItFailsWithInvalidDateStringCases(): iterable
    {
        yield 'not a date' => ['not-a-date'];

        yield 'US date format' => ['05/15/1990'];

        yield 'invalid date' => ['1990-02-30'];

        yield 'empty date' => [''];
    }

    #[DataProvider('provideItAcceptsValidDatesCases')]
    public function testItAcceptsValidDates(string $validDate): void
    {
        $dateOfBirth = DateOfBirth::fromString($validDate);

        self::assertInstanceOf(DateOfBirth::class, $dateOfBirth);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideItAcceptsValidDatesCases(): iterable
    {
        yield 'standard date' => ['1990-05-15'];

        yield 'leap year date' => ['2000-02-29'];

        yield 'first day of year' => ['1985-01-01'];

        yield 'last day of year' => ['1985-12-31'];

        yield 'recent date' => ['2020-06-15'];

        yield 'old date' => ['1920-03-10'];

        yield 'date with spaces' => [' 1990-05-15 '];
    }

    public function testItReturnsStringRepresentation(): void
    {
        $dateOfBirth = DateOfBirth::fromString('1990-05-15');

        self::assertSame('1990-05-15', $dateOfBirth->asString());
    }

    public function testItConvertsToDateTimeImmutable(): void
    {
        $dateOfBirth = DateOfBirth::fromString('1990-05-15');

        $dateTime = $dateOfBirth->asDateTime();

        self::assertSame('1990-05-15', $dateTime->format('Y-m-d'));
    }

    public function testItReturnsDateTimeAtMidnightWithZeroMicroseconds(): void
    {
        $dateOfBirth = DateOfBirth::fromString('1990-05-15');

        $dateTime = $dateOfBirth->asDateTime();

        self::assertSame('00:00:00.000000', $dateTime->format('H:i:s.u'));
    }

    #[DataProvider('provideItCalculatesAgeCorrectlyCases')]
    public function testItCalculatesAgeCorrectly(
        string $dateOfBirth,
        string $referenceDate,
        int $expectedAge,
    ): void {
        $dob = DateOfBirth::fromString($dateOfBirth);
        $reference = new \DateTimeImmutable($referenceDate);

        self::assertSame($expectedAge, $dob->calculateAgeAt($reference));
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function provideItCalculatesAgeCorrectlyCases(): iterable
    {
        // Reference date: 2025-12-12 (matches MockClock in tests)
        yield 'simple age calculation' => ['1990-05-15', '2025-12-12', 35];

        yield 'birthday today' => ['2007-12-12', '2025-12-12', 18];

        yield 'birthday tomorrow (not yet 18)' => ['2007-12-13', '2025-12-12', 17];

        yield 'birthday yesterday' => ['2007-12-11', '2025-12-12', 18];

        yield 'born on leap day, reference is non-leap year' => ['2000-02-29', '2025-12-12', 25];

        yield 'very young' => ['2020-06-15', '2025-12-12', 5];

        yield 'very old' => ['1920-01-01', '2025-12-12', 105];
    }

    public function testIsAfterReturnsTrueWhenDateOfBirthIsAfterReference(): void
    {
        $dateOfBirth = DateOfBirth::fromString('2030-01-01');
        $reference = new \DateTimeImmutable('2025-12-12');

        self::assertTrue($dateOfBirth->isAfter($reference));
    }

    public function testIsAfterReturnsFalseWhenDateOfBirthIsBeforeReference(): void
    {
        $dateOfBirth = DateOfBirth::fromString('1990-05-15');
        $reference = new \DateTimeImmutable('2025-12-12');

        self::assertFalse($dateOfBirth->isAfter($reference));
    }

    public function testIsAfterReturnsFalseWhenDateOfBirthIsSameAsReference(): void
    {
        $dateOfBirth = DateOfBirth::fromString('2025-12-12');
        $reference = new \DateTimeImmutable('2025-12-12');

        self::assertFalse($dateOfBirth->isAfter($reference));
    }
}

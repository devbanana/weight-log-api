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

    public function testItCanBeCreatedFromDateTimeImmutable(): void
    {
        $dateTime = new \DateTimeImmutable('1990-05-15');

        $dateOfBirth = DateOfBirth::fromDateTime($dateTime);

        self::assertInstanceOf(DateOfBirth::class, $dateOfBirth);
    }

    public function testItExtractsDateOnlyFromDateTimeWithTime(): void
    {
        $dateTimeWithTime = new \DateTimeImmutable('1990-05-15 14:30:00');

        $dateOfBirth = DateOfBirth::fromDateTime($dateTimeWithTime);

        self::assertInstanceOf(DateOfBirth::class, $dateOfBirth);
    }
}

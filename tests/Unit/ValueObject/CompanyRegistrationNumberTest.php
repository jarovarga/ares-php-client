<?php

declare(strict_types=1);

namespace AresApi\Tests\Unit\ValueObject;

use AresApi\Exception\ValidationException;
use AresApi\ValueObject\CompanyRegistrationNumber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompanyRegistrationNumber::class)]
final class CompanyRegistrationNumberTest extends TestCase
{
    public function testItPreservesLeadingZeroesAndConvertsToString(): void
    {
        $number = new CompanyRegistrationNumber('01234567');

        self::assertSame('01234567', $number->value());
        self::assertSame('01234567', (string) $number);
    }

    public function testItComparesNumbersByValue(): void
    {
        $number = new CompanyRegistrationNumber('27074358');

        self::assertTrue($number->equals(new CompanyRegistrationNumber('27074358')));
        self::assertFalse($number->equals(new CompanyRegistrationNumber('01234567')));
    }

    #[DataProvider('invalidNumberProvider')]
    public function testItRejectsAnInvalidNumber(string $value): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageIsOrContains('exactly eight digits');

        new CompanyRegistrationNumber($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNumberProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'too short' => ['1234567'];
        yield 'too long' => ['123456789'];
        yield 'letters' => ['1234567A'];
        yield 'whitespace' => [' 27074358'];
        yield 'unicode digits' => ['１２３４５６７８'];
    }
}

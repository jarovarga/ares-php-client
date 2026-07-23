<?php

declare(strict_types=1);

namespace AresApi\Tests\Unit\Company;

use AresApi\Company\DTO\Company;
use AresApi\Company\DTO\LegalForm;
use AresApi\Company\Mapper\AddressMapper;
use AresApi\Company\Mapper\CompanyMapper;
use AresApi\Exception\InvalidResponseException;
use AresApi\Exception\ValidationException;
use AresApi\ValueObject\CompanyRegistrationNumber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Company::class)]
#[CoversClass(LegalForm::class)]
#[CoversClass(CompanyMapper::class)]
final class CompanyMapperTest extends TestCase
{
    public function testItMapsACompleteCompanyPayload(): void
    {
        $company = $this->mapper()->map([
            'icoId' => '27074358',
            'ico' => '27074358',
            'obchodniJmeno' => 'Asseco Central Europe, a.s.',
            'sidlo' => [
                'nazevObce' => 'Praha',
                'textovaAdresa' => 'Budějovická 778/3a, Praha 4',
            ],
            'pravniForma' => '121',
            'pravniFormaRos' => '121',
            'dic' => 'CZ27074358',
            'financniUrad' => '004',
            'datumVzniku' => '2003-08-06',
            'datumAktualizace' => '2026-03-08',
            'primarniZdroj' => 'ros',
            'czNace' => ['620', '63110'],
            'czNace2008' => ['620'],
        ]);

        self::assertSame('27074358', $company->aresId());
        self::assertSame('27074358', $company->registrationNumber()?->value());
        self::assertSame('Asseco Central Europe, a.s.', $company->businessName());
        self::assertSame('Praha', $company->registeredOffice()?->municipalityName());
        self::assertSame(
            'Budějovická 778/3a, Praha 4',
            $company->registeredOffice()?->formattedAddress(),
        );
        self::assertSame('121', $company->legalForm()?->code());
        self::assertSame('121', $company->legalForm()?->rosCode());
        self::assertSame('CZ27074358', $company->taxIdentificationNumber());
        self::assertSame('004', $company->taxOfficeCode());
        self::assertSame('2003-08-06', $company->establishedOn()?->format('Y-m-d'));
        self::assertNull($company->dissolvedOn());
        self::assertSame('2026-03-08', $company->updatedOn()?->format('Y-m-d'));
        self::assertSame('ros', $company->primarySource());
        self::assertSame(['620', '63110'], $company->czNaceCodes());
        self::assertSame(['620'], $company->czNace2008Codes());
        self::assertFalse($company->hasDissolutionDate());
    }

    public function testItMapsAnAresSubjectWithoutARegistrationNumber(): void
    {
        $company = $this->mapper()->map([
            'icoId' => 'ARES_12345678',
            'obchodniJmeno' => 'Subject without a registration number',
            'datumZaniku' => '2020-01-01',
        ]);

        self::assertSame('ARES_12345678', $company->aresId());
        self::assertNull($company->registrationNumber());
        self::assertTrue($company->hasDissolutionDate());
    }

    public function testItDerivesTheRegistrationNumberFromAnUnprefixedAresId(): void
    {
        $company = $this->mapper()->map(['icoId' => '01234567']);

        self::assertSame('01234567', $company->registrationNumber()?->value());
    }

    public function testItRequiresAnAresIdentifierOrRegistrationNumber(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessageIsOrContains('does not contain "icoId" or "ico"');

        $this->mapper()->map(['obchodniJmeno' => 'Missing identifier']);
    }

    public function testItRejectsAnInvalidDate(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessageIsOrContains('valid date in Y-m-d');

        $this->mapper()->map([
            'ico' => '27074358',
            'datumVzniku' => '2025-02-30',
        ]);
    }

    public function testItRejectsACorruptDateContainingANullByte(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessageIsOrContains('valid date in Y-m-d');

        $this->mapper()->map([
            'ico' => '27074358',
            'datumVzniku' => "2025-01-01\0",
        ]);
    }

    public function testItRejectsACompanyFieldWithAnUnexpectedType(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessageIsOrContains('"obchodniJmeno" must be string or null');

        $this->mapper()->map([
            'ico' => '27074358',
            'obchodniJmeno' => 123,
        ]);
    }

    public function testItRejectsAListContainingANonString(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessageIsOrContains('"czNace" must be a list of strings');

        $this->mapper()->map([
            'ico' => '27074358',
            'czNace' => ['620', 123],
        ]);
    }

    #[DataProvider('activityCodeListProvider')]
    public function testItRejectsAnExplicitNullActivityCodeList(
        string $field,
    ): void {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessageIsOrContains(sprintf('"%s" must be a list of strings', $field));

        $this->mapper()->map([
            'ico' => '27074358',
            $field => null,
        ]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function activityCodeListProvider(): iterable
    {
        yield 'CZ-NACE' => ['czNace'];
        yield 'CZ-NACE 2008' => ['czNace2008'];
    }

    public function testItTreatsMissingActivityCodeListsAsEmpty(): void
    {
        $company = $this->mapper()->map(['ico' => '27074358']);

        self::assertSame([], $company->czNaceCodes());
        self::assertSame([], $company->czNace2008Codes());
    }

    public function testItMapsDateOnlyValuesAtMidnightUtc(): void
    {
        $originalTimezone = date_default_timezone_get();
        date_default_timezone_set('Pacific/Auckland');

        try {
            $company = $this->mapper()->map([
                'ico' => '27074358',
                'datumVzniku' => '2003-08-06',
            ]);
        } finally {
            date_default_timezone_set($originalTimezone);
        }

        self::assertSame(
            '2003-08-06T00:00:00+00:00',
            $company->establishedOn()?->format('c'),
        );
        self::assertSame('UTC', $company->establishedOn()?->getTimezone()->getName());
    }

    public function testItWrapsMismatchedPayloadIdentity(): void
    {
        try {
            $this->mapper()->map([
                'icoId' => '11111111',
                'ico' => '22222222',
            ]);
            self::fail('An invalid response exception was not thrown.');
        } catch (InvalidResponseException $exception) {
            self::assertInstanceOf(
                ValidationException::class,
                $exception->getPrevious(),
            );
            self::assertStringContainsString(
                'must match',
                $exception->getMessage(),
            );
        }
    }

    public function testCompanyDeduplicatesActivityCodes(): void
    {
        $company = new Company(
            aresId: '27074358',
            czNaceCodes: ['620', '620', '63110'],
        );

        self::assertSame(['620', '63110'], $company->czNaceCodes());
    }

    public function testCompanyRejectsAnInvalidAresId(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageIsOrContains('ARES identifier');

        new Company(aresId: 'invalid');
    }

    #[DataProvider('inconsistentIdentityProvider')]
    public function testCompanyRejectsInconsistentIdentity(
        string $aresId,
        string $registrationNumber,
    ): void {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageIsOrContains('must match');

        new Company(
            aresId: $aresId,
            registrationNumber: new CompanyRegistrationNumber(
                $registrationNumber,
            ),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function inconsistentIdentityProvider(): iterable
    {
        yield 'different registration number' => ['11111111', '22222222'];
        yield 'registration number with prefixed ARES ID' => [
            'ARES_11111111',
            '11111111',
        ];
    }

    public function testLegalFormRequiresAtLeastOneValidCode(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageIsOrContains('At least one');

        new LegalForm(null);
    }

    private function mapper(): CompanyMapper
    {
        return new CompanyMapper(new AddressMapper());
    }
}

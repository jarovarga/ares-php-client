<?php

declare(strict_types=1);

namespace AresApi\Tests\Unit\Company;

use AresApi\Company\Query\CompanySearchQuery;
use AresApi\Company\Request\GetCompanyRequest;
use AresApi\Company\Request\SearchCompaniesRequest;
use AresApi\Exception\ValidationException;
use AresApi\Pagination\PageRequest;
use AresApi\ValueObject\CompanyRegistrationNumber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(CompanySearchQuery::class)]
#[CoversClass(GetCompanyRequest::class)]
#[CoversClass(SearchCompaniesRequest::class)]
final class CompanySearchQueryTest extends TestCase
{
    public function testItNormalizesAndDeduplicatesSearchCriteria(): void
    {
        $registrationNumber = new CompanyRegistrationNumber('27074358');
        $page = new PageRequest(number: 2, size: 25);

        $query = new CompanySearchQuery(
            registrationNumbers: [$registrationNumber, $registrationNumber],
            businessName: '  Škoda Auto  ',
            addressText: '  Mladá Boleslav  ',
            legalFormCodes: ['121', '121'],
            rosLegalFormCodes: ['301'],
            taxOfficeCodes: ['004'],
            czNaceCodes: ['29100', '29100'],
            page: $page,
        );

        self::assertSame([$registrationNumber], $query->registrationNumbers());
        self::assertSame('Škoda Auto', $query->businessName());
        self::assertSame('Mladá Boleslav', $query->addressText());
        self::assertSame(['121'], $query->legalFormCodes());
        self::assertSame(['301'], $query->rosLegalFormCodes());
        self::assertSame(['004'], $query->taxOfficeCodes());
        self::assertSame(['29100'], $query->czNaceCodes());
        self::assertSame($page, $query->page());
    }

    public function testSearchRequestTranslatesThePublicQueryToAresFields(): void
    {
        $request = new SearchCompaniesRequest(new CompanySearchQuery(
            registrationNumbers: [new CompanyRegistrationNumber('27074358')],
            businessName: 'Škoda',
            addressText: 'Praha 4',
            legalFormCodes: ['121'],
            rosLegalFormCodes: ['301'],
            taxOfficeCodes: ['004'],
            czNaceCodes: ['620'],
            page: new PageRequest(number: 3, size: 10),
        ));

        self::assertSame('POST', $request->method());
        self::assertSame('/ekonomicke-subjekty/vyhledat', $request->path());
        self::assertSame([
            'start' => 20,
            'pocet' => 10,
            'ico' => ['27074358'],
            'obchodniJmeno' => 'Škoda',
            'sidlo' => [
                'textovaAdresa' => 'Praha 4',
            ],
            'pravniForma' => ['121'],
            'pravniFormaRos' => ['301'],
            'financniUrad' => ['004'],
            'czNace' => ['620'],
        ], $request->json());
    }

    public function testSearchRequestOmitsUnusedCriteria(): void
    {
        $request = new SearchCompaniesRequest(new CompanySearchQuery(
            businessName: 'Asseco',
        ));

        self::assertSame([
            'start' => 0,
            'pocet' => 20,
            'obchodniJmeno' => 'Asseco',
        ], $request->json());
    }

    public function testGetRequestUsesTheRegistrationNumberInItsPath(): void
    {
        $request = new GetCompanyRequest(
            new CompanyRegistrationNumber('27074358'),
        );

        self::assertSame('GET', $request->method());
        self::assertSame('/ekonomicke-subjekty/27074358', $request->path());
        self::assertNull($request->json());
    }

    public function testItRequiresAtLeastOneCriterion(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageIsOrContains('At least one');

        new CompanySearchQuery();
    }

    public function testItRejectsMoreThanOneHundredRegistrationNumbers(): void
    {
        $numbers = [];
        for ($value = 1; $value <= 101; ++$value) {
            $numbers[] = new CompanyRegistrationNumber(
                str_pad((string) $value, 8, '0', STR_PAD_LEFT),
            );
        }

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageIsOrContains('at most 100');

        new CompanySearchQuery(
            registrationNumbers: $numbers,
        );
    }

    public function testItAppliesTheLimitAfterDeduplicatingNumbers(): void
    {
        $number = new CompanyRegistrationNumber('27074358');

        $query = new CompanySearchQuery(
            registrationNumbers: array_fill(0, 101, $number),
        );

        self::assertSame([$number], $query->registrationNumbers());
    }

    public function testItRejectsARegistrationNumberOfTheWrongType(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageIsOrContains('CompanyRegistrationNumber');

        (new ReflectionClass(CompanySearchQuery::class))->newInstanceArgs([
            ['27074358'],
        ]);
    }

    public function testItRejectsAnInvalidCode(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageIsOrContains('legal-form');

        new CompanySearchQuery(legalFormCodes: ['12']);
    }

    public function testItRejectsBlankText(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageIsOrContains('must not be empty');

        new CompanySearchQuery(businessName: '   ');
    }
}

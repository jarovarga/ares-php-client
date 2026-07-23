<?php

declare(strict_types=1);

namespace AresApi\Tests\Unit\Company;

use AresApi\Company\DTO\Company;
use AresApi\Company\Mapper\AddressMapper;
use AresApi\Company\Mapper\CompanyMapper;
use AresApi\Company\Mapper\CompanySearchResultMapper;
use AresApi\Company\Result\CompanySearchResult;
use AresApi\Exception\InvalidResponseException;
use AresApi\Exception\ValidationException;
use AresApi\Pagination\PageInfo;
use AresApi\Pagination\PageRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

#[CoversClass(CompanySearchResultMapper::class)]
#[CoversClass(CompanySearchResult::class)]
final class CompanySearchResultMapperTest extends TestCase
{
    public function testItMapsCompaniesAndPagination(): void
    {
        $result = $this->mapper()->map([
            'pocetCelkem' => 41,
            'ekonomickeSubjekty' => [
                [
                    'ico' => '27074358',
                    'obchodniJmeno' => 'First company',
                ],
                [
                    'icoId' => 'ARES_12345678',
                    'obchodniJmeno' => 'Second subject',
                ],
            ],
        ], new PageRequest(number: 2, size: 20));

        self::assertCount(2, $result);
        self::assertFalse($result->isEmpty());
        self::assertSame('First company', $result->items()[0]->businessName());
        $result
        |> iterator_to_array(...)
        |> (fn ($x) => array_map(static fn (Company $company): ?string => $company->businessName(), $x))
        |> (fn ($x) => self::assertSame(['First company', 'Second subject'], $x));
        self::assertSame(2, $result->pageInfo()->currentPage());
        self::assertSame(20, $result->pageInfo()->pageSize());
        self::assertSame(41, $result->pageInfo()->totalItems());
        self::assertSame(3, $result->pageInfo()->totalPages());
    }

    public function testItMapsAnEmptyResult(): void
    {
        $result = $this->mapper()->map([
            'pocetCelkem' => 0,
            'ekonomickeSubjekty' => [],
        ], new PageRequest());

        self::assertTrue($result->isEmpty());
        self::assertCount(0, $result);
        self::assertSame([], $result->items());
    }

    #[DataProvider('invalidSearchPayloadProvider')]
    public function testItRejectsInvalidSearchMetadata(
        array $payload,
        string $message,
    ): void {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->mapper()->map($payload, new PageRequest());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidSearchPayloadProvider(): iterable
    {
        yield 'missing total' => [[
            'ekonomickeSubjekty' => [],
        ], 'pocetCelkem'];

        yield 'negative total' => [[
            'pocetCelkem' => -1,
            'ekonomickeSubjekty' => [],
        ], 'non-negative integer'];

        yield 'records are not a list' => [[
            'pocetCelkem' => 1,
            'ekonomickeSubjekty' => ['company' => []],
        ], 'must be a list'];

        yield 'record is not an object' => [[
            'pocetCelkem' => 1,
            'ekonomickeSubjekty' => ['invalid'],
        ], 'index 0 must be an object'];
    }

    public function testItAddsTheRecordIndexToAMappingFailure(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessageIsOrContains('company at index 1 is invalid');

        $this->mapper()->map([
            'pocetCelkem' => 2,
            'ekonomickeSubjekty' => [
                ['ico' => '27074358'],
                ['obchodniJmeno' => 'Missing identifier'],
            ],
        ], new PageRequest());
    }

    public function testSearchResultRejectsANonCompanyItem(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageIsOrContains('Company instance');

        (new ReflectionClass(CompanySearchResult::class))->newInstanceArgs([
            [new stdClass()],
            new PageInfo(1, 20, 1),
        ]);
    }

    public function testSearchResultRejectsAnAssociativeItemsArray(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageIsOrContains('provided as a list');

        new CompanySearchResult(
            ['company' => new Company(aresId: '27074358')],
            new PageInfo(1, 20, 1),
        );
    }

    private function mapper(): CompanySearchResultMapper
    {
        return new CompanySearchResultMapper(
            new CompanyMapper(new AddressMapper()),
        );
    }
}

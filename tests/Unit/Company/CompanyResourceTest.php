<?php

declare(strict_types=1);

namespace AresApi\Tests\Unit\Company;

use AresApi\Company\CompanyResource;
use AresApi\Company\Mapper\AddressMapper;
use AresApi\Company\Mapper\CompanyMapper;
use AresApi\Company\Mapper\CompanySearchResultMapper;
use AresApi\Company\Query\CompanySearchQuery;
use AresApi\Company\Request\GetCompanyRequest;
use AresApi\Company\Request\SearchCompaniesRequest;
use AresApi\Http\ApiRequestInterface;
use AresApi\Http\ApiTransportInterface;
use AresApi\Pagination\PageRequest;
use AresApi\ValueObject\CompanyRegistrationNumber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompanyResource::class)]
final class CompanyResourceTest extends TestCase
{
    public function testItGetsAndMapsACompanyThroughTheTransport(): void
    {
        $transport = $this->createMock(ApiTransportInterface::class);
        $transport
            ->expects(self::once())
            ->method('execute')
            ->with(self::callback(static function (
                ApiRequestInterface $request,
            ): bool {
                self::assertInstanceOf(GetCompanyRequest::class, $request);
                self::assertSame('GET', $request->method());
                self::assertSame(
                    '/ekonomicke-subjekty/27074358',
                    $request->path(),
                );

                return true;
            }))
            ->willReturn([
                'ico' => '27074358',
                'obchodniJmeno' => 'Asseco Central Europe, a.s.',
            ]);

        $company = $this->resource($transport)->get(
            new CompanyRegistrationNumber('27074358'),
        );

        self::assertSame('27074358', $company->registrationNumber()?->value());
        self::assertSame('Asseco Central Europe, a.s.', $company->businessName());
    }

    public function testItSearchesAndMapsPaginationThroughTheTransport(): void
    {
        $query = new CompanySearchQuery(
            businessName: 'Asseco',
            page: new PageRequest(number: 2, size: 10),
        );
        $transport = $this->createMock(ApiTransportInterface::class);
        $transport
            ->expects(self::once())
            ->method('execute')
            ->with(self::callback(static function (
                ApiRequestInterface $request,
            ): bool {
                self::assertInstanceOf(SearchCompaniesRequest::class, $request);
                self::assertSame([
                    'start' => 10,
                    'pocet' => 10,
                    'obchodniJmeno' => 'Asseco',
                ], $request->json());

                return true;
            }))
            ->willReturn([
                'pocetCelkem' => 11,
                'ekonomickeSubjekty' => [
                    [
                        'ico' => '27074358',
                        'obchodniJmeno' => 'Asseco Central Europe, a.s.',
                    ],
                ],
            ]);

        $result = $this->resource($transport)->search($query);

        self::assertCount(1, $result);
        self::assertSame(
            'Asseco Central Europe, a.s.',
            $result->items()[0]->businessName(),
        );
        self::assertSame(2, $result->pageInfo()->currentPage());
        self::assertSame(11, $result->pageInfo()->totalItems());
        self::assertFalse($result->pageInfo()->hasNextPage());
    }

    private function resource(
        ApiTransportInterface $transport,
    ): CompanyResource {
        $companyMapper = new CompanyMapper(new AddressMapper());

        return new CompanyResource(
            $transport,
            $companyMapper,
            new CompanySearchResultMapper($companyMapper),
        );
    }
}

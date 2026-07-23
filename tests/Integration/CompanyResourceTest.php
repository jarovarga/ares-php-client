<?php

declare(strict_types=1);

namespace AresApi\Tests\Integration;

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
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(CompanyResource::class)]
final class CompanyResourceTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testDetailAndSearchFlowThroughTheResourceBoundary(): void
    {
        $detail = $this->fixture('company-detail.json');
        $search = $this->fixture('company-search.json');

        $transport = new readonly class ($detail, $search) implements ApiTransportInterface {
            /**
             * @param array<string, mixed> $detail
             * @param array<string, mixed> $search
             */
            public function __construct(
                private array $detail,
                private array $search,
            ) {
            }

            public function execute(ApiRequestInterface $request): array
            {
                return match ($request::class) {
                    GetCompanyRequest::class => $this->detail,
                    SearchCompaniesRequest::class => $this->search,
                    default => throw new RuntimeException('Unexpected request.'),
                };
            }
        };

        $companyMapper = new CompanyMapper(new AddressMapper());
        $resource = new CompanyResource(
            $transport,
            $companyMapper,
            new CompanySearchResultMapper($companyMapper),
        );

        $company = $resource->get(
            new CompanyRegistrationNumber('27074358'),
        );
        $result = $resource->search(
            new CompanySearchQuery(
                businessName: 'Asseco',
                page: new PageRequest(1, 2),
            ),
        );

        self::assertSame('27074358', $company->registrationNumber()?->value());
        self::assertSame('Asseco Central Europe, a.s.', $company->businessName());
        self::assertSame('Praha', $company->registeredOffice()?->municipalityName());
        self::assertCount(2, $result);
        self::assertSame(2, $result->pageInfo()->totalItems());
        self::assertNull($result->items()[1]->registrationNumber());
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function fixture(string $name): array
    {
        $contents = file_get_contents(
            dirname(__DIR__) . '/Fixture/' . $name,
        );

        if ($contents === false) {
            self::fail(sprintf('Fixture "%s" could not be read.', $name));
        }

        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}

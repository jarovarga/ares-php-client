<?php

declare(strict_types=1);

namespace AresApi\Company;

use AresApi\Company\DTO\Company;
use AresApi\Company\Mapper\CompanyMapperInterface;
use AresApi\Company\Mapper\CompanySearchResultMapperInterface;
use AresApi\Company\Query\CompanySearchQuery;
use AresApi\Company\Request\GetCompanyRequest;
use AresApi\Company\Request\SearchCompaniesRequest;
use AresApi\Company\Result\CompanySearchResult;
use AresApi\Exception\ApiException;
use AresApi\Exception\InvalidResponseException;
use AresApi\Exception\NotFoundException;
use AresApi\Exception\RateLimitException;
use AresApi\Exception\RequestException;
use AresApi\Exception\TransportException;
use AresApi\Http\ApiTransportInterface;
use AresApi\ValueObject\CompanyRegistrationNumber;

/**
 * Represents a resource for interacting with company data via an API.
 */
final readonly class CompanyResource implements CompanyResourceInterface
{
    /**
     * Constructor method to initialise dependencies.
     *
     * @param ApiTransportInterface $transport Interface for API transport layer.
     * @param CompanyMapperInterface $companyMapper Interface for mapping company data.
     * @param CompanySearchResultMapperInterface $searchResultMapper Interface for mapping search result data.
     */
    public function __construct(
        private ApiTransportInterface $transport,
        private CompanyMapperInterface $companyMapper,
        private CompanySearchResultMapperInterface $searchResultMapper,
    ) {
    }

    /**
     * Retrieves a Company entity using the provided registration number.
     *
     * @param CompanyRegistrationNumber $registrationNumber The registration number of the company to retrieve.
     *
     * @return Company The Company entity corresponding to the given registration number.
     *
     * @throws RequestException If the HTTP request cannot be constructed.
     * @throws TransportException If the HTTP request cannot be completed.
     * @throws NotFoundException If ARES returns HTTP 404.
     * @throws RateLimitException If ARES returns HTTP 429.
     * @throws ApiException If ARES returns another non-successful HTTP response.
     * @throws InvalidResponseException If the response cannot be decoded or mapped.
     */
    public function get(CompanyRegistrationNumber $registrationNumber): Company
    {
        $data = $this->transport->execute(
            new GetCompanyRequest($registrationNumber),
        );

        return $this->companyMapper->map($data);
    }

    /**
     * Searches for companies based on the given query parameters.
     *
     * @param CompanySearchQuery $query The search query containing filters and parameters.
     *
     * @return CompanySearchResult The result of the company search, including any matching companies.
     *
     * @throws RequestException If the HTTP request cannot be constructed.
     * @throws TransportException If the HTTP request cannot be completed.
     * @throws NotFoundException If ARES returns HTTP 404.
     * @throws RateLimitException If ARES returns HTTP 429.
     * @throws ApiException If ARES returns another non-successful HTTP response.
     * @throws InvalidResponseException If the response cannot be decoded or mapped.
     */
    public function search(CompanySearchQuery $query): CompanySearchResult
    {
        $data = $this->transport->execute(
            new SearchCompaniesRequest($query),
        );

        return $this->searchResultMapper->map($data, $query->page());
    }
}

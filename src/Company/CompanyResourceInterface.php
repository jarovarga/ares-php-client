<?php

declare(strict_types=1);

namespace AresApi\Company;

use AresApi\Company\DTO\Company;
use AresApi\Company\Query\CompanySearchQuery;
use AresApi\Company\Result\CompanySearchResult;
use AresApi\Exception\ApiException;
use AresApi\Exception\InvalidResponseException;
use AresApi\Exception\NotFoundException;
use AresApi\Exception\RateLimitException;
use AresApi\Exception\RequestException;
use AresApi\Exception\TransportException;
use AresApi\ValueObject\CompanyRegistrationNumber;

/**
 * Interface for interacting with company data.
 */
interface CompanyResourceInterface
{
    /**
     * Retrieves a Company entity based on the provided registration number.
     *
     * @param CompanyRegistrationNumber $registrationNumber The registration number of the company to retrieve.
     *
     * @return Company The company associated with the given registration number.
     *
     * @throws RequestException If the HTTP request cannot be constructed.
     * @throws TransportException If the HTTP request cannot be completed.
     * @throws NotFoundException If ARES returns HTTP 404.
     * @throws RateLimitException If ARES returns HTTP 429.
     * @throws ApiException If ARES returns another non-successful HTTP response.
     * @throws InvalidResponseException If the response cannot be decoded or mapped.
     */
    public function get(CompanyRegistrationNumber $registrationNumber): Company;

    /**
     * Performs a search operation based on the given query criteria.
     *
     * @param CompanySearchQuery $query The search query containing filtering and search parameters.
     *
     * @return CompanySearchResult The result of the search operation.
     *
     * @throws RequestException If the HTTP request cannot be constructed.
     * @throws TransportException If the HTTP request cannot be completed.
     * @throws NotFoundException If ARES returns HTTP 404.
     * @throws RateLimitException If ARES returns HTTP 429.
     * @throws ApiException If ARES returns another non-successful HTTP response.
     * @throws InvalidResponseException If the response cannot be decoded or mapped.
     */
    public function search(CompanySearchQuery $query): CompanySearchResult;
}

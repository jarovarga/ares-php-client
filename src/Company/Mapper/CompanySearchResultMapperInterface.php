<?php

declare(strict_types=1);

namespace AresApi\Company\Mapper;

use AresApi\Company\Result\CompanySearchResult;
use AresApi\Exception\InvalidResponseException;
use AresApi\Pagination\PageRequest;

/**
 * Maps ARES company search result data to a DTO.
 */
interface CompanySearchResultMapperInterface
{
    /**
     * Maps the provided data and page request into a CompanySearchResult object.
     *
     * @param array<string, mixed> $data The ARES company-search payload.
     * @param PageRequest $page The page request information used for mapping.
     *
     * @return CompanySearchResult The mapped CompanySearchResult object.
     *
     * @throws InvalidResponseException If the payload has an invalid structure or contains invalid values.
     */
    public function map(
        array $data,
        PageRequest $page,
    ): CompanySearchResult;
}

<?php

declare(strict_types=1);

namespace AresApi\Company\Mapper;

use AresApi\Company\Result\CompanySearchResult;
use AresApi\Exception\InvalidResponseException;
use AresApi\Pagination\PageInfo;
use AresApi\Pagination\PageRequest;

/**
 * Maps ARES company search result data to a DTO.
 */
final readonly class CompanySearchResultMapper implements CompanySearchResultMapperInterface
{
    /**
     * Constructor method.
     *
     * @param CompanyMapperInterface $companyMapper An instance of CompanyMapperInterface used for mapping company data.
     */
    public function __construct(
        private CompanyMapperInterface $companyMapper,
    ) {
    }

    /**
     * Maps the provided data to a CompanySearchResult instance.
     *
     * @param array<string, mixed> $data The company-search response payload.
     * @param PageRequest $page The page request containing pagination information.
     *
     * @return CompanySearchResult A result object containing the mapped companies and pagination details.
     *
     * @throws InvalidResponseException If the data contains invalid or unexpected structure/values.
     */
    public function map(
        array $data,
        PageRequest $page,
    ): CompanySearchResult {
        $totalItems = $data['pocetCelkem'] ?? null;
        if (!is_int($totalItems) || $totalItems < 0) {
            throw new InvalidResponseException(
                'ARES field "pocetCelkem" must be a non-negative integer.',
            );
        }

        $records = $data['ekonomickeSubjekty'] ?? null;
        if (!is_array($records) || !array_is_list($records)) {
            throw new InvalidResponseException(
                'ARES field "ekonomickeSubjekty" must be a list.',
            );
        }

        $companies = [];
        foreach ($records as $index => $record) {
            if (!is_array($record)) {
                throw new InvalidResponseException(sprintf(
                    'ARES company at index %d must be an object.',
                    $index,
                ));
            }

            try {
                $companies[] = $this->companyMapper->map($record);
            } catch (InvalidResponseException $exception) {
                throw new InvalidResponseException(
                    sprintf(
                        'ARES company at index %d is invalid: %s',
                        $index,
                        $exception->getMessage(),
                    ),
                    previous: $exception,
                );
            }
        }

        return new CompanySearchResult(
            $companies,
            new PageInfo(
                $page->number(),
                $page->size(),
                $totalItems,
            ),
        );
    }
}

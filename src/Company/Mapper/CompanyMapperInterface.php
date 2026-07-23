<?php

declare(strict_types=1);

namespace AresApi\Company\Mapper;

use AresApi\Company\DTO\Company;
use AresApi\Exception\InvalidResponseException;

/**
 * Maps ARES company data to a DTO.
 */
interface CompanyMapperInterface
{
    /**
     * Maps the given data array to a Company object.
     *
     * @param array<string, mixed> $data The ARES company payload.
     *
     * @return Company The mapped company.
     *
     * @throws InvalidResponseException If the payload has an invalid structure or contains invalid values.
     */
    public function map(array $data): Company;
}

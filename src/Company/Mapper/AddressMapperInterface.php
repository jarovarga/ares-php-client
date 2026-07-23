<?php

declare(strict_types=1);

namespace AresApi\Company\Mapper;

use AresApi\Company\DTO\Address;
use AresApi\Exception\InvalidResponseException;

/**
 * Maps ARES address data to a DTO.
 */
interface AddressMapperInterface
{
    /**
     * Maps the given array of data to an Address object.
     *
     * @param array<string, mixed> $data The ARES address payload.
     *
     * @return Address The mapped address.
     *
     * @throws InvalidResponseException If the payload is a non-empty list or contains a value of an unexpected type.
     */
    public function map(array $data): Address;
}

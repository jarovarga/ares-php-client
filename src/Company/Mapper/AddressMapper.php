<?php

declare(strict_types=1);

namespace AresApi\Company\Mapper;

use AresApi\Company\DTO\Address;
use AresApi\Exception\InvalidResponseException;

/**
 * Maps ARES address data to a DTO.
 */
final class AddressMapper implements AddressMapperInterface
{
    /**
     * Maps an array of address data to an Address object.
     *
     * @param array<string, mixed> $data The associative array containing address data.
     *
     * @return Address The constructed Address object with mapped properties.
     *
     * @throws InvalidResponseException If the payload is a non-empty list or contains a value of an unexpected type.
     */
    public function map(array $data): Address
    {
        if ($data !== [] && array_is_list($data)) {
            throw new InvalidResponseException(
                'The ARES address payload must be an object; a list was received.',
            );
        }

        return new Address(
            countryCode: $this->nullableString($data, 'kodStatu'),
            countryName: $this->nullableString($data, 'nazevStatu'),
            regionCode: $this->nullableInt($data, 'kodKraje'),
            regionName: $this->nullableString($data, 'nazevKraje'),
            districtCode: $this->nullableInt($data, 'kodOkresu'),
            districtName: $this->nullableString($data, 'nazevOkresu'),
            municipalityCode: $this->nullableInt($data, 'kodObce'),
            municipalityName: $this->nullableString($data, 'nazevObce'),
            administrativeDistrictCode: $this->nullableInt(
                $data,
                'kodSpravnihoObvodu',
            ),
            administrativeDistrictName: $this->nullableString(
                $data,
                'nazevSpravnihoObvodu',
            ),
            cityDistrictCode: $this->nullableInt(
                $data,
                'kodMestskehoObvodu',
            ),
            cityDistrictName: $this->nullableString(
                $data,
                'nazevMestskehoObvodu',
            ),
            municipalityDistrictCode: $this->nullableInt(
                $data,
                'kodMestskeCastiObvodu',
            ),
            municipalityDistrictName: $this->nullableString(
                $data,
                'nazevMestskeCastiObvodu',
            ),
            streetCode: $this->nullableInt($data, 'kodUlice'),
            streetName: $this->nullableString($data, 'nazevUlice'),
            houseNumber: $this->nullableInt($data, 'cisloDomovni'),
            houseNumberTypeCode: $this->nullableInt(
                $data,
                'typCisloDomovni',
            ),
            addressComplement: $this->nullableString($data, 'doplnekAdresy'),
            municipalityPartCode: $this->nullableInt($data, 'kodCastiObce'),
            municipalityPartName: $this->nullableString(
                $data,
                'nazevCastiObce',
            ),
            orientationNumber: $this->nullableInt(
                $data,
                'cisloOrientacni',
            ),
            orientationNumberLetter: $this->nullableString(
                $data,
                'cisloOrientacniPismeno',
            ),
            addressPlaceCode: $this->nullableInt(
                $data,
                'kodAdresnihoMista',
            ),
            postalCode: $this->nullableInt($data, 'psc'),
            postalCodeText: $this->nullableString($data, 'pscTxt'),
            formattedAddress: $this->nullableString($data, 'textovaAdresa'),
            addressNumber: $this->nullableString($data, 'cisloDoAdresy'),
            standardized: $this->standardized($data),
        );
    }

    /**
     * Retrieves a value from the given data array for the specified field and ensures it is either a string or null.
     *
     * @param array<string, mixed> $data The address data.
     * @param string $field The field name to fetch from the data array.
     *
     * @return string|null The string value, or null when the field is absent or null.
     *
     * @throws InvalidResponseException If the field contains a value of an unexpected type.
     */
    private function nullableString(array $data, string $field): ?string
    {
        $value = $data[$field] ?? null;

        if ($value !== null && !is_string($value)) {
            throw $this->invalidType($field, 'string or null', $value);
        }

        return $value;
    }

    /**
     * Retrieves a value from the given data array for the specified field and ensures it is either an integer or null.
     *
     * @param array<string, mixed> $data The address data.
     * @param string $field The field name to fetch from the data array.
     *
     * @return int|null The integer value, or null when the field is absent or null.
     *
     * @throws InvalidResponseException If the field contains a value of an unexpected type.
     */
    private function nullableInt(array $data, string $field): ?int
    {
        $value = $data[$field] ?? null;

        if ($value !== null && !is_int($value)) {
            throw $this->invalidType($field, 'integer or null', $value);
        }

        return $value;
    }

    /**
     * Retrieves the ARES address-standardisation flag.
     *
     * @param array<string, mixed> $data The address data.
     *
     * @return bool|null The standardization flag, or null when absent.
     *
     * @throws InvalidResponseException If the field contains a value of an unexpected type.
     */
    private function standardized(array $data): ?bool
    {
        $value = $data['standardizaceAdresy'] ?? null;

        if ($value !== null && !is_bool($value)) {
            throw $this->invalidType(
                'standardizaceAdresy',
                'boolean or null',
                $value,
            );
        }

        return $value;
    }

    /**
     * Creates an InvalidResponseException for cases where a value does not match the expected type.
     *
     * @param string $field The name of the field with the invalid value.
     * @param string $expected A description of the expected type or format for the value.
     * @param mixed $value The received value that does not match the expected type.
     *
     * @return InvalidResponseException Returns an exception describing the invalid type error.
     */
    private function invalidType(
        string $field,
        string $expected,
        mixed $value,
    ): InvalidResponseException {
        return new InvalidResponseException(sprintf(
            'ARES field "%s" must be %s; %s received.',
            $field,
            $expected,
            get_debug_type($value),
        ));
    }
}

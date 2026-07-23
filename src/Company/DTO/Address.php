<?php

declare(strict_types=1);

namespace AresApi\Company\DTO;

/**
 * Represents an address.
 */
final readonly class Address
{
    /**
     * Constructor method to initialise the object with address-related properties.
     *
     * @param string|null $countryCode The ISO code of the country.
     * @param string|null $countryName The name of the country.
     * @param int|null $regionCode The code of the region.
     * @param string|null $regionName The name of the region.
     * @param int|null $districtCode The code of the district.
     * @param string|null $districtName The name of the district.
     * @param int|null $municipalityCode The code of the municipality.
     * @param string|null $municipalityName The name of the municipality.
     * @param int|null $administrativeDistrictCode The code of the administrative district.
     * @param string|null $administrativeDistrictName The name of the administrative district.
     * @param int|null $cityDistrictCode The code of the city district.
     * @param string|null $cityDistrictName The name of the city district.
     * @param int|null $municipalityDistrictCode The code of the municipality district.
     * @param string|null $municipalityDistrictName The name of the municipality district.
     * @param int|null $streetCode The code of the street.
     * @param string|null $streetName The name of the street.
     * @param int|null $houseNumber The house number.
     * @param int|null $houseNumberTypeCode The code representing the house number type.
     * @param string|null $addressComplement Additional address component (e.g., apartment number).
     * @param int|null $municipalityPartCode The code of the municipality part.
     * @param string|null $municipalityPartName The name of the municipality part.
     * @param int|null $orientationNumber The orientation number (if applicable).
     * @param string|null $orientationNumberLetter Additional letter associated with the orientation number.
     * @param int|null $addressPlaceCode The code of the place within the address.
     * @param int|null $postalCode The numeric postal code.
     * @param string|null $postalCodeText The textual representation of the postal code.
     * @param string|null $formattedAddress The fully formatted address.
     * @param string|null $addressNumber The complete address number (e.g., house number + orientation).
     * @param bool|null $standardized Whether the address is standardized (true) or not (false).
     */
    public function __construct(
        private ?string $countryCode = null,
        private ?string $countryName = null,
        private ?int $regionCode = null,
        private ?string $regionName = null,
        private ?int $districtCode = null,
        private ?string $districtName = null,
        private ?int $municipalityCode = null,
        private ?string $municipalityName = null,
        private ?int $administrativeDistrictCode = null,
        private ?string $administrativeDistrictName = null,
        private ?int $cityDistrictCode = null,
        private ?string $cityDistrictName = null,
        private ?int $municipalityDistrictCode = null,
        private ?string $municipalityDistrictName = null,
        private ?int $streetCode = null,
        private ?string $streetName = null,
        private ?int $houseNumber = null,
        private ?int $houseNumberTypeCode = null,
        private ?string $addressComplement = null,
        private ?int $municipalityPartCode = null,
        private ?string $municipalityPartName = null,
        private ?int $orientationNumber = null,
        private ?string $orientationNumberLetter = null,
        private ?int $addressPlaceCode = null,
        private ?int $postalCode = null,
        private ?string $postalCodeText = null,
        private ?string $formattedAddress = null,
        private ?string $addressNumber = null,
        private ?bool $standardized = null,
    ) {
    }

    /**
     * Returns the ISO code of the country.
     *
     * @return string|null The country code, or null if not set.
     */
    public function countryCode(): ?string
    {
        return $this->countryCode;
    }

    /**
     * Retrieves the name of the country associated with the current context.
     *
     * @return string|null The country name, or null if not set.
     */
    public function countryName(): ?string
    {
        return $this->countryName;
    }

    /**
     * Retrieves the region code.
     *
     * @return int|null The region code, or null if not set.
     */
    public function regionCode(): ?int
    {
        return $this->regionCode;
    }

    /**
     * Retrieves the name of the region associated with this instance.
     *
     * @return string|null The region name, or null if not set.
     */
    public function regionName(): ?string
    {
        return $this->regionName;
    }

    /**
     * Retrieves the district code, representing a specific geographical or administrative area.
     *
     * @return int|null The district code, or null if not set.
     */
    public function districtCode(): ?int
    {
        return $this->districtCode;
    }

    /**
     * Retrieves the name of the district, which identifies a specific geographical or administrative area.
     *
     * @return string|null The district name, or null if not set.
     */
    public function districtName(): ?string
    {
        return $this->districtName;
    }

    /**
     * Retrieves the municipality code, which identifies a specific municipality or administrative division.
     *
     * @return int|null The municipality code, or null if not set.
     */
    public function municipalityCode(): ?int
    {
        return $this->municipalityCode;
    }

    /**
     * Retrieves the name of the municipality.
     *
     * @return string|null The name of the municipality, or null if not set.
     */
    public function municipalityName(): ?string
    {
        return $this->municipalityName;
    }

    /**
     * Retrieves the administrative district code, representing a specific subdivision or region within an area.
     *
     * @return int|null The administrative district code, or null if it is not set.
     */
    public function administrativeDistrictCode(): ?int
    {
        return $this->administrativeDistrictCode;
    }

    /**
     * Retrieves the name of the administrative district.
     *
     * @return string|null The name of the administrative district, or null if not set.
     */
    public function administrativeDistrictName(): ?string
    {
        return $this->administrativeDistrictName;
    }

    /**
     * Retrieves the city district code.
     *
     * @return int|null The city district code or null if not set.
     */
    public function cityDistrictCode(): ?int
    {
        return $this->cityDistrictCode;
    }

    /**
     * Retrieves the name of the city district associated with this entity.
     *
     * @return string|null The city district name, or null if not set.
     */
    public function cityDistrictName(): ?string
    {
        return $this->cityDistrictName;
    }

    /**
     * Retrieves the municipality district code, representing the specific code assigned to a district within a municipality.
     *
     * @return int|null The municipality district code, or null if not set.
     */
    public function municipalityDistrictCode(): ?int
    {
        return $this->municipalityDistrictCode;
    }

    /**
     * Retrieves the name of the municipality district, providing a textual identifier for the district.
     *
     * @return string|null The name of the municipality district, or null if not set.
     */
    public function municipalityDistrictName(): ?string
    {
        return $this->municipalityDistrictName;
    }

    /**
     * Retrieves the street code, which may represent a unique identifier for a street.
     *
     * @return int|null The street code, or null if not set.
     */
    public function streetCode(): ?int
    {
        return $this->streetCode;
    }

    /**
     * Retrieves the street name associated with this entity.
     *
     * @return string|null The street name, or null if not set.
     */
    public function streetName(): ?string
    {
        return $this->streetName;
    }

    /**
     * Retrieves the house number, representing a numeric identifier for a specific building or residence.
     *
     * @return int|null The house number, or null if not set.
     */
    public function houseNumber(): ?int
    {
        return $this->houseNumber;
    }

    /**
     * Retrieves the house number type code, representing a numeric identifier for the type of house number.
     *
     * @return int|null The house number type code, or null if not set.
     */
    public function houseNumberTypeCode(): ?int
    {
        return $this->houseNumberTypeCode;
    }

    /**
     * Retrieves the complementary address information, if available.
     *
     * @return string|null The address complement or null if none is set.
     */
    public function addressComplement(): ?string
    {
        return $this->addressComplement;
    }

    /**
     * Retrieves the municipality part code, which identifies a specific part or division within a municipality.
     *
     * @return int|null The municipality part code, or null if not set.
     */
    public function municipalityPartCode(): ?int
    {
        return $this->municipalityPartCode;
    }

    /**
     * Retrieves the name of the municipality part, which identifies a specific section or subdivision within a municipality.
     *
     * @return string|null The name of the municipality part, or null if not set.
     */
    public function municipalityPartName(): ?string
    {
        return $this->municipalityPartName;
    }

    /**
     * Retrieves the orientation number, which could be used to determine a specific directional or positional value.
     *
     * @return int|null The orientation number, or null if not set.
     */
    public function orientationNumber(): ?int
    {
        return $this->orientationNumber;
    }

    /**
     * Retrieves the orientation number letter, which represents a character associated with a specific orientation or position.
     *
     * @return string|null The orientation number letter, or null if not set.
     */
    public function orientationNumberLetter(): ?string
    {
        return $this->orientationNumberLetter;
    }

    /**
     * Retrieves the address place code, representing a specific code related to a location or address.
     *
     * @return int|null The address place code, or null if not set.
     */
    public function addressPlaceCode(): ?int
    {
        return $this->addressPlaceCode;
    }

    /**
     * Retrieves the postal code associated with the entity.
     *
     * @return int|null The postal code, or null if not set.
     */
    public function postalCode(): ?int
    {
        return $this->postalCode;
    }

    /**
     * Retrieves the postal code as a text value.
     *
     * @return string|null The postal code text, or null if not set.
     */
    public function postalCodeText(): ?string
    {
        return $this->postalCodeText;
    }

    /**
     * Retrieves the formatted address, which represents a structured and human-readable version of the address.
     *
     * @return string|null The formatted address, or null if not available.
     */
    public function formattedAddress(): ?string
    {
        return $this->formattedAddress;
    }

    /**
     * Retrieves the address number, which may represent a specific numerical identifier for an address.
     *
     * @return string|null The address number, or null if not set.
     */
    public function addressNumber(): ?string
    {
        return $this->addressNumber;
    }

    /**
     * Checks if the entity is standardised, indicating whether it meets predefined standards or criteria.
     *
     * @return bool|null True if standardized, false if not, or null if not defined.
     */
    public function standardized(): ?bool
    {
        return $this->standardized;
    }
}

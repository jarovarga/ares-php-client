<?php

declare(strict_types=1);

namespace AresApi\Company\DTO;

use AresApi\Exception\ValidationException;
use AresApi\ValueObject\CompanyRegistrationNumber;
use DateTimeImmutable;

/**
 * Represents a company.
 */
final readonly class Company
{
    /**
     * @var list<string>
     */
    private array $czNaceCodes;

    /**
     * @var list<string>
     */
    private array $czNace2008Codes;

    /**
     * Constructor for creating an instance with company details and associated codes.
     *
     * @param string $aresId Unique ARES identifier (must contain eight digits, optionally prefixed with "ARES_").
     * @param CompanyRegistrationNumber|null $registrationNumber Optional company registration number.
     * @param string|null $businessName Optional business name.
     * @param Address|null $registeredOffice Optional registered office address.
     * @param LegalForm|null $legalForm Optional legal form of the company.
     * @param string|null $taxIdentificationNumber Optional company tax identification number.
     * @param string|null $taxOfficeCode Optional tax office code (must contain exactly three digits).
     * @param DateTimeImmutable|null $establishedOn Optional date when the company was established.
     * @param DateTimeImmutable|null $dissolvedOn Optional date when the company was dissolved.
     * @param DateTimeImmutable|null $updatedOn Optional date when the company details were last updated.
     * @param string|null $primarySource Optional primary source of the information.
     * @param list<string> $czNaceCodes List of CZ-NACE activity codes.
     * @param list<string> $czNace2008Codes List of CZ-NACE 2008 activity codes.
     *
     * @throws ValidationException If the identifiers are inconsistent, or an ARES identifier, tax-office code, or activity code is invalid.
     */
    public function __construct(
        private string $aresId,
        private ?CompanyRegistrationNumber $registrationNumber = null,
        private ?string $businessName = null,
        private ?Address $registeredOffice = null,
        private ?LegalForm $legalForm = null,
        private ?string $taxIdentificationNumber = null,
        private ?string $taxOfficeCode = null,
        private ?DateTimeImmutable $establishedOn = null,
        private ?DateTimeImmutable $dissolvedOn = null,
        private ?DateTimeImmutable $updatedOn = null,
        private ?string $primarySource = null,
        array $czNaceCodes = [],
        array $czNace2008Codes = [],
    ) {
        if (preg_match('/^(?:ARES_)?\d{8}$/D', $aresId) !== 1) {
            throw new ValidationException(
                'An ARES identifier must contain eight digits with an optional "ARES_" prefix.',
            );
        }

        if (
            $registrationNumber !== null
            && $aresId !== $registrationNumber->value()
        ) {
            throw new ValidationException(
                'The ARES identifier must match the company registration number when one is assigned.',
            );
        }

        if (
            $taxOfficeCode !== null
            && preg_match('/^\d{3}$/D', $taxOfficeCode) !== 1
        ) {
            throw new ValidationException(
                'A tax-office code must contain exactly three digits.',
            );
        }

        $this->czNaceCodes = self::normalizeActivityCodes(
            $czNaceCodes,
            'CZ-NACE',
        );
        $this->czNace2008Codes = self::normalizeActivityCodes(
            $czNace2008Codes,
            'CZ-NACE 2008',
        );
    }

    /**
     * Retrieves the ARES ID associated with this instance.
     *
     * @return string The ARES ID.
     */
    public function aresId(): string
    {
        return $this->aresId;
    }

    /**
     * Retrieves the registration number associated with this instance.
     *
     * @return CompanyRegistrationNumber|null The registration number, or null if not set.
     */
    public function registrationNumber(): ?CompanyRegistrationNumber
    {
        return $this->registrationNumber;
    }

    /**
     * Retrieves the business name associated with this instance.
     *
     * @return string|null The business name, or null if not set.
     */
    public function businessName(): ?string
    {
        return $this->businessName;
    }

    /**
     * Retrieves the registered office address associated with this instance.
     *
     * @return Address|null The registered office address, or null if not set.
     */
    public function registeredOffice(): ?Address
    {
        return $this->registeredOffice;
    }

    /**
     * Retrieves the legal form associated with this instance.
     *
     * @return LegalForm|null The legal form, or null if not set.
     */
    public function legalForm(): ?LegalForm
    {
        return $this->legalForm;
    }

    /**
     * Retrieves the tax identification number associated with this instance.
     *
     * @return string|null The tax identification number, or null if not available.
     */
    public function taxIdentificationNumber(): ?string
    {
        return $this->taxIdentificationNumber;
    }

    /**
     * Retrieves the tax office code associated with this instance.
     *
     * @return string|null The tax office code or null if not set.
     */
    public function taxOfficeCode(): ?string
    {
        return $this->taxOfficeCode;
    }

    /**
     * Retrieves the date the entity was established.
     *
     * @return DateTimeImmutable|null The establishment date or null if not set.
     */
    public function establishedOn(): ?DateTimeImmutable
    {
        return $this->establishedOn;
    }

    /**
     * Gets the date when the entity was dissolved.
     *
     * @return DateTimeImmutable|null The dissolution date, or null when ARES did not provide one.
     */
    public function dissolvedOn(): ?DateTimeImmutable
    {
        return $this->dissolvedOn;
    }

    /**
     * Retrieves the date when this instance was last updated.
     *
     * @return DateTimeImmutable|null The update date, or null if not set.
     */
    public function updatedOn(): ?DateTimeImmutable
    {
        return $this->updatedOn;
    }

    /**
     * Retrieves the primary source associated with this instance.
     *
     * @return string|null The primary source, or null if not set.
     */
    public function primarySource(): ?string
    {
        return $this->primarySource;
    }

    /**
     * Retrieves the list of CZ NACE codes associated with this instance.
     *
     * @return list<string> The CZ-NACE codes.
     */
    public function czNaceCodes(): array
    {
        return $this->czNaceCodes;
    }

    /**
     * Retrieves the CZ NACE 2008 codes associated with this instance.
     *
     * @return list<string> The CZ-NACE 2008 codes.
     */
    public function czNace2008Codes(): array
    {
        return $this->czNace2008Codes;
    }

    /**
     * Determines if the entity has a dissolution date.
     *
     * @return bool True if a dissolution date exists, false otherwise.
     */
    public function hasDissolutionDate(): bool
    {
        return $this->dissolvedOn !== null;
    }

    /**
     * Normalises an array of activity codes by validating and deduplicating them.
     *
     * Each code must be a non-empty string of at most five characters.
     * Any invalid code will result in a ValidationException being thrown.
     *
     * @param list<mixed> $codes Activity codes to be validated and normalised.
     * @param string $field The name of the field used in the exception message for invalid codes.
     *
     * @return list<string> The normalised list of unique activity codes.
     *
     * @throws ValidationException If any code is invalid.
     */
    private static function normalizeActivityCodes(
        array $codes,
        string $field,
    ): array {
        $normalized = [];

        foreach ($codes as $code) {
            if (
                !is_string($code)
                || preg_match('/^\S{1,5}$/uD', $code) !== 1
            ) {
                throw new ValidationException(sprintf(
                    'Every %s code must be a non-empty string of at most five characters.',
                    $field,
                ));
            }

            $normalized[$code] = $code;
        }

        return array_values($normalized);
    }
}

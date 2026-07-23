<?php

declare(strict_types=1);

namespace AresApi\Company\Query;

use AresApi\Exception\ValidationException;
use AresApi\Pagination\PageRequest;
use AresApi\ValueObject\CompanyRegistrationNumber;

/**
 * Represents a query for searching companies.
 */
final readonly class CompanySearchQuery
{
    /**
     * @var list<CompanyRegistrationNumber>
     */
    private array $registrationNumbers;

    /**
     * @var string|null
     */
    private ?string $businessName;

    /**
     * @var string|null
     */
    private ?string $addressText;

    /**
     * @var list<string>
     */
    private array $legalFormCodes;

    /**
     * @var list<string>
     */
    private array $rosLegalFormCodes;

    /**
     * @var list<string>
     */
    private array $taxOfficeCodes;

    /**
     * @var list<string>
     */
    private array $czNaceCodes;

    /**
     * Constructs a new instance of the class.
     *
     * @param list<CompanyRegistrationNumber> $registrationNumbers Company registration numbers.
     * @param string|null $businessName The business name to be used as a search criterion.
     * @param string|null $addressText The address text to be used as a search criterion.
     * @param list<string> $legalFormCodes Legal-form codes containing exactly three digits.
     * @param list<string> $rosLegalFormCodes ROS legal-form codes containing exactly three digits.
     * @param list<string> $taxOfficeCodes Tax-office codes containing exactly three digits.
     * @param list<string> $czNaceCodes CZ-NACE codes containing between one and five non-whitespace characters.
     * @param PageRequest $page The page request object used for pagination, defaulting to a new PageRequest instance.
     *
     * @throws ValidationException If a search criterion is invalid, more than 100 unique registration numbers are supplied, or no criterion is supplied.
     */
    public function __construct(
        array $registrationNumbers = [],
        ?string $businessName = null,
        ?string $addressText = null,
        array $legalFormCodes = [],
        array $rosLegalFormCodes = [],
        array $taxOfficeCodes = [],
        array $czNaceCodes = [],
        private PageRequest $page = new PageRequest(),
    ) {
        $uniqueRegistrationNumbers = [];
        foreach ($registrationNumbers as $registrationNumber) {
            if (!$registrationNumber instanceof CompanyRegistrationNumber) {
                throw new ValidationException(
                    'Every registration number must be a CompanyRegistrationNumber instance.',
                );
            }

            $uniqueRegistrationNumbers[$registrationNumber->value()] = $registrationNumber;
        }

        if (count($uniqueRegistrationNumbers) > 100) {
            throw new ValidationException(
                'ARES accepts at most 100 company registration numbers per search.',
            );
        }

        $this->registrationNumbers = array_values($uniqueRegistrationNumbers);
        $this->businessName = self::normalizeText(
            $businessName,
            2_000,
            'business name',
        );
        $this->addressText = self::normalizeText(
            $addressText,
            1_500,
            'address text',
        );
        $this->legalFormCodes = self::normalizeCodes(
            $legalFormCodes,
            '/^\d{3}$/D',
            'legal-form',
        );
        $this->rosLegalFormCodes = self::normalizeCodes(
            $rosLegalFormCodes,
            '/^\d{3}$/D',
            'ROS legal-form',
        );
        $this->taxOfficeCodes = self::normalizeCodes(
            $taxOfficeCodes,
            '/^\d{3}$/D',
            'tax-office',
        );
        $this->czNaceCodes = self::normalizeCodes(
            $czNaceCodes,
            '/^\S{1,5}$/uD',
            'CZ-NACE',
        );

        if (
            $this->registrationNumbers === []
            && $this->businessName === null
            && $this->addressText === null
            && $this->legalFormCodes === []
            && $this->rosLegalFormCodes === []
            && $this->taxOfficeCodes === []
            && $this->czNaceCodes === []
        ) {
            throw new ValidationException(
                'At least one company search criterion must be provided.',
            );
        }
    }

    /**
     * Retrieves the list of company registration numbers.
     *
     * @return list<CompanyRegistrationNumber> The company registration numbers.
     */
    public function registrationNumbers(): array
    {
        return $this->registrationNumbers;
    }

    /**
     * Retrieves the business name.
     *
     * @return string|null The business name, or null if not set.
     */
    public function businessName(): ?string
    {
        return $this->businessName;
    }

    /**
     * Retrieves the address text.
     *
     * @return string|null The address text, or null if not set.
     */
    public function addressText(): ?string
    {
        return $this->addressText;
    }

    /**
     * Retrieves the legal form codes.
     *
     * @return list<string> The legal-form codes.
     */
    public function legalFormCodes(): array
    {
        return $this->legalFormCodes;
    }

    /**
     * Retrieves the ROS legal form codes.
     *
     * @return list<string> The ROS legal-form codes.
     */
    public function rosLegalFormCodes(): array
    {
        return $this->rosLegalFormCodes;
    }

    /**
     * Retrieves the tax office codes.
     *
     * @return list<string> The tax-office codes.
     */
    public function taxOfficeCodes(): array
    {
        return $this->taxOfficeCodes;
    }

    /**
     * Retrieves the CZ-NACE codes.
     *
     * @return list<string> The CZ-NACE codes.
     */
    public function czNaceCodes(): array
    {
        return $this->czNaceCodes;
    }

    /**
     * Retrieves the PageRequest instance associated with the current context.
     *
     * @return PageRequest The PageRequest instance.
     */
    public function page(): PageRequest
    {
        return $this->page;
    }

    /**
     * Normalises and validates a given text value.
     *
     * @param string|null $value The text value to be normalized. Can be null.
     * @param int $maximumLength The maximum allowed length for the text.
     * @param string $field The name of the field being validated, used for error messages.
     *
     * @return string|null The normalized text value, or null if the input value is null.
     *
     * @throws ValidationException If the text is empty after trimming, contains invalid UTF-8 characters, or exceeds the maximum allowed length.
     */
    private static function normalizeText(
        ?string $value,
        int $maximumLength,
        string $field,
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            throw new ValidationException(sprintf(
                'The %s must not be empty.',
                $field,
            ));
        }

        if (preg_match('//u', $value) !== 1) {
            throw new ValidationException(sprintf(
                'The %s must contain valid UTF-8.',
                $field,
            ));
        }

        preg_match_all('/./us', $value, $characters);
        if (count($characters[0]) > $maximumLength) {
            throw new ValidationException(sprintf(
                'The %s must not exceed %d characters.',
                $field,
                $maximumLength,
            ));
        }

        return $value;
    }

    /**
     * Normalises an array of codes by validating them against a pattern and
     * removing duplicates.
     *
     * @param list<mixed> $codes The codes to be normalised.
     * @param string $pattern The validation pattern to check each code against.
     * @param string $field The name of the field being validated, used in error messages.
     *
     * @return list<string> The unique, validated codes.
     *
     * @throws ValidationException If any code is not a string or does not match the specified pattern.
     */
    private static function normalizeCodes(
        array $codes,
        string $pattern,
        string $field,
    ): array {
        $normalized = [];

        foreach ($codes as $code) {
            if (!is_string($code) || preg_match($pattern, $code) !== 1) {
                throw new ValidationException(sprintf(
                    'Every %s code has an invalid format.',
                    $field,
                ));
            }

            $normalized[$code] = $code;
        }

        return array_values($normalized);
    }
}

<?php

declare(strict_types=1);

namespace AresApi\Company\Mapper;

use AresApi\Company\DTO\Address;
use AresApi\Company\DTO\Company;
use AresApi\Company\DTO\LegalForm;
use AresApi\Exception\InvalidResponseException;
use AresApi\Exception\ValidationException;
use AresApi\ValueObject\CompanyRegistrationNumber;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Maps ARES company data to a DTO.
 */
final readonly class CompanyMapper implements CompanyMapperInterface
{
    /**
     * @param AddressMapperInterface $addressMapper
     */
    public function __construct(
        private AddressMapperInterface $addressMapper,
    ) {
    }

    /**
     * Maps the provided data array to a Company instance. Processes the data and resolves
     * various fields to construct a valid Company object.
     *
     * @param array<string, mixed> $data The input data array representing company details.
     *
     * @return Company The mapped Company instance based on the provided data.
     *
     * @throws InvalidResponseException If the payload has an invalid structure, contains inconsistent identifiers, or contains invalid values.
     */
    public function map(array $data): Company
    {
        try {
            $registrationNumber = $this->registrationNumber($data);
            $aresId = $this->nullableString($data, 'icoId')
                ?? $registrationNumber?->value();

            if ($aresId === null) {
                throw new InvalidResponseException(
                    'The ARES response does not contain "icoId" or "ico".',
                );
            }

            return new Company(
                aresId: $aresId,
                registrationNumber: $registrationNumber,
                businessName: $this->nullableString($data, 'obchodniJmeno'),
                registeredOffice: $this->address($data),
                legalForm: $this->legalForm($data),
                taxIdentificationNumber: $this->nullableString($data, 'dic'),
                taxOfficeCode: $this->nullableString($data, 'financniUrad'),
                establishedOn: $this->date($data, 'datumVzniku'),
                dissolvedOn: $this->date($data, 'datumZaniku'),
                updatedOn: $this->date($data, 'datumAktualizace'),
                primarySource: $this->nullableString($data, 'primarniZdroj'),
                czNaceCodes: $this->stringList($data, 'czNace'),
                czNace2008Codes: $this->stringList($data, 'czNace2008'),
            );
        } catch (ValidationException $exception) {
            throw new InvalidResponseException(
                sprintf('The ARES company payload is invalid: %s', $exception->getMessage()),
                previous: $exception,
            );
        }
    }

    /**
     * Extracts and validates a company registration number from the provided data.
     *
     * @param array<string, mixed> $data An associative array containing the data to extract the registration number from.
     *
     * @return CompanyRegistrationNumber|null The registration number, or null when neither "ico" nor an unprefixed eight-digit "icoId" provides one.
     *
     * @throws InvalidResponseException If a source field has an unexpected type.
     * @throws ValidationException If the registration number has an invalid format.
     */
    private function registrationNumber(
        array $data,
    ): ?CompanyRegistrationNumber {
        $value = $this->nullableString($data, 'ico');

        if ($value === null) {
            $aresId = $this->nullableString($data, 'icoId');
            if ($aresId !== null && preg_match('/^\d{8}$/D', $aresId) === 1) {
                $value = $aresId;
            }
        }

        return $value === null
            ? null
            : new CompanyRegistrationNumber($value);
    }

    /**
     * Extracts and maps the 'sidlo' field from the provided data array to an Address object.
     *
     * @param array<string, mixed> $data The input data array.
     *
     * @return Address|null Returns an Address object if the 'sidlo' field is valid, or null if the field is not set.
     * @throws InvalidResponseException If "sidlo" is not an object-like array or contains invalid address values.
     */
    private function address(array $data): ?Address
    {
        $value = $data['sidlo'] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw $this->invalidType('sidlo', 'object or null', $value);
        }

        return $this->addressMapper->map($value);
    }

    /**
     * Creates a LegalForm instance based on the provided data array, if applicable.
     *
     * @param array<string, mixed> $data The company payload.
     *
     * @return LegalForm|null An instance of LegalForm if sufficient data is provided, or null if the required fields are absent.
     *
     * @throws InvalidResponseException If a field is not a string or null.
     * @throws ValidationException If a legal-form code has an invalid format.
     */
    private function legalForm(array $data): ?LegalForm
    {
        $code = $this->nullableString($data, 'pravniForma');
        $rosCode = $this->nullableString($data, 'pravniFormaRos');

        return $code === null && $rosCode === null
            ? null
            : new LegalForm($code, $rosCode);
    }

    /**
     * Retrieves the value of a specified field from the provided data array, validates it as a date in Y-m-d format,
     * and returns it as a DateTimeImmutable object or null.
     *
     * @param array<string, mixed> $data The company payload.
     * @param string $field The key whose value needs to be retrieved and validated as a date.
     *
     * @return DateTimeImmutable|null The validated date at midnight UTC, or null when the field is absent or null.
     *
     * @throws InvalidResponseException If the field value is not in a valid date format (Y-m-d).
     */
    private function date(array $data, string $field): ?DateTimeImmutable
    {
        $value = $this->nullableString($data, $field);
        if ($value === null) {
            return null;
        }

        if (str_contains($value, "\0")) {
            throw new InvalidResponseException(
                sprintf(
                    'ARES field "%s" must contain a valid date in Y-m-d format.',
                    $field,
                ),
            );
        }

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value,
            new DateTimeZone('UTC'),
        );

        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidResponseException(sprintf(
                'ARES field "%s" must contain a valid date in Y-m-d format.',
                $field,
            ));
        }

        return $date;
    }

    /**
     * Retrieves the value of a specified field from the provided data array, ensuring it is either a string or null.
     *
     * @param array<string, mixed> $data The company payload.
     * @param string $field The key whose value needs to be retrieved and validated.
     *
     * @return string|null The value of the specified field if it is a string or null.
     *
     * @throws InvalidResponseException If the field value is not a string or null.
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
     * Extracts a list of strings from the provided data array for the given field.
     *
     * @param array<string, mixed> $data The input data array.
     * @param string $field The key to extract the list of strings from.
     *
     * @return list<string> A list of strings, or an empty list when the field is absent.
     *
     * @throws InvalidResponseException If a present field is not a list of strings.
     */
    private function stringList(array $data, string $field): array
    {
        if (!array_key_exists($field, $data)) {
            return [];
        }

        $value = $data[$field];

        if (!is_array($value) || !array_is_list($value)) {
            throw $this->invalidType($field, 'a list of strings', $value);
        }

        foreach ($value as $item) {
            if (!is_string($item)) {
                throw $this->invalidType($field, 'a list of strings', $value);
            }
        }

        return $value;
    }

    /**
     * Throws an InvalidResponseException for a field with an unexpected type.
     * Constructs an exception message specifying the field name, the expected type,
     * and the actual type of the value provided.
     *
     * @param string $field The name of the field with the invalid type.
     * @param string $expected The expected type of the field value.
     * @param mixed $value The actual value that caused the type validation to fail.
     *
     * @return InvalidResponseException An exception describing the type mismatch error.
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

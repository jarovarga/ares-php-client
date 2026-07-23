<?php

declare(strict_types=1);

namespace AresApi\ValueObject;

use AresApi\Exception\ValidationException;

/**
 * Represents a company registration number.
 */
final readonly class CompanyRegistrationNumber
{
    /**
     * Constructs a new instance of the class.
     *
     * @param string $value A string value that must contain exactly eight digits.
     *
     * @throws ValidationException If the provided value does not match the required eight-digit format.
     */
    public function __construct(
        private string $value,
    ) {
        if (preg_match('/^\d{8}$/D', $value) !== 1) {
            throw new ValidationException(
                'A company registration number must contain exactly eight digits.',
            );
        }
    }

    /**
     * Retrieves the stored value.
     *
     * @return string The value stored in the instance.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Compares the current instance with another instance for equality.
     *
     * @param self $other The instance to compare against.
     *
     * @return bool True if the current instance and the provided instance are equal, otherwise false.
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Converts the object to its string representation.
     *
     * @return string The string representation of the object.
     */
    public function __toString(): string
    {
        return $this->value;
    }
}

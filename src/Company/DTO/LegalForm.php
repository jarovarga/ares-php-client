<?php

declare(strict_types=1);

namespace AresApi\Company\DTO;

use AresApi\Exception\ValidationException;

/**
 * Represents a legal form.
 */
final readonly class LegalForm
{
    /**
     * Constructor for the class.
     *
     * Validates that at least one of the provided codes is not null and ensures
     * that they conform to the expected format of three digits.
     *
     * @param string|null $code The primary legal-form code. Must contain exactly three digits if provided.
     * @param string|null $rosCode The ROS-specific legal-form code. Must contain exactly three digits if provided.
     *
     * @throws ValidationException If neither $code nor $rosCode is provided or if their values do not meet the required format.
     */
    public function __construct(
        private ?string $code,
        private ?string $rosCode = null,
    ) {
        if ($code === null && $rosCode === null) {
            throw new ValidationException(
                'At least one legal-form code must be provided.',
            );
        }

        foreach (['code' => $code, 'ROS code' => $rosCode] as $name => $value) {
            if ($value !== null && preg_match('/^\d{3}$/D', $value) !== 1) {
                throw new ValidationException(sprintf(
                    'The legal-form %s must contain exactly three digits.',
                    $name,
                ));
            }
        }
    }

    /**
     * Retrieves the code.
     *
     * @return string|null The code, or null if not set.
     */
    public function code(): ?string
    {
        return $this->code;
    }

    /**
     * Retrieves the ROS code.
     *
     * @return string|null The ROS code, or null if not set.
     */
    public function rosCode(): ?string
    {
        return $this->rosCode;
    }
}

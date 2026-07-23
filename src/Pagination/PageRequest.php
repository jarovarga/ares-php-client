<?php

declare(strict_types=1);

namespace AresApi\Pagination;

use AresApi\Exception\ValidationException;

/**
 * Represents a request for a specific page of data.
 */
final readonly class PageRequest
{
    public const int MAX_RESULT_WINDOW = 10_000;

    /**
     * Constructs a new instance of the class.
     *
     * @param int $number The page number, must be at least 1.
     * @param int $size The page size, between 1 and the ARES 10,000-item result-window limit.
     *
     * @throws ValidationException If the page number is less than 1.
     * @throws ValidationException If the page size is outside the valid range.
     * @throws ValidationException If the requested page extends beyond the ARES result window.
     */
    public function __construct(
        private int $number = 1,
        private int $size = 20,
    ) {
        if ($number < 1) {
            throw new ValidationException('The page number must be at least 1.');
        }

        if ($size < 1 || $size > self::MAX_RESULT_WINDOW) {
            throw new ValidationException(sprintf(
                'The page size must be between 1 and %d.',
                self::MAX_RESULT_WINDOW,
            ));
        }

        if ($number > intdiv(self::MAX_RESULT_WINDOW, $size)) {
            throw new ValidationException(
                'The requested page exceeds the 10,000-item result window supported by ARES.',
            );
        }
    }

    /**
     * Retrieves the current page number.
     *
     * @return int The current page number.
     */
    public function number(): int
    {
        return $this->number;
    }

    /**
     * Retrieves the size.
     *
     * @return int The size value.
     */
    public function size(): int
    {
        return $this->size;
    }

    /**
     * Calculates the offset based on the current number and size.
     *
     * @return int The computed offset value.
     */
    public function offset(): int
    {
        return ($this->number - 1) * $this->size;
    }
}

<?php

declare(strict_types=1);

namespace AresApi\Pagination;

use AresApi\Exception\ValidationException;

/**
 * Represents pagination information for a dataset.
 */
final readonly class PageInfo
{
    /**
     * Constructor for initialising pagination parameters.
     *
     * @param int $currentPage The current page number, must be at least 1.
     * @param int $pageSize The number of items per page, must be at least 1.
     * @param int $totalItems The total number of items, must not be negative.
     *
     * @throws ValidationException If the provided values do not meet the defined constraints.
     */
    public function __construct(
        private int $currentPage,
        private int $pageSize,
        private int $totalItems,
    ) {
        if ($currentPage < 1) {
            throw new ValidationException('The current page must be at least 1.');
        }

        if ($pageSize < 1) {
            throw new ValidationException('The page size must be at least 1.');
        }

        if ($totalItems < 0) {
            throw new ValidationException('The total item count must not be negative.');
        }
    }

    /**
     * Retrieves the current page number.
     *
     * @return int The current page number.
     */
    public function currentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * Retrieves the number of items per page.
     *
     * @return int The number of items per page.
     */
    public function pageSize(): int
    {
        return $this->pageSize;
    }

    /**
     * Retrieves the total number of items.
     *
     * @return int The total number of items.
     */
    public function totalItems(): int
    {
        return $this->totalItems;
    }

    /**
     * Calculates the total number of pages based on the total items and page size.
     *
     * @return int The total number of pages.
     */
    public function totalPages(): int
    {
        if ($this->totalItems === 0) {
            return 0;
        }

        return intdiv($this->totalItems - 1, $this->pageSize) + 1;
    }

    /**
     * Determines whether another page is available within the ARES result window.
     *
     * @return bool True if a next page exists, false otherwise.
     */
    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->totalPages()
            && $this->currentPage < intdiv(
                PageRequest::MAX_RESULT_WINDOW,
                $this->pageSize,
            );
    }

    /**
     * Determines if there is a previous page available.
     *
     * @return bool True if a previous page exists, false otherwise.
     */
    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }
}

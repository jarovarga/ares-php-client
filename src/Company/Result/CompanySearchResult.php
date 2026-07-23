<?php

declare(strict_types=1);

namespace AresApi\Company\Result;

use AresApi\Company\DTO\Company;
use AresApi\Exception\ValidationException;
use AresApi\Pagination\PageInfo;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Represents a search result for a collection of Company objects.
 *
 * @implements IteratorAggregate<int, Company>
 */
final readonly class CompanySearchResult implements Countable, IteratorAggregate
{
    /**
     * Initialises a new instance of the class.
     *
     * @param list<Company> $items The company search-result items.
     * @param PageInfo $pageInfo The pagination information associated with the search results.
     *
     * @throws ValidationException If the $items array is not a list or contains elements that are not instances of the Company class.
     */
    public function __construct(
        private array $items,
        private PageInfo $pageInfo,
    ) {
        if (!array_is_list($items)) {
            throw new ValidationException(
                'Search result items must be provided as a list.',
            );
        }

        foreach ($items as $item) {
            if (!$item instanceof Company) {
                throw new ValidationException(
                    'Every search result item must be a Company instance.',
                );
            }
        }
    }

    /**
     * Retrieves the list of search result items.
     *
     * @return list<Company> The search-result items.
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * Retrieves the pagination information associated with the results.
     *
     * @return PageInfo The pagination information.
     */
    public function pageInfo(): PageInfo
    {
        return $this->pageInfo;
    }

    /**
     * Counts the number of items in the collection.
     *
     * @return int The total number of items in the collection.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Checks if the collection of items is empty.
     *
     * @return bool True if the collection has no items, false otherwise.
     */
    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * Retrieves an iterator for traversing the items.
     *
     * @return Traversable<int, Company> An iterator for the search-result items.
     */
    public function getIterator(): Traversable
    {
        yield from $this->items;
    }
}

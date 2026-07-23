<?php

declare(strict_types=1);

namespace AresApi\Tests\Unit\Pagination;

use AresApi\Exception\ValidationException;
use AresApi\Pagination\PageInfo;
use AresApi\Pagination\PageRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageInfo::class)]
#[CoversClass(PageRequest::class)]
final class PageInfoTest extends TestCase
{
    public function testPageRequestCalculatesAnApiOffset(): void
    {
        $page = new PageRequest(number: 3, size: 25);

        self::assertSame(3, $page->number());
        self::assertSame(25, $page->size());
        self::assertSame(50, $page->offset());
    }

    public function testPageRequestRejectsAZeroPageNumber(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageIsOrContains('page number');

        new PageRequest(number: 0);
    }

    public function testPageRequestRejectsAZeroPageSize(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageIsOrContains('page size');

        new PageRequest(size: 0);
    }

    public function testPageRequestAllowsTheLastApiResultWindow(): void
    {
        $page = new PageRequest(number: 100, size: 100);

        self::assertSame(9_900, $page->offset());
        self::assertSame(
            PageRequest::MAX_RESULT_WINDOW,
            $page->offset() + $page->size(),
        );
    }

    public function testPageRequestRejectsAWindowPastTheApiResultLimit(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageIsOrContains('10,000-item result window');

        new PageRequest(number: 101, size: 100);
    }

    public function testPageRequestRejectsAPageSizePastTheApiResultLimit(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageIsOrContains('page size');

        new PageRequest(size: PageRequest::MAX_RESULT_WINDOW + 1);
    }

    public function testItCalculatesTotalsAndNavigation(): void
    {
        $page = new PageInfo(
            currentPage: 2,
            pageSize: 20,
            totalItems: 41,
        );

        self::assertSame(2, $page->currentPage());
        self::assertSame(20, $page->pageSize());
        self::assertSame(41, $page->totalItems());
        self::assertSame(3, $page->totalPages());
        self::assertTrue($page->hasNextPage());
        self::assertTrue($page->hasPreviousPage());
    }

    public function testAnEmptyResultHasNoPagesOrNavigation(): void
    {
        $page = new PageInfo(
            currentPage: 1,
            pageSize: 20,
            totalItems: 0,
        );

        self::assertSame(0, $page->totalPages());
        self::assertFalse($page->hasNextPage());
        self::assertFalse($page->hasPreviousPage());
    }

    public function testTheLastAccessibleResultWindowHasNoNextPage(): void
    {
        $page = new PageInfo(
            currentPage: 500,
            pageSize: 20,
            totalItems: 20_000,
        );

        self::assertSame(1_000, $page->totalPages());
        self::assertFalse($page->hasNextPage());
        self::assertTrue($page->hasPreviousPage());
    }

    public function testItRejectsInvalidMetadata(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageIsOrContains('must not be negative');

        new PageInfo(
            currentPage: 1,
            pageSize: 20,
            totalItems: -1,
        );
    }
}

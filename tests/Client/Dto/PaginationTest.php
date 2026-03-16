<?php

namespace Client\Dto;

use FQL\Client\Dto\Pagination;
use PHPUnit\Framework\TestCase;

class PaginationTest extends TestCase
{
    public function testFromArray(): void
    {
        $pagination = Pagination::fromArray([
            'page' => 2,
            'pageCount' => 10,
            'itemCount' => 100,
            'itemsPerPage' => 10,
            'offset' => 10,
        ]);

        $this->assertEquals(2, $pagination->page);
        $this->assertEquals(10, $pagination->pageCount);
        $this->assertEquals(100, $pagination->itemCount);
        $this->assertEquals(10, $pagination->itemsPerPage);
        $this->assertEquals(10, $pagination->offset);
    }

    public function testFromArrayDefaults(): void
    {
        $pagination = Pagination::fromArray([]);

        $this->assertEquals(1, $pagination->page);
        $this->assertEquals(1, $pagination->pageCount);
        $this->assertEquals(0, $pagination->itemCount);
        $this->assertEquals(0, $pagination->itemsPerPage);
        $this->assertEquals(0, $pagination->offset);
    }

    public function testHasMultiplePages(): void
    {
        $single = Pagination::fromArray(['pageCount' => 1]);
        $this->assertFalse($single->hasMultiplePages());

        $multiple = Pagination::fromArray(['pageCount' => 5]);
        $this->assertTrue($multiple->hasMultiplePages());
    }
}

<?php

namespace QueSoft\Ntak\Tests;

use QueSoft\Ntak\Enums\NTAKCategory;
use QueSoft\Ntak\NTAK;
use PHPUnit\Framework\TestCase;

class CategoryTest extends TestCase
{
    public function test_list_categories(): void
    {
        $this->assertEquals(
            NTAKCategory::cases(),
            NTAK::categories()
        );
    }

    public function test_list_sub_categories(): void
    {
        $randomCategory = NTAKCategory::random();

        $this->assertEquals(
            $randomCategory->subcategories(),
            NTAK::subcategories($randomCategory)
        );

        $subcategories = NTAK::subcategories($randomCategory);
        $randomSubcategory = $subcategories[array_rand($subcategories)];

        $this->assertTrue(
            $randomCategory->hasSubcategory($randomSubcategory)
        );
    }

    public function test_from_name(): void
    {
        $this->assertSame(
            NTAKCategory::ETEL,
            NTAKCategory::fromName('ETEL')
        );
    }
}

<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Support\ProductStockAvailability;
use Tests\TestCase;

class ProductStockAvailabilityTest extends TestCase
{
    public function test_service_products_do_not_track_inventory(): void
    {
        $product = new Product([
            'type' => 'service',
            'track_inventory' => false,
        ]);

        $this->assertFalse(ProductStockAvailability::productTracksInventory($product));
    }

    public function test_non_inventory_stock_products_do_not_track_inventory(): void
    {
        $product = new Product([
            'type' => 'stock',
            'track_inventory' => false,
        ]);

        $this->assertFalse(ProductStockAvailability::productTracksInventory($product));
    }

    public function test_stock_products_track_inventory(): void
    {
        $product = new Product([
            'type' => 'stock',
            'track_inventory' => true,
        ]);

        $this->assertTrue(ProductStockAvailability::productTracksInventory($product));
    }

    public function test_format_quantity_trims_trailing_zeroes(): void
    {
        $this->assertSame('5', ProductStockAvailability::formatQuantity(5.0));
        $this->assertSame('2.5', ProductStockAvailability::formatQuantity(2.5));
    }
}

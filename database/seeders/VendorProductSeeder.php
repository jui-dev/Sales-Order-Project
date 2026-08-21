<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorProduct;
use App\Services\Pricing\PriceListService;
use Illuminate\Database\Seeder;

/**
 * Which seeded vendor carries which seeded product, and at what cost.
 *
 * Without this a purchase order has nothing to offer: an order can only ask
 * for what its vendor actually sells, so unassigned products leave the line
 * editor empty.
 *
 * Carriage and cost are stored apart, the same way the application stores
 * them. The pivot records only that the vendor carries the product; what they
 * charge goes on their dated purchase price list through PriceListService, so
 * seed data cannot introduce the second copy of a price the ledger exists to
 * prevent.
 *
 * Several products are carried by two vendors at different costs on purpose -
 * that is the case the pricing screens are built around, and it never shows up
 * if every product has exactly one supplier.
 */
class VendorProductSeeder extends Seeder
{
    /**
     * Vendor id => the prefix that vendor uses for its own part numbers.
     */
    private const VENDOR_SKU_PREFIX = [
        4001 => 'GTS',
        4002 => 'OEC',
        4003 => 'WWL',
    ];

    public function run(): void
    {
        // product sku => [vendor id => agreed unit cost]
        $catalogue = [
            // Electronics - the tech supplier leads, the general warehouse
            // carries the volume lines a little cheaper.
            'IPHONE-15-PRO' => [4001 => 899.00, 4003 => 915.00],
            'SAMSUNG-S24' => [4001 => 720.00, 4003 => 735.00],
            'MACBOOK-PRO-16' => [4001 => 1980.00],
            'DELL-XPS-15' => [4001 => 1250.00, 4003 => 1275.00],
            'ELECTRONICS-BLUETOOTH-SPEAKER' => [4001 => 34.50, 4003 => 32.00],

            // Home, office and kitchen - the office supplier's ground.
            'HOME-GARDEN-LED-LAMP' => [4002 => 18.75, 4003 => 17.90],
            'KITCHEN-COFFEE-MAKER' => [4002 => 62.00],
            'FURNITURE-OFFICE-DESK' => [4002 => 185.00, 4003 => 179.50],

            // Clothing - bought in bulk from the general warehouse.
            'CLOTHING-UNISEX-HOODIE' => [4003 => 21.40],
            'MEN-TSHIRT-WHITE' => [4003 => 7.25],
            'MEN-JEANS-SLIM' => [4003 => 24.90],
            'WOMEN-DRESS-FLORAL' => [4003 => 29.60],
        ];

        $products = Product::whereIn('sku', array_keys($catalogue))->get()->keyBy('sku');
        $vendors = Vendor::whereIn('id', array_keys(self::VENDOR_SKU_PREFIX))->get()->keyBy('id');
        $lists = app(PriceListService::class);

        foreach ($catalogue as $sku => $costs) {
            $product = $products->get($sku);

            if (! $product) {
                continue;
            }

            foreach ($costs as $vendorId => $unitCost) {
                $vendor = $vendors->get($vendorId);

                if (! $vendor) {
                    continue;
                }

                VendorProduct::updateOrCreate(
                    ['vendor_id' => $vendor->id, 'product_id' => $product->id],
                    [
                        'vendor_sku' => self::VENDOR_SKU_PREFIX[$vendorId].'-'.$sku,
                        'is_active' => true,
                    ],
                );

                // Dated, and idempotent when the cost has not moved - reseeding
                // must not churn a fresh row onto every vendor's history.
                $lists->setPrice($lists->forVendor($vendor), $product, $unitCost);
            }
        }
    }
}

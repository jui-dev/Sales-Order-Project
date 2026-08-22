<?php

namespace Tests\Feature\Reference;

use App\Models\Customer;
use App\Models\PickingList;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\StockTransaction;
use App\Models\StockTransfer;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Support\Nav\ActionCatalog;
use App\Support\Nav\EffectClassifier;
use App\Support\Nav\NavCatalog;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Tests\TestCase;

/**
 * The Action Effects page points at things. Do those things exist?
 *
 * What each action *claims* to trigger still needs a person to check against
 * the code. Where it claims to trigger it does not: NavCatalog::path() returns
 * an unknown key unchanged, so a wrong destination renders as a bare lowercase
 * string on the page and, worse, silently sends the menu badge to a row nothing
 * reads. That is how Product changes stopped raising a badge on Products
 * without anybody noticing.
 */
class ActionCatalogIntegrityTest extends TestCase
{
    public function test_every_catalogued_action_names_a_real_route(): void
    {
        $registered = collect(Route::getRoutes())->map(fn ($route) => $route->getName())->filter()->all();

        foreach (array_keys(ActionCatalog::all()) as $routeName) {
            $this->assertContains(
                $routeName,
                $registered,
                sprintf('ActionCatalog documents "%s", which is not a registered route.', $routeName),
            );
        }
    }

    public function test_every_effect_points_at_a_real_menu_entry(): void
    {
        foreach (ActionCatalog::all() as $routeName => $action) {
            foreach ($action['effects'] as $effect) {
                $this->assertNavKeyExists(
                    $effect['key'],
                    sprintf('Action "%s" points an effect at', $routeName),
                );
            }
        }
    }

    public function test_every_key_the_classifier_can_emit_is_a_real_menu_entry(): void
    {
        foreach ($this->everyClassifiableModel() as $description => $model) {
            foreach (EffectClassifier::keysFor($model) as $key) {
                $this->assertNavKeyExists(
                    $key,
                    sprintf('EffectClassifier sends %s to', $description),
                );
            }
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function assertNavKeyExists(string $key, string $context): void
    {
        $this->assertTrue(
            $this->navKeys()->contains($key),
            sprintf(
                '%s "%s", which is not a NavCatalog key. It will render as a bare key and any badge sent to it is never read.',
                $context,
                $key,
            ),
        );
    }

    private function navKeys()
    {
        return collect(array_keys(
            (new ReflectionClass(NavCatalog::class))->getConstant('ITEMS'),
        ));
    }

    /**
     * One unsaved instance of everything the classifier answers for.
     *
     * The location-driven models answer differently per route, so each of those
     * routes is covered rather than one arbitrary example.
     *
     * @return iterable<string, \Illuminate\Database\Eloquent\Model>
     */
    private function everyClassifiableModel(): iterable
    {
        $models = (new ReflectionClass(EffectClassifier::class))->getConstant('MODELS');

        foreach (array_keys($models) as $class) {
            // Covered exhaustively below; a bare instance answers for only one
            // of the several menu rows these land on.
            if (in_array($class, [PickingList::class, StockTransfer::class, StockTransaction::class], true)) {
                continue;
            }

            yield class_basename($class) => new $class();
        }

        $journeys = [
            'a vendor to warehouse receipt' => [Vendor::class, Warehouse::class],
            'a warehouse to retailer transfer' => [Warehouse::class, Retailer::class],
            'a warehouse to customer pick' => [Warehouse::class, Customer::class],
            'a retailer to customer pick' => [Retailer::class, Customer::class],
        ];

        foreach ($journeys as $description => [$from, $to]) {
            yield 'a picking list for ' . $description => new PickingList([
                'from_location_type' => $from,
                'to_location_type' => $to,
            ]);

            yield 'a stock transfer for ' . $description => new StockTransfer([
                'from_location_type' => $from,
                'to_location_type' => $to,
            ]);
        }

        foreach (['customer_return', 'vendor_return', 'retailer_return', 'purchase'] as $type) {
            yield 'a ' . str_replace('_', ' ', $type) . ' movement' => new StockTransaction([
                'transaction_type' => $type,
            ]);
        }
    }
}

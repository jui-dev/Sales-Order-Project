<?php

namespace Tests\Feature;

use App\Http\Middleware\TrackTriggeredEffects;
use App\Models\Product;
use App\Models\Supply;
use App\Models\SupplyItem;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Support\Nav\NavEffects;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Completing a supply quietly raises a GRN and changes what the vendor picking
 * screen shows. These tests cover the feedback loop that tells the user so.
 */
class TriggeredEffectsTest extends TestCase
{
    use RefreshDatabase;

    private function supply(string $status = 'pending'): Supply
    {
        $supply = Supply::create([
            'vendor_id' => Vendor::factory()->create()->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'status' => $status,
            'supply_date' => now(),
            'total_cost' => 100,
        ]);

        SupplyItem::create([
            'supply_id' => $supply->id,
            'product_id' => Product::factory()->create(['purchase_price' => 10])->id,
            'quantity' => 10,
            'unit_cost' => 10,
            'subtotal' => 100,
        ]);

        return $supply;
    }

    public function test_completing_a_supply_raises_badges_on_every_menu_it_touched(): void
    {
        $supply = $this->supply();

        $response = $this->actingAs(User::find(1))
            ->patch(route('supplies.completed', $supply->id));

        $response->assertRedirect();

        $counts = session(TrackTriggeredEffects::COUNTS);

        // The supply itself moved, and a draft GRN was raised for receiving.
        $this->assertSame(1, $counts['procurement.supplies'] ?? 0);
        $this->assertSame(1, $counts['procurement.grns'] ?? 0);

        // The vendor picking screen lists supplies rather than records of its
        // own, so it is affected without anything being created there.
        $this->assertSame(1, $counts['picking.vendor-to-warehouse'] ?? 0);
    }

    public function test_the_landing_page_names_the_records_that_were_created(): void
    {
        $supply = $this->supply();

        $response = $this->actingAs(User::find(1))
            ->patch(route('supplies.completed', $supply->id));

        // The panel says what was created and, crucially, where to find it -
        // the GRN is not on the page the reader was just looking at. The
        // headline is the controller's own success message; the catalogue
        // label only stands in when an action flashes nothing.
        $this->followRedirects($response)
            ->assertSee('Supply marked as completed. Receive the goods below.')
            ->assertSee('Goods receipt note created (draft)')
            ->assertSee('Procurement &gt; Good Receipt Notes', false);
    }

    /**
     * Completing a supply redirects to the new GRN, and the banner there points
     * the reader at Procurement > GRNs. The badge on that menu item has to
     * still be there when they look, or the two halves of the same screen
     * disagree. Reading one GRN is not the same as opening the GRN list.
     */
    public function test_landing_on_a_record_does_not_spend_the_badge_for_its_list(): void
    {
        $supply = $this->supply();
        $admin = User::find(1);

        $response = $this->actingAs($admin)->patch(route('supplies.completed', $supply->id));
        $response->assertRedirect(route('grns.show', $supply->fresh()->grn->id));

        // The page the banner appears on: it names the GRN, so the sidebar must
        // agree with it.
        $this->followRedirects($response)->assertSee('Procurement &gt; Good Receipt Notes', false);
        $this->assertSame(1, session(TrackTriggeredEffects::COUNTS)['procurement.grns'] ?? 0);

        // Opening the list itself is what spends it.
        $this->actingAs($admin)->get(route('grns.index'));
        $this->assertArrayNotHasKey('procurement.grns', session(TrackTriggeredEffects::COUNTS));
    }

    public function test_a_badge_survives_unrelated_browsing_and_clears_on_the_screen_it_points_at(): void
    {
        $supply = $this->supply();
        $admin = User::find(1);

        $this->actingAs($admin)->patch(route('supplies.completed', $supply->id));

        // Somewhere else entirely: the badge is still owed to the reader.
        $this->actingAs($admin)->get(route('products.index'));
        $this->assertSame(1, session(TrackTriggeredEffects::COUNTS)['picking.vendor-to-warehouse'] ?? 0);

        // Opening the screen the badge points at spends it, and leaves the
        // badges for the screens they have not looked at yet.
        $this->actingAs($admin)->get(route('vendor-to-warehouse-picking.index'));

        $counts = session(TrackTriggeredEffects::COUNTS);
        $this->assertArrayNotHasKey('picking.vendor-to-warehouse', $counts);
        $this->assertSame(1, $counts['procurement.supplies'] ?? 0);
    }

    public function test_a_returns_filter_only_clears_its_own_row(): void
    {
        $admin = User::find(1);

        session([TrackTriggeredEffects::COUNTS => [
            'returns.all' => 2,
            'returns.customer_return' => 1,
            'returns.vendor_return' => 1,
        ]]);

        // The three Returns rows are one index filtered by ?type=, so opening
        // one filter must not spend the badges on the others.
        $this->actingAs($admin)->get(route('returns.index', ['type' => 'customer_return']));

        $counts = session(TrackTriggeredEffects::COUNTS);
        $this->assertArrayNotHasKey('returns.customer_return', $counts);
        $this->assertSame(1, $counts['returns.vendor_return'] ?? 0);
        $this->assertSame(2, $counts['returns.all'] ?? 0);
    }

    public function test_reading_a_page_records_nothing(): void
    {
        $this->supply();

        $this->actingAs(User::find(1))->get(route('supplies.index'));

        $this->assertEmpty(session(TrackTriggeredEffects::COUNTS, []));
    }

    public function test_a_user_without_the_permission_never_sees_the_badge(): void
    {
        $admin = User::find(1);
        $supply = $this->supply();

        $this->actingAs($admin)->patch(route('supplies.completed', $supply->id));

        // The counts are stored per session, so a reader who cannot open GRNs
        // has to be filtered when the sidebar renders rather than when the
        // record is made.
        $onlooker = User::factory()->create();
        $this->actingAs($onlooker);
        $this->assertFalse($onlooker->can('grns.view'));

        $request = Request::create(route('dashboard'));
        $request->setLaravelSession(app('session.store'));

        $this->assertArrayNotHasKey('procurement.grns', NavEffects::counts($request));
    }

    public function test_the_badge_is_rendered_on_the_sidebar_item(): void
    {
        $supply = $this->supply();
        $admin = User::find(1);

        $this->actingAs($admin)->patch(route('supplies.completed', $supply->id));

        $page = $this->actingAs($admin)->get(route('dashboard'));

        $page->assertSee('nav-effect-badge', false)
            // An uncompiled component tag would otherwise reach the browser as
            // literal text and the badge would never appear.
            ->assertDontSee('<x-nav-badge', false);
    }

    /**
     * The effects notice is a button on the page header, not a banner across
     * the page. It carries the detail; the flashed message stays a toast,
     * because nothing else on screen says the action worked.
     */
    public function test_the_effects_are_a_header_button_and_the_message_a_toast(): void
    {
        $supply = $this->supply();

        $response = $this->actingAs(User::find(1))
            ->patch(route('supplies.completed', $supply->id));

        $page = $this->followRedirects($response);

        // The message the controller flashed still arrives as a toast...
        $page->assertSee('toast align-items-center text-white bg-success', false)
            ->assertSee('Supply marked as completed. Receive the goods below.');

        // ...and the effects sit behind the button, in a modal.
        $page->assertSee('id="triggeredEffectsBtn"', false)
            ->assertSee('id="triggeredEffectsModal"', false)
            ->assertSee('This also triggered');
    }

    /**
     * A delete creates and updates nothing, so it raises no effects and no
     * button. The toast is the only feedback there is either way.
     */
    public function test_an_action_with_no_effects_keeps_its_toast(): void
    {
        $this->actingAs(User::find(1));

        $this->withSession(['success' => 'Supply deleted successfully.'])
            ->get(route('dashboard'))
            ->assertSee('toast align-items-center text-white bg-success', false)
            ->assertSee('Supply deleted successfully.');
    }

    public function test_the_reference_page_lists_what_actions_trigger(): void
    {
        $this->actingAs(User::find(1))
            ->get(route('reference.action-effects'))
            ->assertOk()
            ->assertSee('Mark supply completed')
            ->assertSee('Post a goods receipt note');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\Location;
use App\Models\Store;
use App\Models\User;
use App\Services\StockService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Store $main;
    private Store $field;
    private Department $programs;
    private Department $finance;

    protected function setUp(): void
    {
        parent::setUp();
        $today = CarbonImmutable::create(2026, 6, 15, 12);

        $this->user = User::create([
            'name' => 'Storekeeper', 'email' => 'stores@example.test',
            'password' => bcrypt('password'), 'role' => 'stores',
        ]);
        $location       = Location::forceCreate(['name' => 'HQ']);
        $this->main     = Store::forceCreate(['name' => 'Main Store', 'location_id' => $location->id]);
        $this->field    = Store::forceCreate(['name' => 'Field Store', 'location_id' => $location->id]);
        $this->programs = Department::forceCreate(['code' => 'PRG', 'name' => 'Programs']);
        $this->finance  = Department::forceCreate(['code' => 'FIN', 'name' => 'Finance']);

        $paper  = $this->item('Paper', 20);
        $pens   = $this->item('Pens', 10);
        $toner  = $this->item('Toner', 5);
        $this->item('Chairs', 0); // never stocked, no reorder level → not flagged

        $stock = app(StockService::class);

        // Outside the 90-day window (inside 12 months): 10 paper in and out.
        $this->travelTo($today->subDays(200));
        $stock->receiveStock($this->main, $this->user, [['item_id' => $paper->id, 'quantity' => 10]]);
        $stock->issueStock($this->main, $this->user, [['item_id' => $paper->id, 'quantity' => 10]], ['department_id' => $this->programs->id]);

        $this->travelTo($today->subDays(60));
        $stock->receiveStock($this->main, $this->user, [
            ['item_id' => $paper->id, 'quantity' => 100],
            ['item_id' => $pens->id, 'quantity' => 30],
            ['item_id' => $toner->id, 'quantity' => 4],
        ]);
        $stock->receiveStock($this->field, $this->user, [['item_id' => $pens->id, 'quantity' => 5]]);

        $this->travelTo($today->subDays(10));
        $stock->issueStock($this->main, $this->user, [
            ['item_id' => $paper->id, 'quantity' => 85],
            ['item_id' => $toner->id, 'quantity' => 4],
        ], ['department_id' => $this->programs->id, 'issued_to' => 'Field team']);

        $this->travelTo($today->subDays(5));
        $stock->issueStock($this->main, $this->user, [['item_id' => $pens->id, 'quantity' => 30]], ['department_id' => $this->finance->id]);

        $this->travelTo($today);
    }

    private function item(string $name, int $reorderLevel): Item
    {
        return Item::forceCreate([
            'name' => $name, 'reorder_level' => $reorderLevel, 'is_active' => true, 'created_by' => $this->user->id,
        ]);
    }

    private function dashboard(string $query = '')
    {
        return $this->withoutVite()->actingAs($this->user)->get('/inventory/dashboard' . $query)->assertOk();
    }

    public function test_dashboard_summarises_issues_receipts_stock_and_reorder_list(): void
    {
        $this->dashboard()->assertInertia(fn ($page) => $page
            ->component('Inventory/Dashboard')
            ->where('period.key', '90d')
            ->where('summary.issues', 2)
            ->where('summary.units_issued', 119)
            ->where('summary.units_received', 139)
            ->where('summary.units_on_hand', 20)
            ->where('summary.low_stock', 2)
            ->where('summary.out_of_stock', 1)
            ->where('topItems.0.name', 'Paper')
            ->where('topItems.0.quantity', 85)
            ->where('topItems.1.name', 'Pens')
            ->where('byDepartment.0.name', 'Programs')
            ->where('byDepartment.0.quantity', 89)
            ->where('byDepartment.1.quantity', 30)
            // Out of stock first, then lowest available.
            ->where('reorder.0.name', 'Toner')
            ->where('reorder.0.status', 'out')
            ->where('reorder.1.name', 'Pens')
            ->where('reorder.1.available', 5)
            ->where('reorder.2.name', 'Paper')
            ->where('reorderTotal', 3)
            ->where('recentIssues.0.department', 'Finance')
            ->where('recentIssues.1.issued_to', 'Field team')
            ->where('recentIssues.1.units', 89)
            ->where('trend', fn ($points) => collect($points)->sum('issued') === 119 && collect($points)->sum('received') === 139)
        );
    }

    public function test_longer_period_includes_older_movements(): void
    {
        $this->dashboard('?period=12m')->assertInertia(fn ($page) => $page
            ->where('summary.issues', 3)
            ->where('summary.units_issued', 129)
            ->where('summary.units_received', 149)
            ->where('trend', fn ($points) => count($points) >= 12)
        );
    }

    public function test_store_and_department_filters(): void
    {
        $this->dashboard("?store_id={$this->field->id}")->assertInertia(fn ($page) => $page
            ->where('summary.units_on_hand', 5)
            ->where('summary.units_issued', 0)
            ->where('summary.units_received', 5)
            ->where('reorder.0.name', 'Pens')
            ->where('reorderTotal', 1)
        );

        // Receipts carry no department, so they drop out under a department filter.
        $this->dashboard("?department_id={$this->finance->id}")->assertInertia(fn ($page) => $page
            ->where('summary.units_issued', 30)
            ->where('summary.receipts_counted', false)
            ->where('summary.units_received', 0)
            ->has('byDepartment', 1)
        );
    }

    public function test_dashboard_exports_as_pdf(): void
    {
        $response = $this->actingAs($this->user)->get('/inventory/dashboard/export/pdf?period=30d');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}

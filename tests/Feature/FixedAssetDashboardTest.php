<?php

namespace Tests\Feature;

use App\Models\AssetCategory;
use App\Models\FixedAsset;
use App\Models\FixedAssetRevaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixedAssetDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private AssetCategory $laptops;
    private AssetCategory $vehicles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 6, 15)->startOfDay());

        $this->user = User::create([
            'name' => 'Asset Manager', 'email' => 'assets@example.test',
            'password' => bcrypt('password'), 'role' => 'stores',
        ]);
        $this->laptops  = AssetCategory::forceCreate(['name' => 'Laptops']);
        $this->vehicles = AssetCategory::forceCreate(['name' => 'Vehicles']);

        // 10,000 over 5 yrs, 2 yrs old → 2,000/yr, 4,000 accumulated, 6,000 book.
        $laptop = $this->asset('FA-1', 'Laptop', $this->laptops, 10000, 5, 0, now()->subYears(2), 'assigned');
        // (50,000 − 10,000) over 10 yrs, 3 yrs old → 4,000/yr, 12,000 accumulated, 38,000 book; revaluation due today.
        $this->asset('FA-2', 'Truck', $this->vehicles, 50000, 10, 10000, now()->subYears(3), 'available');
        // Disposed: counted by status, excluded from values.
        $this->asset('FA-3', 'Old laptop', $this->laptops, 999, 3, 0, now()->subYears(4), 'disposed');

        FixedAssetRevaluation::create([
            'fixed_asset_id' => $laptop->id, 'revalued_by' => $this->user->id,
            'revaluation_date' => now()->subMonth(), 'previous_value' => 7000, 'revalued_amount' => 7500,
        ]);
    }

    private function asset(string $number, string $name, AssetCategory $category, float $cost, int $life, float $salvage, $purchased, string $status): FixedAsset
    {
        return FixedAsset::forceCreate([
            'asset_number' => $number, 'name' => $name, 'asset_category_id' => $category->id,
            'purchase_cost' => $cost, 'useful_life_years' => $life, 'salvage_value' => $salvage,
            'purchase_date' => $purchased->toDateString(), 'revaluation_cycle_years' => 3,
            'status' => $status, 'created_by' => $this->user->id,
        ]);
    }

    public function test_dashboard_summarises_values_depreciation_and_revaluations(): void
    {
        $this->withoutVite()->actingAs($this->user)->get('/fixed-assets/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FixedAssets/Dashboard')
                ->where('summary.asset_count', 2)
                ->where('summary.disposed_count', 1)
                ->where('summary.cost', 60000)
                ->where('summary.book_value', 44000)
                ->where('summary.accumulated_depreciation', 16000)
                ->where('summary.annual_depreciation', 6000)
                ->where('summary.revaluations_overdue', 1)
                ->where('summary.revaluations_due_soon', 0)
                ->where('summary.revaluation_count_12m', 1)
                ->where('summary.revaluation_change_12m', 500)
                ->where('byCategory.0.name', 'Vehicles')
                ->where('byCategory.0.book_value', 38000)
                ->where('byCategory.1.name', 'Laptops')
                ->where('byCategory.1.count', 1)
                ->where('projection.0.book_value', 44000)
                ->where('projection.1.book_value', 38000)
                ->where('upcomingRevaluations.0.asset_number', 'FA-2')
                ->where('upcomingRevaluations.0.overdue', true)
                ->where('recentRevaluations.0.change', 500)
                ->has('byStatus', 3)
            );
    }

    public function test_dashboard_filters_by_category(): void
    {
        $this->withoutVite()->actingAs($this->user)->get("/fixed-assets/dashboard?category_id={$this->laptops->id}")
            ->assertInertia(fn ($page) => $page
                ->where('summary.asset_count', 1)
                ->where('summary.book_value', 6000)
                ->where('summary.revaluations_overdue', 0)
                ->has('byCategory', 1)
            );
    }

    public function test_dashboard_exports_as_pdf(): void
    {
        $response = $this->actingAs($this->user)->get("/fixed-assets/dashboard/export/pdf?category_id={$this->vehicles->id}");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('fixed-asset-dashboard-2026-06-15.pdf', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}

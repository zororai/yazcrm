<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\FixedAsset;
use App\Models\FixedAssetActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixedAssetEditDeleteTest extends TestCase
{
    use RefreshDatabase;

    private FixedAsset $asset;

    protected function setUp(): void
    {
        parent::setUp();
        $creator = $this->makeUser('admin');
        $this->asset = FixedAsset::forceCreate([
            'asset_number' => 'FA-0001', 'name' => 'Laptop', 'purchase_cost' => 10000, 'useful_life_years' => 5,
            'purchase_date' => '2025-01-10', 'status' => 'available', 'created_by' => $creator->id,
        ]);
    }

    private function makeUser(string $role): User
    {
        static $n = 0;
        $n++;

        return User::create(['name' => "Asset User {$n}", 'email' => "asset{$n}@example.test", 'password' => bcrypt('x'), 'role' => $role]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Laptop', 'purchase_cost' => 10000, 'useful_life_years' => 5, 'purchase_date' => '2025-01-10',
        ];
    }

    public function test_edit_records_old_and_new_values_in_audit_trail(): void
    {
        $manager = $this->makeUser('stores');

        $this->actingAs($manager)->from('/fixed-assets')
            ->put("/fixed-assets/{$this->asset->id}", $this->payload(['name' => 'Laptop Pro', 'purchase_cost' => 12000]))
            ->assertSessionHas('success', 'Asset updated.');

        $this->asset->refresh();
        $this->assertSame('Laptop Pro', $this->asset->name);
        $this->assertEquals(12000, (float) $this->asset->purchase_cost);

        $audit = AuditLog::latest('id')->firstOrFail();
        $this->assertSame('PUT', $audit->method);
        $this->assertSame($manager->id, $audit->user_id);
        $this->assertStringStartsWith('Edited fixed asset FA-0001 (Laptop Pro): ', $audit->description);
        $this->assertStringContainsString("Name: 'Laptop' → 'Laptop Pro'", $audit->description);
        $this->assertStringContainsString('Purchase cost: 10,000.00 → 12,000.00', $audit->description);
        $this->assertStringNotContainsString('Purchase date', $audit->description); // unchanged fields left out

        $log = FixedAssetActivityLog::where('action', 'updated')->firstOrFail();
        $this->assertEqualsCanonicalizing(['name', 'purchase_cost'], $log->changed_fields);
    }

    public function test_saving_without_changes_is_noted_but_not_logged_as_an_edit(): void
    {
        $this->actingAs($this->makeUser('stores'))->from('/fixed-assets')
            ->put("/fixed-assets/{$this->asset->id}", $this->payload())
            ->assertSessionHas('success', 'No changes to save.');

        $this->assertStringContainsString('(no changes)', AuditLog::latest('id')->value('description'));
        $this->assertFalse(FixedAssetActivityLog::where('action', 'updated')->exists());
    }

    public function test_only_admin_or_director_can_delete_and_a_reason_is_required(): void
    {
        $this->actingAs($this->makeUser('stores'))
            ->delete("/fixed-assets/{$this->asset->id}", ['reason' => 'x'])->assertForbidden();

        $director = $this->makeUser('director');
        $this->actingAs($director)->from('/fixed-assets')
            ->delete("/fixed-assets/{$this->asset->id}")->assertSessionHasErrors('reason');
        $this->assertNotSoftDeleted($this->asset);

        $this->actingAs($director)
            ->delete("/fixed-assets/{$this->asset->id}", ['reason' => 'Duplicate of FA-0002'])
            ->assertRedirect('/fixed-assets');

        $this->assertSoftDeleted($this->asset);

        $audit = AuditLog::latest('id')->firstOrFail();
        $this->assertSame('DELETE', $audit->method);
        $this->assertStringContainsString('Deleted fixed asset FA-0001 (Laptop)', $audit->description);
        $this->assertStringContainsString('Reason: Duplicate of FA-0002', $audit->description);

        $log = FixedAssetActivityLog::where('action', 'deleted')->firstOrFail();
        $this->assertSame('Duplicate of FA-0002', $log->reason);
        $this->assertSame($director->id, $log->user_id);
    }

    public function test_index_shows_edit_and_delete_to_the_right_roles(): void
    {
        $this->withoutVite()->actingAs($this->makeUser('stores'))->get('/fixed-assets')
            ->assertInertia(fn ($page) => $page->where('isManager', true)->where('canDelete', false));

        $this->withoutVite()->actingAs($this->makeUser('director'))->get('/fixed-assets')
            ->assertInertia(fn ($page) => $page->where('isManager', true)->where('canDelete', true));

        $this->withoutVite()->actingAs($this->makeUser('staff'))->get('/fixed-assets')
            ->assertInertia(fn ($page) => $page->where('isManager', false)->where('canDelete', false));
    }

    public function test_audit_trail_page_shows_and_searches_details(): void
    {
        $this->actingAs($this->makeUser('stores'))->from('/fixed-assets')
            ->put("/fixed-assets/{$this->asset->id}", $this->payload(['name' => 'Laptop Pro']));

        $this->withoutVite()->actingAs($this->makeUser('admin'))->get('/audit-log?search=Laptop+Pro')
            ->assertInertia(fn ($page) => $page
                ->where('logs.total', 1)
                ->where('logs.data.0.description', fn ($d) => str_contains($d, "Name: 'Laptop' → 'Laptop Pro'"))
            );
    }
}

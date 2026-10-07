<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// Read-only report pages open to anyone granted the matching sidebar
// permission, not just admins; settings pages stay admin-only.
class NavPermissionAccessTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, array $perms): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name' => "Nav User {$n}", 'email' => "nav{$n}@example.test", 'password' => bcrypt('x'),
            'role' => $role, 'nav_permissions' => $perms,
        ]);
    }

    public function test_permission_holders_can_open_analytics_bot_contacts_and_by_project(): void
    {
        Http::fake(); // Bot Contacts calls the uChat API
        $manager = $this->user('helpline_manager', ['analytics', 'bot_contacts', 'by_project']);

        $this->withoutVite()->actingAs($manager)->get('/analytics')->assertOk();
        $this->withoutVite()->actingAs($manager)->get('/uchat-contacts')->assertOk();
        // By Project's stats use MySQL date functions, so on the SQLite test
        // database we only assert that access is granted (not a 403).
        $this->assertNotSame(403, $this->withoutVite()->actingAs($manager)->get('/distress-domains/section/project')->status());
    }

    public function test_each_page_needs_its_own_permission(): void
    {
        $agent = $this->user('agent', ['calls', 'tickets']);

        $this->actingAs($agent)->get('/analytics')->assertForbidden();
        $this->actingAs($agent)->get('/uchat-contacts')->assertForbidden();
        $this->actingAs($agent)->get('/distress-domains/section/project')->assertForbidden();

        $onlyAnalytics = $this->user('agent', ['analytics']);
        $this->withoutVite()->actingAs($onlyAnalytics)->get('/analytics')->assertOk();
        $this->actingAs($onlyAnalytics)->get('/uchat-contacts')->assertForbidden();
    }

    public function test_settings_pages_stay_admin_only(): void
    {
        $manager = $this->user('helpline_manager', ['analytics', 'bot_contacts', 'by_project', 'domains']);

        $this->actingAs($manager)->get('/distress-domains')->assertForbidden();
        $this->actingAs($manager)->get('/distress-domains/section/distress-domains')->assertForbidden();
        $this->actingAs($manager)->post('/distress-domains', ['name' => 'X'])->assertForbidden();
        $this->actingAs($manager)->get('/roles')->assertForbidden();
    }

    public function test_admin_still_has_access_without_listed_permissions(): void
    {
        $admin = $this->user('admin', []);

        $this->withoutVite()->actingAs($admin)->get('/analytics')->assertOk();
        $this->withoutVite()->actingAs($admin)->get('/distress-domains/section/distress-domains')->assertOk();
    }
}

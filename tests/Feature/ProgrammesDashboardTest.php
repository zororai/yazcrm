<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProgrammesDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::create(2026, 6, 15, 12));

        $this->admin = $this->makeUser('admin');

        $this->ticket('2026-06-03', 'UNICEF', ['purpose_of_call' => 'Counselling', 'services_requested' => 'Psycho-social support', 'uptake_confirmed' => 1, 'referred_to' => 'Clinic', 'caller_gender' => 'male', 'caller_age' => 16, 'call_validity' => 'valid']);
        $this->ticket('2026-06-10', 'UNICEF', ['purpose_of_call' => 'Counselling', 'services_requested' => 'Psycho-social support', 'uptake_confirmed' => 0, 'referred_to' => '', 'caller_gender' => 'Female', 'caller_age' => 22, 'call_validity' => 'valid']);
        $this->ticket('2026-06-12', 'UNICEF', ['purpose_of_call' => 'Information', 'services_requested' => 'HIV testing', 'uptake_confirmed' => 1, 'referred_to' => 'NAC', 'caller_gender' => 'female', 'caller_age' => 30, 'call_validity' => 'invalid']);
        $this->ticket('2026-05-20', 'UNICEF', ['purpose_of_call' => 'Counselling', 'caller_gender' => 'male', 'caller_age' => 12]);
        $this->ticket('2026-06-05', 'UNICEF', ['purpose_of_call' => 'Counselling', 'deleted_at' => '2026-06-06 09:00:00']);
        $this->ticket('2026-06-11', 'GLOBAL FUND', ['purpose_of_call' => 'Information', 'caller_gender' => 'other', 'caller_age' => 40]);
    }

    private function makeUser(string $role, array $perms = []): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name' => "Prog User {$n}", 'email' => "prog{$n}@example.test", 'password' => bcrypt('password'),
            'role' => $role, 'nav_permissions' => $perms,
        ]);
    }

    private function ticket(string $date, string $project, array $attrs = []): void
    {
        DB::table('tickets')->insert($attrs + [
            'subject' => 'Case', 'project' => $project,
            'created_at' => "{$date} 10:00:00", 'updated_at' => "{$date} 10:00:00",
        ]);
    }

    private function dashboard(string $query, ?User $user = null)
    {
        return $this->withoutVite()->actingAs($user ?? $this->admin)->get('/programmes/dashboard' . $query);
    }

    public function test_programme_month_view_matches_screen_definitions(): void
    {
        $this->dashboard('?programme=UNICEF&period=month')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Programmes/Dashboard')
            ->where('summary.total', 3)
            ->where('summary.previous_total', 1)
            ->where('summary.valid', 2)
            ->where('summary.referred', 2)
            ->where('summary.uptake', 2)
            ->where('caseTypes.0.name', 'Counselling')
            ->where('caseTypes.0.count', 2)
            ->where('caseTypes.1.name', 'Information')
            ->where('caseTypeRecorded', 3)
            ->where('services.0.name', 'Psycho-social support')
            ->where('services.0.referred', 2)
            ->where('services.0.uptake', 1)
            ->where('services.0.rate', 50)
            ->where('services.1.uptake', 1)
            ->where('gender.0.name', 'Female') // "Female" and "female" merged
            ->where('gender.0.count', 2)
            ->where('gender.1.name', 'Male')
            ->where('ageGroups.0.count', 1)
            ->where('ageGroups.1.count', 1)
            ->where('ageGroups.2.count', 1)
            ->where('ageGender.1.band', '15-19')
            ->where('ageGender.1.male', 1)
            ->where('ageGender.2.female', 1)
            ->where('ageGender.3.female', 1)
            ->where('trend.unit', 'day')
            ->where('trend.points', fn ($points) => collect($points)->sum('count') === 3)
        );
    }

    public function test_all_programmes_longer_period_and_custom_range(): void
    {
        $this->dashboard('?period=3m')->assertInertia(fn ($page) => $page
            ->where('summary.total', 5)
            ->where('programmes', ['GLOBAL FUND', 'UNICEF'])
            ->where('trend.unit', 'month')
            ->where('trend.points', fn ($points) => collect($points)->pluck('count')->all() === [0, 1, 4])
        );

        $this->dashboard('?period=custom&from=2026-06-10&to=2026-06-12')->assertInertia(fn ($page) => $page
            ->where('summary.total', 3)
            ->where('gender', fn ($g) => collect($g)->pluck('name')->sort()->values()->all() === ['Female', 'Other'])
        );
    }

    public function test_screen_filters_narrow_every_figure(): void
    {
        DB::table('tickets')->where('caller_age', 16)->update(['province' => 'Harare', 'district' => 'Harare Urban']);
        DB::table('tickets')->where('caller_age', 22)->update(['province' => 'Midlands', 'district' => 'Gweru']);
        DB::table('tickets')->where('caller_age', 30)->update(['province' => 'Harare', 'district' => 'Epworth']);
        DB::table('tickets')->where('project', 'GLOBAL FUND')->update(['province' => 'Harare']);

        // June has 4 live cases: three UNICEF + one GLOBAL FUND.
        $this->dashboard('?period=month')->assertInertia(fn ($page) => $page
            ->where('summary.total', 4)
            ->where('options.services', ['HIV testing', 'Psycho-social support'])
            ->where('options.provinceDistricts.Harare', ['Harare Urban', 'Harare Rural', 'Chitungwiza', 'Epworth'])
            ->has('options.ageGroups', 5)
        );

        $cases = fn (string $query) => fn ($page) => $page->where('summary.total', match ($query) {
            'province=Harare'                => 3,
            'province=Harare&district=Epworth' => 1,
            'gender=male'                    => 1,
            'age=18-24'                      => 1,
            'service=HIV+testing'            => 1,
            'programme=UNICEF&province=Harare&age=u18' => 1,
            'gender=nonsense'                => 4, // unknown values are ignored
        });

        foreach (['province=Harare', 'province=Harare&district=Epworth', 'gender=male', 'age=18-24', 'service=HIV+testing', 'programme=UNICEF&province=Harare&age=u18', 'gender=nonsense'] as $query) {
            $this->dashboard("?period=month&{$query}")->assertInertia($cases($query));
        }

        // Filters flow into every section, not just the total.
        $this->dashboard('?period=month&service=HIV+testing')->assertInertia(fn ($page) => $page
            ->has('services', 1)
            ->where('caseTypes.0.name', 'Information')
            ->where('gender.0.name', 'Female')
            ->where('activeFilters.Service', 'HIV testing')
        );
    }

    public function test_requires_programmes_dashboard_permission(): void
    {
        $staff = $this->makeUser('staff', ['dashboard']);
        $this->dashboard('', $staff)->assertForbidden();
        $this->actingAs($staff)->get('/programmes/dashboard/export/pdf')->assertForbidden();

        $allowed = $this->makeUser('staff', ['programmes_dashboard']);
        $this->dashboard('', $allowed)->assertOk();
    }

    public function test_dashboard_exports_as_pdf(): void
    {
        $response = $this->actingAs($this->admin)->get('/programmes/dashboard/export/pdf?programme=UNICEF&period=3m');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('programmes-dashboard-unicef-2026-06-15.pdf', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}

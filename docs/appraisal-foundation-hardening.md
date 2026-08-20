# Appraisal Module — Foundation Hardening

This documents the functions/classes added on top of the original
[Staff Performance Appraisal](staff-performance-appraisal.md) feature to move
authorization out of the controller, make status changes go through a
controlled workflow, and add an audit trail. No new appraisal statuses,
cycles, notifications, or dashboards were added in this pass — see "Out of
scope" at the end.

## `AppraisalPolicy`

**File:** `app/Policies/AppraisalPolicy.php`

Laravel policy for the `Appraisal` model, auto-discovered by naming
convention. Replaces the inline `isManager()` / `authorizeView()` /
`canEditSelf()` / `canEditReview()` helpers that used to live on the
controller — same rules, just centralized so frontend (`can` props) and
backend (`$this->authorize()`) can never disagree.

| Method | Allowed when |
|---|---|
| `viewAny` | always |
| `view` | manager, or the appraisal's owner, or its assigned supervisor |
| `create` | always |
| `update` | manager, or owner **and** status is `draft` |
| `submit` | same as `update`, **and** status is `draft` |
| `review` | manager, or assigned supervisor **and** status is `submitted` |
| `complete` | same as `review`, **and** status is `submitted` |
| `reopen` | manager only |
| `delete` | admin only |
| `manage` | manager only — used to gate the history panel and the `can.manage` flag sent to Vue |

`manager` = `role` is `admin` or `director`.

## `AppraisalWorkflowService`

**File:** `app/Services/AppraisalWorkflowService.php`

Centralizes every status change instead of controller methods setting
`status`/timestamps directly.

- `create(User $actor, array $attributes): Appraisal` — creates a `draft`
  appraisal and logs a `created` activity row.
- `update(Appraisal $appraisal, User $actor, array $data, string $action = 'updated'): Appraisal` —
  saves non-status field edits (self-assessment or review fields) inside a
  transaction and logs the changed field names (`$action` is `updated` or
  `review_updated`).
- `submit(Appraisal $appraisal, User $actor): Appraisal` — `draft → submitted`,
  stamps `submitted_at` / `employee_signed_at`.
- `complete(Appraisal $appraisal, User $actor): Appraisal` — `submitted →
  completed`, stamps `completed_at` / `supervisor_signed_at`.
- `reopen(Appraisal $appraisal, User $actor, string $reason): Appraisal` —
  `submitted → draft` or `completed → submitted`, clears `completed_at`,
  requires and logs a `reason`.

Every transition is checked against an explicit `TRANSITIONS` map and throws
`RuntimeException` for anything not listed (e.g. `draft → completed`
directly is rejected). Every write — status change or field edit — happens
inside `DB::transaction()` together with its activity-log row, so a failure
in one rolls back the other.

## `AppraisalActivityLog`

**Migration:** `database/migrations/2026_08_19_000001_create_appraisal_activity_logs_table.php`
**Model:** `app/Models/AppraisalActivityLog.php`

Append-only audit trail, one row per action.

| Column | Notes |
|---|---|
| `appraisal_id`, `user_id` | who did what, on which appraisal |
| `action` | `created`, `updated`, `submitted`, `review_updated`, `completed`, `reopened` |
| `old_status`, `new_status` | populated for status-changing actions |
| `reason` | required on `reopened`, null otherwise |
| `changed_fields` | JSON array of field names touched (for `updated`/`review_updated`) |
| `created_at` | no `updated_at` — rows are never edited |

`Appraisal::activityLogs()` (`app/Models/Appraisal.php`) exposes these,
newest first. `AppraisalController::show()`/`review()` pass them to Vue only
when the viewer passes `can('manage', $appraisal)` — i.e. only managers see
history, not the employee or their supervisor.

## Controller changes

**File:** `app/Http/Controllers/Web/AppraisalController.php`

- Now depends on `AppraisalWorkflowService` (constructor-injected).
- Every action authorizes via `$this->authorize(...)` against
  `AppraisalPolicy` instead of inline checks (requires the base
  `App\Http\Controllers\Controller` to `use AuthorizesRequests`, added in
  `app/Http/Controllers/Controller.php`).
- `store`, `update`, `submit`, `updateReview`, `complete`, `reopen` all
  delegate their writes to `AppraisalWorkflowService`.
- `reopen` now validates a required `reason` string (max 1000 chars) and
  passes it through to the service/log.
- `reviewIndex`'s ordering switched from the MySQL-only
  `orderByRaw("FIELD(status,'submitted','completed')")` to a portable
  `CASE WHEN` expression.

## Frontend changes

**Files:** `resources/js/Pages/Appraisals/Show.vue`,
`resources/js/Pages/Appraisals/Review.vue`

- The "Reopen" button now prompts for a reason (`window.prompt`) and posts
  it with the reopen request; it's a no-op if the reason is left blank.
- A manager-only, collapsible **History** section (`<details>`) lists each
  `activityLogs` entry: actor, action, old→new status, timestamp, and reason
  (if any).

## Tests (new — none existed before)

**Files:** `tests/Feature/AppraisalPolicyTest.php`,
`tests/Feature/AppraisalWorkflowTest.php`, plus scaffolding
(`phpunit.xml`, `tests/TestCase.php`, `tests/CreatesApplication.php`).

Covers: employee create/edit/submit-own-draft, edit blocked after submit,
cross-employee access blocked, supervisor can only review their assigned
appraisal, reopen requires a reason and is manager-only, delete is
admin-only, invalid transitions throw, and an activity-log row is created
for every action in a full submit → review → complete → reopen cycle.

Run with `php artisan test --filter=Appraisal` against a MySQL database you
control (the bundled `phpunit.xml` defaults to SQLite in-memory, but an
existing unrelated migration uses MySQL-only raw SQL and will fail there).

## Out of scope for this pass

New statuses (`under_review`, `cancelled`), appraisal cycles, a
template/question engine, goals/development planning, notifications,
review-deadline tracking, dashboards, employee-info snapshotting, and
supervisor-reassignment history were **not** implemented — they were
deferred as later slices of a larger improvement spec.

# Staff Performance Appraisal

## Overview

The Appraisal feature is a lightweight, self-service performance review cycle. An
employee completes a self-assessment, submits it for review, and their assigned
supervisor (or a manager) completes the review and signs off. Managers (admins/
directors) can also start appraisals on behalf of staff, reopen a completed cycle,
or delete records.

## Data Model

**Model:** `app/Models/Appraisal.php`
**Migration:** `database/migrations/2026_08_18_000002_create_appraisals_table.php`

| Field | Notes |
|---|---|
| `user_id` | The employee being appraised (`belongsTo User`) |
| `supervisor_id` | Assigned reviewer, nullable (`belongsTo User`) |
| `job_title`, `department`, `employee_number` | Snapshot of employee details at time of review |
| `start_date` | Employee's start date |
| `reviewer_name`, `date_of_review`, `next_review_date` | Review scheduling fields |
| `overall_rating` | Tinyint (1-5) score set by supervisor |
| `employee_responses` | JSON — self-assessment answers |
| `supervisor_responses` | JSON — supervisor's review answers |
| `status` | Enum: `draft` → `submitted` → `completed` |
| `submitted_at`, `completed_at` | Timestamps for status transitions |
| `employee_signed_at`, `supervisor_signed_at` | Sign-off timestamps |

Indexes: `[user_id, status]` and `[supervisor_id, status]` for fast filtering of
"my appraisals" and "my review queue".

## Workflow

1. **Create (draft)** — An employee starts their own appraisal, or a manager
   (`admin`/`director`) starts one on behalf of a staff member. The employee's
   current supervisor is auto-assigned as `supervisor_id`.
2. **Self-assessment** — While `status = draft`, the owner (or a manager) can
   edit job details and `employee_responses`.
3. **Submit** — The employee submits the draft. Status moves to `submitted`,
   and `submitted_at` / `employee_signed_at` are stamped.
4. **Supervisor review** — The assigned supervisor (or a manager) fills in
   `supervisor_responses`, sets `overall_rating` (1-5), and review dates via
   the Review page.
5. **Complete** — The supervisor completes the review. Status moves to
   `completed`, stamping `completed_at` / `supervisor_signed_at`.
6. **Reopen (managers only)** — A manager can roll a `submitted` or
   `completed` appraisal back a stage for correction.
7. **Delete (admin only)** — Admins can permanently remove an appraisal.

## Authorization

There is no dedicated Policy class — access checks live inline in the
controller:

- `isManager()` — role is `admin` or `director`
- `authorizeView()` — owner, assigned supervisor, or manager
- `canEditSelf()` — owner + `draft` status, or manager
- `canEditReview()` — assigned supervisor + `submitted` status, or manager
- Delete is restricted to `admin`; reopen is restricted to managers

## Controller & Routes

**Controller:** `app/Http/Controllers/Web/AppraisalController.php`

| Action | Purpose |
|---|---|
| `index` | List appraisals (scoped to self/supervisor unless manager) |
| `store` | Create a new draft |
| `show` | View a single appraisal |
| `reviewIndex` | Supervisor's review queue (excludes drafts) |
| `review` | Supervisor review page for one appraisal |
| `update` | Employee edits (draft only) |
| `submit` | draft → submitted |
| `updateReview` | Supervisor fills in review fields |
| `complete` | submitted → completed |
| `reopen` | Manager rewinds status |
| `destroy` | Admin deletes |

**Routes:** `routes/web.php` (~lines 93-106)

```
GET/POST   appraisals
GET/PUT/DELETE appraisals/{appraisal}
POST       appraisals/{appraisal}/submit
POST       appraisals/{appraisal}/complete
POST       appraisals/{appraisal}/reopen
GET        appraisal-reviews
GET/PUT    appraisals/{appraisal}/review
```

## Frontend

Vue/Inertia pages under `resources/js/Pages/Appraisals/`:

- `Index.vue` — employee's list of appraisals, entry point to start a new one
- `Show.vue` — employee's own appraisal form/detail view
- `ReviewIndex.vue` — supervisor's landing page listing appraisals awaiting review
- `Review.vue` — supervisor review form (responses, rating, sign-off)

## Notifications

None currently. Status changes only produce flash session `success` messages —
there is no email/notification sent to the employee or supervisor when an
appraisal is submitted, reviewed, or completed. This could be a future
enhancement.

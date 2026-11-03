# Leave module

Staff apply for leave in the staff app. Their line manager approves or rejects it in the app or the CRM. Leave admins manage leave types, entitlements, public holidays and reports in the CRM, and can correct a decision afterwards.

## Setup

1. Run the migration. It creates `leave_types`, `leave_balances`, `leave_requests` and `public_holidays`:
   ```bash
   php artisan migrate
   ```
2. Add the default leave types (Annual, Sick, Maternity, Paternity, Compassionate, Unpaid) and Zimbabwe's public holidays for this year and next:
   ```bash
   php artisan db:seed --class=LeaveSeeder
   ```
   You can re-run it safely; it leaves existing rows alone. Run it each January to add the next year's holidays. Check the default day counts on **Leave Types** against your contracts.
3. For each person, set **Supervisor (line manager)** on **Users**. Leave uses this existing `supervisor_id` field; there is no separate manager field.
4. On **Users** or **Roles**, tick **Leave: approve for direct reports** (`leave_approve`) for line managers, and **Leave Admin** (`leave_admin`) for HR.

Nothing else needs to be set up. The app's API is under `/api/v1/leave` and uses the same login token as the rest of the app.

## Who can do what

| Who | How they get it | What they can do |
|---|---|---|
| Leave admin | CRM role **admin** or **director**, or the `leave_admin` permission | Everything: all requests, leave types, entitlements, public holidays, balances, reports (with CSV export), and correcting decisions |
| Approver | The `leave_approve` permission, **and** being the requester's supervisor | See and decide pending requests from their direct reports (a comment is required to reject), and see the team calendar |
| Any active staff member | Being able to log in | Apply, see their own balances and requests, and cancel their own pending request |
| Deactivated user | Not applicable | Cannot log in. Any app token they already had stops working straight away (401 `account_disabled`) |

Rules that apply to everyone:
- **No self-approval.** Nobody can approve, reject or correct their own request, including admins.
- **Approvers see only their direct reports.** People whose supervisor is someone else never appear for them.
- **Cancelling.** Only the owner can cancel, and only while the request is pending.
- **Corrections.** Only leave admins can correct an approved or rejected request, and they must give a reason. The old and new status and comment, and the reason, go to the **Audit Trail**.
- **Everything is checked on the server.** The app shows everyone the same menus. A screen someone has no access to opens empty, or shows the server's 403 message.

## Business rules

- **Day count:** working days only (Monday to Friday), excluding public holidays.
- **Refused requests:**
  - the end date is before the start date;
  - the request crosses 31 December (apply for each year separately);
  - the dates overlap the person's own pending or approved leave;
  - the days exceed what is left after approved and pending leave (unpaid leave has no limit);
  - the leave type needs a supporting document and none is attached (PDF, JPG or PNG, up to 5 MB).
- **Balances:** each person has a balance per leave type per year. Until an admin sets one, it starts at the leave type's default. Days are deducted only on approval, inside a database transaction. Correcting an approved request to rejected or cancelled gives them back.
- **Notifications:** the line manager gets a CRM notification when a request is submitted, and the employee when it is approved or rejected. These use the CRM's existing database notifications (the bell).

## Where the code is

| Part | Location |
|---|---|
| Access rules | `app/Support/Leave/LeaveAccess.php`, `app/Policies/LeaveRequestPolicy.php` |
| Rules and balances | `app/Services/Leave/` (`LeaveService`, `WorkingDays`, `LeaveSummary`, `LeaveReport`, `LeaveAuditor`) |
| API | `routes/leave.php`, `app/Http/Controllers/Api/V1/Leave/`, `app/Http/Requests/Leave/`, `app/Http/Resources/Leave/` |
| CRM pages | `app/Http/Controllers/Web/LeaveController.php`, `resources/js/Pages/Leave/` |
| Blocking deactivated users | `app/Http/Middleware/EnsureUserIsActive.php` (alias `active.user`). The **Deactivate** button on **Users** also revokes the user's app tokens |
| Tests | `tests/Feature/Leave/` |

## API

All under `/api/v1/leave`, using a Sanctum bearer token.

| Method and path | Who | Notes |
|---|---|---|
| `GET types`, `GET holidays` | Everyone | Admins can pass `?all=1` to include inactive types |
| `GET balances?year=` | Everyone | Their own balances |
| `GET working-days?start_date=&end_date=` | Everyone | The apply screen's live day count |
| `GET requests?status=&year=`, `POST requests` | Everyone | Their own requests. Send `multipart/form-data` when there is an `attachment` |
| `GET requests/{id}`, `GET requests/{id}/attachment` | Owner, their approver, admins | |
| `PATCH requests/{id}/cancel` | Owner, while pending | |
| `GET pending` | Everyone | Approvers get their direct reports' requests, admins get everyone's, others get an empty list |
| `PATCH requests/{id}/approve`, `PATCH requests/{id}/reject` | Approver (direct reports) or admin, never their own | `comment` is required to reject |
| `GET calendar?from=&to=` | Approvers and admins | |
| `PATCH requests/{id}/correct` | Admins | `status` (approved, rejected or cancelled), `reason` (required) and an optional `comment` |
| `GET reports?year=&format=csv` | Admins | |
| `POST/PUT/DELETE types`, `POST/PUT/DELETE holidays`, `GET/POST/DELETE entitlements` | Admins | |

Every request in a response includes `can: {cancel, decide}`, so the app knows which buttons to show without any permission logic of its own.

## Tests

```bash
php artisan test tests/Feature/Leave
```

| File | What it covers |
|---|---|
| `LeaveAccessTest` | Every row of the table above, both the allowed actions and the refused ones; no self-approval; direct-reports-only scoping; deactivated users |
| `LeaveRulesTest` | Weekend and holiday counting; Zimbabwe holiday dates; overlap; balance; unpaid leave; attachments; crossing the year end; approval deducting the balance; correction restoring it and writing the audit entry; notifications |
| `LeaveCrmPagesTest` | Permission tick boxes; page access for staff, approvers and directors; approving and correcting in the CRM |

# Work Management Module (Backend Core)

A lightweight, native (no Monday.com dependency) work-management module:
`Workspace → Board → TaskGroup → Task → Subtask`, following the same
architectural pattern as the hardened [Appraisal module](appraisal-foundation-hardening.md).

This pass covers **backend core only** — migrations, models, policies,
`TaskWorkflowService`, controllers, Form Requests, and routes. No Vue pages
exist yet (list/Kanban views, task detail panel, drag-and-drop are a
follow-up).

## Data model

| Table | Notes |
|---|---|
| `workspaces` | `owner_id`, `name`, `description`, `is_archived`; soft-deletes |
| `boards` | belongs to a workspace and an owner; soft-deletes |
| `task_groups` | belongs to a board; `position`, `color`, `is_archived` (model is `TaskGroup`, table `task_groups` — avoids clashing with any `Group` concept elsewhere) |
| `tasks` | belongs to a board, optionally a group, optionally a parent task (`parent_id` → subtask); `status`, `priority` as plain strings (see below), `start_date`/`due_date`/`completed_at`; soft-deletes |
| `task_user` | many-to-many task ↔ assignee, with `assigned_by` |
| `task_watchers` | many-to-many task ↔ watcher |
| `task_comments` | chronological discussion per task |
| `task_activity_logs` | append-only audit trail, same shape as `AppraisalActivityLog` |

`status`/`priority` are plain strings, not DB enums — the valid values and
the status transition map live in `App\Support\Tasks\TaskStatus` and
`App\Support\Tasks\TaskPriority` so new values can be added without a
migration.

### Status transitions

```
not_started → in_progress, cancelled
in_progress → blocked, completed, cancelled
blocked     → in_progress, cancelled
completed   → in_progress
cancelled   → not_started
```

Anything not listed is rejected by `TaskWorkflowService::changeStatus()`.

## `TaskWorkflowService`

**File:** `app/Services/TaskWorkflowService.php`

Same shape as `AppraisalWorkflowService`: every write (`create`, `update`,
`assign`, `changeStatus`, `changePriority`, `addComment`, `complete`,
`reopen`, `archive`, `restore`) happens inside `DB::transaction()` together
with a `TaskActivityLog` row, so a failure in one rolls back the other.
`complete()` delegates to `changeStatus()`; `reopen()` only accepts a
`completed` task and requires a reason.

## Policies

- `WorkspacePolicy` — anyone can view/create; only the owner or a manager
  (`admin`/`director`) can update/delete.
- `BoardPolicy` — same shape, plus `manageGroups` (create/rename/reorder/
  archive the board's `TaskGroup`s), which just delegates to `update`.
- `TaskPolicy` — `view` allows manager, creator, board owner, assignee, or
  watcher; `update`/`changeStatus`/`changePriority`/`archive`/`restore`
  allow manager, creator, or assignee; `assign` allows manager, board owner,
  or creator; `comment` mirrors `view`; `delete` is admin-only; `manage`
  (gates the activity-log view) is manager-only.

## Controllers & routes

`WorkspaceController`, `BoardController`, `TaskGroupController`,
`TaskController` — all thin, delegating writes to `TaskWorkflowService` and
authorizing via the policies above. `BoardController::show()` accepts
`status`, `priority`, `assignee`, `search`, `overdue` query params and
applies them server-side via scopes on `Task` (`scopeOpen`, `scopeOverdue`,
`scopeAssignedTo`, `scopeHighPriority`), per the "don't filter thousands of
tasks in the browser" requirement.

Routes added in `routes/web.php` under the `auth` group: `/workspaces`,
`/boards` (+ nested `/boards/{board}/groups`), `/tasks/{task}` and its
action endpoints (`/assign`, `/status`, `/priority`, `/complete`, `/reopen`,
`/archive`, `/restore`, `/comments`).

## Form Requests

`StoreWorkspaceRequest`, `UpdateWorkspaceRequest`, `StoreBoardRequest`,
`UpdateBoardRequest`, `StoreTaskRequest`, `UpdateTaskRequest`,
`AssignTaskRequest`, `ChangeTaskStatusRequest`, `ChangeTaskPriorityRequest`,
`StoreTaskCommentRequest`, `ReopenTaskRequest` — each calls the relevant
policy in `authorize()`. `StoreTaskRequest` validates that `group_id` and
`parent_id` belong to the same `board_id`, and `due_date >= start_date`.

## Tests

`tests/Feature/TaskPolicyTest.php`, `tests/Feature/TaskWorkflowTest.php` —
same pattern as the Appraisal tests (view/update/delete authorization,
invalid-transition rejection, activity-log creation across a full
create→assign→status→priority→comment→complete→reopen→archive→restore
lifecycle, subtask progress calculation).

Verified via a standalone script run inside a rolled-back DB transaction
against the real MySQL database (not via `php artisan test`, since
`RefreshDatabase` would migrate:fresh the shared `crm` database — see the
same caveat in the Appraisal docs). The committed tests are for you to run
against a MySQL instance you control.

## Out of scope for this pass

Vue pages (list view, Kanban board, drag-and-drop, task detail panel),
notifications, dashboards/analytics beyond raw counts already exposed in
`BoardController::show()`, and any integration back into the Appraisal
module (e.g. turning a development goal into a task) — all deferred per the
spec's own phase ordering.

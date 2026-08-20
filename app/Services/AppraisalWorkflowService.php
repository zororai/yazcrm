<?php

namespace App\Services;

use App\Models\Appraisal;
use App\Models\AppraisalActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AppraisalWorkflowService
{
    public function __construct(private readonly AppraisalTaskGenerator $taskGenerator)
    {
    }

    private const TRANSITIONS = [
        'submit'   => ['draft' => 'submitted'],
        'complete' => ['submitted' => 'completed'],
        'reopen'   => ['submitted' => 'draft', 'completed' => 'submitted'],
    ];

    private const LOG_ACTIONS = [
        'submit'   => 'submitted',
        'complete' => 'completed',
        'reopen'   => 'reopened',
    ];

    public function create(User $actor, array $attributes): Appraisal
    {
        return DB::transaction(function () use ($actor, $attributes) {
            $appraisal = Appraisal::create($attributes + ['status' => 'draft']);

            $this->log($appraisal, $actor, 'created', null, 'draft');

            return $appraisal;
        });
    }

    public function update(Appraisal $appraisal, User $actor, array $data, string $action = 'updated'): Appraisal
    {
        return DB::transaction(function () use ($appraisal, $actor, $data, $action) {
            $appraisal->update($data);

            $this->log($appraisal, $actor, $action, null, null, null, array_keys($data));

            return $appraisal;
        });
    }

    public function submit(Appraisal $appraisal, User $actor): Appraisal
    {
        return $this->transition($appraisal, $actor, 'submit', [
            'submitted_at'       => now(),
            'employee_signed_at' => now(),
        ]);
    }

    public function complete(Appraisal $appraisal, User $actor): Appraisal
    {
        $appraisal = $this->transition($appraisal, $actor, 'complete', [
            'completed_at'         => now(),
            'supervisor_signed_at' => now(),
        ]);

        // Runs after the appraisal transaction has committed — task creation
        // has its own transactional/audit-log handling and shouldn't be
        // nested inside (or able to roll back) the appraisal's own write.
        $this->taskGenerator->generate($appraisal, $actor);

        return $appraisal;
    }

    public function reopen(Appraisal $appraisal, User $actor, string $reason): Appraisal
    {
        return $this->transition($appraisal, $actor, 'reopen', [
            'completed_at' => null,
        ], $reason);
    }

    private function transition(Appraisal $appraisal, User $actor, string $action, array $extra, ?string $reason = null): Appraisal
    {
        $from = $appraisal->status;
        $map  = self::TRANSITIONS[$action];

        if (! array_key_exists($from, $map)) {
            throw new RuntimeException("Cannot {$action} an appraisal in status '{$from}'.");
        }

        $to = $map[$from];

        return DB::transaction(function () use ($appraisal, $actor, $action, $from, $to, $extra, $reason) {
            $appraisal->update(['status' => $to] + $extra);

            $this->log($appraisal, $actor, self::LOG_ACTIONS[$action], $from, $to, $reason);

            return $appraisal;
        });
    }

    private function log(
        Appraisal $appraisal,
        User $actor,
        string $action,
        ?string $oldStatus = null,
        ?string $newStatus = null,
        ?string $reason = null,
        ?array $changedFields = null
    ): void {
        AppraisalActivityLog::create([
            'appraisal_id'   => $appraisal->id,
            'user_id'        => $actor->id,
            'action'         => $action,
            'old_status'     => $oldStatus,
            'new_status'     => $newStatus,
            'reason'         => $reason,
            'changed_fields' => $changedFields,
        ]);
    }
}

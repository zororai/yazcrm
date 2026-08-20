<?php

namespace App\Services;

use App\Models\Appraisal;
use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;

class AppraisalTaskGenerator
{
    // supervisor_responses key => task title prefix
    private const SOURCE_FIELDS = [
        'training_needs'              => 'Training',
        'mentorship'                  => 'Mentorship',
        'action_plan'                 => 'Action Plan',
        'recommendations_improvement' => 'Improvement',
    ];

    public function __construct(private readonly TaskWorkflowService $tasks)
    {
    }

    // Called after an appraisal is completed — turns the supervisor's
    // development-goal text fields into tracked tasks assigned to the employee.
    public function generate(Appraisal $appraisal, User $actor): void
    {
        $responses = $appraisal->supervisor_responses ?? [];
        $board     = $this->developmentBoard($actor);
        $employee  = $appraisal->user;

        foreach (self::SOURCE_FIELDS as $field => $label) {
            $text = trim((string) ($responses[$field] ?? ''));

            if ($text === '') {
                continue;
            }

            $task = $this->tasks->create($actor, [
                'board_id'     => $board->id,
                'appraisal_id' => $appraisal->id,
                'title'        => "{$employee->name}: {$label}",
                'description'  => $text,
                'due_date'     => $appraisal->next_review_date,
            ]);

            if ($employee) {
                $this->tasks->assign($task, $actor, [$employee->id]);
            }
        }
    }

    private function developmentBoard(User $actor): Board
    {
        $workspace = Workspace::firstOrCreate(
            ['name' => 'Staff Development'],
            ['owner_id' => $actor->id, 'description' => 'Development actions generated from completed appraisals.']
        );

        return Board::firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'Development Actions'],
            ['owner_id' => $actor->id]
        );
    }
}

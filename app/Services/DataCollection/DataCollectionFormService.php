<?php

namespace App\Services\DataCollection;

use App\Models\DataCollectionActivityLog;
use App\Models\DataCollectionForm;
use App\Models\DataCollectionFormVersion;
use App\Models\DataCollectionProject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DataCollectionFormService
{
    public function __construct(private readonly FormSchemaValidationService $validator)
    {
    }

    public function createProject(User $actor, array $attributes): DataCollectionProject
    {
        return DB::transaction(function () use ($actor, $attributes) {
            $project = DataCollectionProject::create($attributes + [
                'status'     => 'draft',
                'created_by' => $actor->id,
            ]);

            $this->log($actor, 'project_created', projectId: $project->id);

            return $project;
        });
    }

    public function updateProject(DataCollectionProject $project, User $actor, array $data): DataCollectionProject
    {
        return DB::transaction(function () use ($project, $actor, $data) {
            $project->update($data);

            $this->log($actor, 'project_updated', projectId: $project->id, changedFields: array_keys($data));

            return $project;
        });
    }

    public function createForm(DataCollectionProject $project, User $actor, array $attributes): DataCollectionForm
    {
        return DB::transaction(function () use ($project, $actor, $attributes) {
            $form = DataCollectionForm::create($attributes + [
                'project_id' => $project->id,
                'status'     => 'draft',
                'created_by' => $actor->id,
            ]);

            // Every form starts with an empty draft v1 so there's always
            // something to build in the schema builder immediately.
            $version = DataCollectionFormVersion::create([
                'form_id'        => $form->id,
                'version_number' => 1,
                'schema'         => ['title' => $form->name, 'sections' => []],
                'status'         => 'draft',
                'created_by'     => $actor->id,
            ]);

            $this->log($actor, 'form_created', projectId: $project->id, formId: $form->id, formVersionId: $version->id);

            return $form;
        });
    }

    public function updateForm(DataCollectionForm $form, User $actor, array $data): DataCollectionForm
    {
        return DB::transaction(function () use ($form, $actor, $data) {
            $form->update($data);

            $this->log($actor, 'form_updated', projectId: $form->project_id, formId: $form->id, changedFields: array_keys($data));

            return $form;
        });
    }

    // A published version is immutable — this creates the next draft version,
    // seeded from the currently published (or latest) version's schema.
    public function createNewVersion(DataCollectionForm $form, User $actor): DataCollectionFormVersion
    {
        return DB::transaction(function () use ($form, $actor) {
            $latest = $form->versions()->first();

            if ($latest && $latest->status === 'draft') {
                throw new RuntimeException('This form already has a draft version — edit it instead of starting another.');
            }

            $version = DataCollectionFormVersion::create([
                'form_id'        => $form->id,
                'version_number' => ($latest?->version_number ?? 0) + 1,
                'schema'         => $latest?->schema ?? ['title' => $form->name, 'sections' => []],
                'status'         => 'draft',
                'created_by'     => $actor->id,
            ]);

            $this->log($actor, 'version_created', projectId: $form->project_id, formId: $form->id, formVersionId: $version->id);

            return $version;
        });
    }

    public function updateVersionSchema(DataCollectionFormVersion $version, User $actor, array $schema): DataCollectionFormVersion
    {
        if ($version->status !== 'draft') {
            throw new RuntimeException('Only a draft version can be edited — published versions are immutable.');
        }

        return DB::transaction(function () use ($version, $actor, $schema) {
            $version->update(['schema' => $schema]);

            $this->log($actor, 'version_schema_updated', projectId: $version->form->project_id, formId: $version->form_id, formVersionId: $version->id);

            return $version;
        });
    }

    public function publishVersion(DataCollectionFormVersion $version, User $actor): DataCollectionFormVersion
    {
        if ($version->status !== 'draft') {
            throw new RuntimeException('Only a draft version can be published.');
        }

        $errors = $this->validator->validate($version->schema);
        if ($errors) {
            throw new RuntimeException(implode(' ', $errors));
        }

        return DB::transaction(function () use ($version, $actor) {
            $form = $version->form;

            // Retire whatever was previously published.
            DataCollectionFormVersion::where('form_id', $form->id)
                ->where('status', 'published')
                ->update(['status' => 'retired']);

            $version->update([
                'status'       => 'published',
                'published_at' => now(),
                'published_by' => $actor->id,
            ]);

            $form->update(['current_version_id' => $version->id, 'status' => 'published']);

            $this->log($actor, 'version_published', projectId: $form->project_id, formId: $form->id, formVersionId: $version->id);

            return $version;
        });
    }

    private function log(
        User $actor,
        string $action,
        ?int $projectId = null,
        ?int $formId = null,
        ?int $formVersionId = null,
        ?array $changedFields = null,
    ): void {
        DataCollectionActivityLog::create([
            'project_id'      => $projectId,
            'form_id'         => $formId,
            'form_version_id' => $formVersionId,
            'user_id'         => $actor->id,
            'action'          => $action,
            'changed_fields'  => $changedFields,
        ]);
    }
}

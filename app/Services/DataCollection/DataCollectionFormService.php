<?php

namespace App\Services\DataCollection;

use App\Models\DataCollectionActivityLog;
use App\Models\DataCollectionForm;
use App\Models\DataCollectionFormAssignment;
use App\Models\DataCollectionFormVersion;
use App\Models\DataCollectionProject;
use App\Models\User;
use App\Services\DataCollection\XlsForm\XlsFormParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

// Projects, forms and their versions. A form's questions come only from an
// uploaded XLSForm. Uploading creates a draft version (or replaces the
// current draft); deploying it makes it what the app downloads and archives
// the previous deployed version, which still accepts submissions from
// devices that haven't updated.
class DataCollectionFormService
{
    public function __construct(private readonly XlsFormParser $parser)
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

            $this->log($actor, 'form_created', projectId: $project->id, formId: $form->id);

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

    // Parses the XLSForm into a draft version. Problems are stored on the
    // version (and shown to the designer) rather than thrown.
    public function uploadXlsForm(DataCollectionForm $form, User $actor, UploadedFile $file): DataCollectionFormVersion
    {
        $result = $this->parser->parseFile($file->getRealPath());

        $path = $file->storeAs("data-collection/xlsforms/{$form->id}", Str::uuid().'.xlsx', 'local');

        return DB::transaction(function () use ($form, $actor, $file, $result, $path) {
            $latest = $form->versions()->lockForUpdate()->first();
            $draft = $latest?->status === 'draft' ? $latest : null;

            $schema = $result['schema'];
            if ($schema !== null) {
                $schema['title'] = $schema['title'] ?: $form->name;
            }

            $attributes = [
                'schema'        => $schema ?? ['children' => []],
                'errors'        => $result['errors'] ?: null,
                'checksum'      => null,
                'version_label' => $schema['settings']['version'] ?? null,
                'source_path'   => $path,
                'source_name'   => mb_substr($file->getClientOriginalName(), 0, 255),
                'created_by'    => $actor->id,
            ];

            if ($draft) {
                if ($draft->source_path) {
                    Storage::disk('local')->delete($draft->source_path);
                }
                $draft->update($attributes);
                $version = $draft;
            } else {
                $version = DataCollectionFormVersion::create($attributes + [
                    'form_id'        => $form->id,
                    'version_number' => ($latest?->version_number ?? 0) + 1,
                    'status'         => 'draft',
                ]);
            }

            $this->log($actor, 'xlsform_uploaded', projectId: $form->project_id, formId: $form->id, formVersionId: $version->id,
                changedFields: ['errors' => count($result['errors'])]);

            return $version;
        });
    }

    public function deployVersion(DataCollectionFormVersion $version, User $actor): DataCollectionFormVersion
    {
        if ($version->status !== 'draft') {
            throw new RuntimeException('Only a draft version can be deployed.');
        }
        if (! empty($version->errors)) {
            throw new RuntimeException('Fix the errors in the XLSForm and upload it again before deploying.');
        }
        if (! ($version->schema['children'] ?? [])) {
            throw new RuntimeException('This version has no questions.');
        }

        return DB::transaction(function () use ($version, $actor) {
            $form = $version->form()->lockForUpdate()->first();

            DataCollectionFormVersion::where('form_id', $form->id)
                ->where('status', 'deployed')
                ->update(['status' => 'archived']);

            $schema = $version->schema;
            $schema['version'] = $version->version_number;

            $version->update([
                'schema'      => $schema,
                'checksum'    => self::checksum($schema),
                'status'      => 'deployed',
                'deployed_at' => now(),
                'deployed_by' => $actor->id,
            ]);

            $form->update(['current_version_id' => $version->id, 'status' => 'deployed']);

            $this->log($actor, 'version_deployed', projectId: $form->project_id, formId: $form->id, formVersionId: $version->id);

            return $version;
        });
    }

    // Archived forms disappear from the app's form list; data already
    // collected for them is still accepted when it syncs.
    public function setArchived(DataCollectionForm $form, User $actor, bool $archived): void
    {
        $status = $archived ? 'archived' : ($form->current_version_id ? 'deployed' : 'draft');
        $form->update(['status' => $status]);

        $this->log($actor, $archived ? 'form_archived' : 'form_restored', projectId: $form->project_id, formId: $form->id);
    }

    // permissions: a subset of DataCollectionFormAssignment::PERMISSIONS.
    public function assign(DataCollectionForm $form, User $actor, ?int $userId, ?string $crmRole, array $permissions): DataCollectionFormAssignment
    {
        if (($userId === null) === ($crmRole === null)) {
            throw new RuntimeException('Assign the form to either a user or a role.');
        }
        $permissions = array_values(array_intersect(DataCollectionFormAssignment::PERMISSIONS, $permissions));
        if (! $permissions) {
            throw new RuntimeException('Choose at least one permission.');
        }

        $assignment = DataCollectionFormAssignment::updateOrCreate(
            ['form_id' => $form->id, 'user_id' => $userId, 'crm_role' => $crmRole],
            ['permissions' => $permissions, 'assigned_by' => $actor->id],
        );

        $this->log($actor, 'form_assigned', projectId: $form->project_id, formId: $form->id,
            changedFields: ['user_id' => $userId, 'crm_role' => $crmRole, 'permissions' => $permissions]);

        return $assignment;
    }

    public function unassign(DataCollectionFormAssignment $assignment, User $actor): void
    {
        $form = $assignment->form;
        $assignment->delete();

        $this->log($actor, 'form_unassigned', projectId: $form->project_id, formId: $form->id,
            changedFields: ['user_id' => $assignment->user_id, 'crm_role' => $assignment->crm_role]);
    }

    // sha256 of the schema with keys sorted at every level, so the same form
    // always hashes the same and any change to it is detected by the app.
    public static function checksum(array $schema): string
    {
        $normalise = function ($value) use (&$normalise) {
            if (! is_array($value)) {
                return $value;
            }
            $value = array_map($normalise, $value);
            if (! array_is_list($value)) {
                ksort($value);
            }

            return $value;
        };

        return hash('sha256', json_encode($normalise($schema), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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

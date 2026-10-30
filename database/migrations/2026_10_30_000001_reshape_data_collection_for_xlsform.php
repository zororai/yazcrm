<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Data collection moves from the built-in sections/questions builder to
// XLSForm upload + offline collection from the beneficiary app. The old
// submission/assignment/review tables were never used (all empty when this
// was written), so they're rebuilt to the new shape rather than converted.
// Refuses to run if any of them hold data, so nothing is silently lost.
return new class extends Migration
{
    private array $rebuilt = [
        'data_collection_submission_reviews',
        'data_collection_submissions',
        'data_collection_form_assignments',
    ];

    public function up(): void
    {
        foreach ($this->rebuilt as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException("{$table} has data — convert it by hand before reshaping data collection.");
            }
        }

        Schema::table('data_collection_activity_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submission_id');
        });
        foreach ($this->rebuilt as $table) {
            Schema::dropIfExists($table);
        }

        // Versions: draft|deployed|archived. A deployed version is immutable;
        // editing a form uploads a new version and archives the old one,
        // which stays valid for devices that still have it.
        Schema::table('data_collection_form_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_by');
            $table->dropColumn('published_at');
        });
        Schema::table('data_collection_form_versions', function (Blueprint $table) {
            $table->string('checksum', 64)->nullable()->after('schema');
            $table->json('errors')->nullable()->after('checksum'); // [{sheet,row,column,message}] from the XLSForm upload
            $table->string('source_path')->nullable()->after('errors');
            $table->string('source_name')->nullable()->after('source_path');
            $table->timestamp('deployed_at')->nullable()->after('status');
            $table->foreignId('deployed_by')->nullable()->after('deployed_at')->constrained('users')->nullOnDelete();
        });

        // Per-form access. A row grants a user, or everyone with a CRM role,
        // a set of permissions on one form: collect|view|edit|export|map.
        Schema::create('data_collection_form_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('data_collection_forms')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('crm_role')->nullable(); // matches users.role
            $table->json('permissions');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['form_id', 'user_id'], 'dc_assignments_form_user_unique');
            $table->unique(['form_id', 'crm_role'], 'dc_assignments_form_role_unique');
            $table->index('user_id');
            $table->index('crm_role');
        });

        Schema::create('data_collection_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->foreignId('form_id')->constrained('data_collection_forms')->cascadeOnDelete();
            $table->foreignId('form_version_id')->constrained('data_collection_form_versions')->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->json('data');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('device_id')->nullable();
            $table->string('app_version', 50)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedTinyInteger('completion')->nullable(); // % of visible questions answered
            $table->string('status')->default('pending'); // pending|approved|not_approved|on_hold
            $table->text('status_note')->nullable();
            $table->foreignId('status_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('status_at')->nullable();
            $table->string('crm_record_type')->nullable(); // beneficiary|contact|case|activity
            $table->unsignedBigInteger('crm_record_id')->nullable();
            $table->timestamp('processed_at')->nullable(); // projection + CRM mapping done
            $table->timestamps();
            $table->softDeletes();

            $table->index(['form_id', 'collected_at']);
            $table->index(['form_id', 'status']);
            $table->index(['submitted_by', 'collected_at']);
            $table->index('collected_at');
            $table->index('status');
        });

        Schema::create('data_collection_submission_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('data_collection_submissions')->cascadeOnDelete();
            $table->string('field_name');
            $table->string('disk', 50);
            $table->string('path');
            $table->string('original_name'); // the device's filename — matching key only, never used as a path
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->string('checksum', 64);
            $table->timestamps();

            $table->unique(['submission_id', 'field_name', 'original_name'], 'dc_media_submission_field_name_unique');
        });

        // Edits and status changes. Never updated or deleted.
        Schema::create('data_collection_submission_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('data_collection_submissions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // received|edited|status_changed|deleted|restored|mapped
            $table->json('old')->nullable();
            $table->json('new')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['submission_id', 'created_at'], 'dc_audits_submission_created_idx');
        });

        // Flattened answers, rebuilt by the queued job after each accept or
        // edit, so the summary report / filters / cross-tabs run as indexed
        // SQL instead of decoding JSON for every row. One row per answer; a
        // select_multiple gives one row per chosen option, repeat answers
        // carry their instance number.
        Schema::create('data_collection_submission_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('data_collection_submissions')->cascadeOnDelete();
            $table->foreignId('form_id')->constrained('data_collection_forms')->cascadeOnDelete();
            $table->string('field', 191); // repeat children: "repeat_name/child_name"
            $table->unsignedSmallInteger('instance')->nullable();
            $table->string('value', 191)->nullable();
            $table->decimal('value_num', 20, 6)->nullable();
            $table->date('value_date')->nullable();

            $table->index(['form_id', 'field', 'value'], 'dc_values_form_field_value_idx');
            $table->index(['form_id', 'field', 'value_num'], 'dc_values_form_field_num_idx');
            $table->index(['form_id', 'field', 'value_date'], 'dc_values_form_field_date_idx');
            $table->index('submission_id');
        });

        // Items refused by POST /submissions/batch — reason and field errors
        // only, never the submitted data.
        Schema::create('data_collection_rejections', function (Blueprint $table) {
            $table->id();
            $table->string('client_uuid', 64)->nullable();
            $table->unsignedBigInteger('form_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('device_id')->nullable();
            $table->string('reason');
            $table->json('errors')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['form_id', 'created_at']);
        });

        // CRM mapping: one config per form, plus a log row per run.
        Schema::create('data_collection_form_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->unique()->constrained('data_collection_forms')->cascadeOnDelete();
            $table->string('target');                            // beneficiary|contact|case|activity
            $table->string('mode')->default('store_only');       // create|update|store_only
            $table->string('match_crm_field')->nullable();       // e.g. phone_number
            $table->string('match_form_field')->nullable();      // e.g. respondent_phone
            $table->json('field_map');                           // {crm_field: form_field}
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('data_collection_mapping_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('data_collection_submissions')->cascadeOnDelete();
            $table->foreignId('form_id')->constrained('data_collection_forms')->cascadeOnDelete();
            $table->string('result');                            // created|updated|skipped|duplicate|failed
            $table->string('crm_record_type')->nullable();
            $table->unsignedBigInteger('crm_record_id')->nullable();
            $table->text('error')->nullable();
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->timestamps();

            $table->unique('submission_id');
            $table->index(['form_id', 'result']);
        });

        Schema::create('data_collection_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('data_collection_forms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('format', 20);                        // csv|xlsx|geojson|pdf
            $table->json('options')->nullable();                 // filters, labels vs values
            $table->string('status')->default('queued');         // queued|running|ready|failed
            $table->string('disk', 50)->nullable();
            $table->string('path')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['form_id', 'created_at']);
        });

        Schema::table('data_collection_activity_logs', function (Blueprint $table) {
            $table->foreignId('submission_id')->nullable()->after('form_version_id')
                ->constrained('data_collection_submissions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('data_collection_activity_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submission_id');
        });
        foreach ([
            'data_collection_exports', 'data_collection_mapping_logs', 'data_collection_form_mappings',
            'data_collection_rejections', 'data_collection_submission_values', 'data_collection_submission_audits',
            'data_collection_submission_media', 'data_collection_submissions', 'data_collection_form_assignments',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('data_collection_form_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deployed_by');
            $table->dropColumn(['checksum', 'errors', 'source_path', 'source_name', 'deployed_at']);
        });
        Schema::table('data_collection_form_versions', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
        });

        // The original assignment/submission/review tables come back empty,
        // as created by 2026_08_21_000008/9/11.
        Schema::create('data_collection_form_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('data_collection_forms')->cascadeOnDelete();
            $table->foreignId('form_version_id')->constrained('data_collection_form_versions')->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('assigned');
            $table->timestamps();
        });
        Schema::create('data_collection_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('submission_uid')->unique();
            $table->foreignId('project_id')->constrained('data_collection_projects')->cascadeOnDelete();
            $table->foreignId('form_id')->constrained('data_collection_forms')->cascadeOnDelete();
            $table->foreignId('form_version_id')->constrained('data_collection_form_versions')->cascadeOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('data_collection_form_assignments')->nullOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->json('answers')->nullable();
            $table->unsignedTinyInteger('completion_percentage')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('data_collection_submission_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('data_collection_submissions')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('decision');
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::table('data_collection_activity_logs', function (Blueprint $table) {
            $table->foreignId('submission_id')->nullable()->after('form_version_id')
                ->constrained('data_collection_submissions')->nullOnDelete();
        });
    }
};

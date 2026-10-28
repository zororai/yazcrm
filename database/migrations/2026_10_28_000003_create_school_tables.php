<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// School module (Rose Primary): classes, subjects and who teaches what,
// students, daily class registers, teacher sign-in, termly marks and the
// scheme of work (topics a teacher plans to cover, ticked off as taught).
// Teachers are ordinary users with the 'school' nav permission.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "Grade 3 Blue"
            $table->string('grade_level', 50)->nullable(); // e.g. "ECD A", "Grade 3"
            $table->unsignedSmallInteger('academic_year');
            $table->foreignId('class_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('school_subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('school_teaching_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('school_subject_id')->constrained('school_subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['school_class_id', 'school_subject_id']); // one teacher per subject per class
        });

        Schema::create('school_students', function (Blueprint $table) {
            $table->id();
            $table->string('admission_number', 50)->unique();
            $table->string('first_name');
            $table->string('surname');
            $table->enum('gender', ['M', 'F'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->foreignId('school_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('school_student_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_student_id')->constrained('school_students')->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete(); // class at the time
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'late', 'excused']);
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_student_id', 'date']);
            $table->index(['school_class_id', 'date']);
        });

        Schema::create('school_teacher_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'late', 'leave']);
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['teacher_id', 'date']);
        });

        Schema::create('school_student_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_student_id')->constrained('school_students')->cascadeOnDelete();
            $table->foreignId('school_subject_id')->constrained('school_subjects')->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->unsignedSmallInteger('academic_year');
            $table->unsignedTinyInteger('term'); // 1–3
            $table->string('assessment', 50); // e.g. "Mid-term", "End of term"
            $table->decimal('mark', 5, 2); // percentage, 0–100
            $table->string('comment')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['school_student_id', 'school_subject_id', 'academic_year', 'term', 'assessment'],
                'school_results_unique'
            );
        });

        Schema::create('school_scheme_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('school_subject_id')->constrained('school_subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('academic_year');
            $table->unsignedTinyInteger('term');
            $table->unsignedTinyInteger('week')->nullable();
            $table->string('topic');
            $table->text('objectives')->nullable();
            $table->date('planned_date')->nullable();
            $table->enum('status', ['planned', 'in_progress', 'completed', 'skipped'])->default('planned');
            $table->date('completed_date')->nullable();
            $table->text('notes')->nullable(); // teacher's reflection once taught
            $table->timestamps();

            $table->index(['school_class_id', 'school_subject_id', 'academic_year', 'term'], 'school_topics_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_scheme_topics');
        Schema::dropIfExists('school_student_results');
        Schema::dropIfExists('school_teacher_attendances');
        Schema::dropIfExists('school_student_attendances');
        Schema::dropIfExists('school_students');
        Schema::dropIfExists('school_teaching_assignments');
        Schema::dropIfExists('school_subjects');
        Schema::dropIfExists('school_classes');
    }
};

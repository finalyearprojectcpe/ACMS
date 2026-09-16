<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Stores one student set's semester result sheet until the course adviser publishes it.
        Schema::create('result_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advisor_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_set_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_semester_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->unsignedInteger('current_revision_number')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['student_set_id', 'academic_session_id', 'academic_year_semester_id'],
                'result_sheets_context_unique'
            );
            $table->index(['advisor_profile_id', 'status']);
            $table->index(['student_set_id', 'academic_session_id']);
        });

        // Stores the course columns for a result sheet with title and units snapshotted for transcripts.
        Schema::create('result_sheet_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_sheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_offering_id')->constrained()->restrictOnDelete();
            $table->string('course_code');
            $table->string('course_title');
            $table->unsignedTinyInteger('credit_units');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['result_sheet_id', 'course_code']);
            $table->index(['course_code', 'result_sheet_id']);
        });

        // Stores one student row summary in a result sheet, including the calculated semester GPA.
        Schema::create('student_semester_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_sheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('total_units')->default(0);
            $table->unsignedSmallInteger('total_grade_points')->default(0);
            $table->decimal('gpa', 3, 2)->default(0);
            $table->timestamps();

            $table->unique(['result_sheet_id', 'student_profile_id'], 'student_semester_results_sheet_student_unique');
            $table->index(['student_profile_id', 'gpa']);
        });

        // Stores each grade cell for a student and course in a result sheet.
        Schema::create('student_course_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_semester_result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('result_sheet_course_id')->constrained()->cascadeOnDelete();
            $table->string('grade', 1);
            $table->unsignedTinyInteger('grade_point');
            $table->unsignedSmallInteger('quality_points');
            $table->timestamps();

            $table->unique(
                ['student_semester_result_id', 'result_sheet_course_id'],
                'student_course_results_row_course_unique'
            );
            $table->index(['result_sheet_course_id', 'grade']);
        });

        // Preserves each imported or reviewed version of a result sheet, including its source spreadsheet and grade snapshot.
        Schema::create('result_sheet_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_sheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('action');
            $table->string('source_file_disk')->nullable();
            $table->string('source_file_path')->nullable();
            $table->string('source_file_name')->nullable();
            $table->string('source_file_checksum', 64)->nullable();
            $table->json('column_mappings')->nullable();
            $table->json('snapshot');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['result_sheet_id', 'revision_number']);
            $table->index(['result_sheet_id', 'action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('result_sheet_revisions');
        Schema::dropIfExists('student_course_results');
        Schema::dropIfExists('student_semester_results');
        Schema::dropIfExists('result_sheet_courses');
        Schema::dropIfExists('result_sheets');
    }
};

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
        // Stores a student's semester course registration for adviser review and approval.
        Schema::create('course_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_semester_id')->constrained()->restrictOnDelete();
            $table->foreignId('reviewer_staff_profile_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->text('feedback_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['student_profile_id', 'academic_session_id', 'academic_year_semester_id'],
                'course_registrations_student_session_year_semester_unique'
            );
            $table->index(['department_id', 'academic_session_id', 'status'], 'course_registrations_department_session_status_index');
            $table->index(['reviewer_staff_profile_id', 'status'], 'course_registrations_reviewer_status_index');
        });

        // Stores the individual courses selected on a student's registration, including current, carryover, and outstanding requirement rows.
        Schema::create('course_registration_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_offering_id')->constrained()->restrictOnDelete();
            $table->string('source')->default('current_set');
            $table->string('category');
            $table->string('course_code');
            $table->string('course_title');
            $table->unsignedTinyInteger('credit_units');
            $table->timestamps();

            $table->unique(['course_registration_id', 'course_offering_id'], 'course_registration_items_registration_offering_unique');
            $table->index(['course_offering_id', 'source'], 'course_registration_items_offering_source_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_registration_items');
        Schema::dropIfExists('course_registrations');
    }
};

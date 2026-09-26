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
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_semester_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->text('feedback_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['student_profile_id', 'academic_session_semester_id']);
            $table->index(['academic_session_semester_id', 'status']);
        });

        // Stores the individual courses selected on a student's semester registration.
        Schema::create('course_registration_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_offering_id')->constrained()->restrictOnDelete();
            $table->boolean('is_elective')->default(false);
            $table->string('course_code');
            $table->string('course_title');
            $table->unsignedTinyInteger('credit_units');
            $table->string('grade', 1)->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->unique(['course_registration_id', 'course_offering_id'], 'course_registration_items_registration_offering_unique');
            $table->index('course_offering_id');
        });

        Schema::create('grade_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_registration_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('old_grade', 1);
            $table->string('new_grade', 1);
            $table->timestamp('created_at');

            $table->index(['course_registration_item_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_corrections');
        Schema::dropIfExists('course_registration_items');
        Schema::dropIfExists('course_registrations');
    }
};

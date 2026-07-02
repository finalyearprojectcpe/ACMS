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
        // Assigns a course advisor to a department student set, identified by the students' entry academic session.
        Schema::create('student_set_advisor_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entry_academic_session_id')->constrained('academic_sessions')->restrictOnDelete();
            $table->foreignId('staff_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by_staff_profile_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->foreignId('starts_academic_session_id')->nullable()->constrained('academic_sessions')->nullOnDelete();
            $table->foreignId('ends_academic_session_id')->nullable()->constrained('academic_sessions')->nullOnDelete();
            $table->unsignedTinyInteger('active_marker')->nullable();
            $table->timestamps();

            $table->unique(['department_id', 'entry_academic_session_id', 'active_marker'], 'student_set_advisor_active_unique');
            $table->index(['staff_profile_id', 'entry_academic_session_id'], 'student_set_advisor_staff_index');
            $table->index(['department_id', 'starts_academic_session_id', 'ends_academic_session_id'], 'student_set_advisor_session_bounds_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_set_advisor_assignments');
    }
};

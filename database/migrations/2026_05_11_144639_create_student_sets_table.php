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
        // Groups students by department and entry session under the course adviser who manages them.
        Schema::create('student_sets', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->nullable()->unique();
            $table->foreignId('advisor_profile_id')->constrained()->cascadeOnDelete();
            $table->string('department_name');
            $table->string('department_slug');
            $table->unsignedSmallInteger('entry_start_year');
            $table->unsignedSmallInteger('entry_end_year');
            $table->string('invite_token', 64)->unique();
            $table->string('logo_path')->nullable();
            $table->boolean('student_signup_enabled')->default(true);
            $table->foreignId('current_academic_year_id')->nullable()->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('current_semester_id')->nullable()->constrained('semesters')->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['advisor_profile_id', 'department_slug', 'entry_start_year', 'entry_end_year'],
                'student_sets_advisor_department_entry_session_unique',
            );
            $table->index(['advisor_profile_id', 'entry_start_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_sets');
    }
};

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
        // Stores the courses available to one student set in a specific session and year-semester.
        Schema::create('course_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_set_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_semester_id')->constrained()->restrictOnDelete();
            $table->string('course_title');
            $table->unsignedTinyInteger('credit_units');
            $table->string('category');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['student_set_id', 'course_id', 'academic_session_id', 'academic_year_semester_id'],
                'course_offerings_set_course_session_year_semester_unique'
            );
            $table->index(['student_set_id', 'academic_session_id', 'academic_year_semester_id'], 'course_offerings_set_context_index');
            $table->index(['category', 'archived_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_offerings');
    }
};

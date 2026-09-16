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
        // Groups students who entered together under the course adviser who manages their academic journey.
        Schema::create('student_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advisor_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entry_academic_session_id')->constrained('academic_sessions')->restrictOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('minimum_years_to_degree');
            $table->unsignedTinyInteger('maximum_course_units');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['advisor_profile_id', 'entry_academic_session_id']);
            $table->index(['advisor_profile_id', 'status']);
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

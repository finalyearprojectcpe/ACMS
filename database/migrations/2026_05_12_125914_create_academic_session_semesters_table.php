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
        // Records a semester within a set session, its course-load limit, and whether students may register.
        Schema::create('academic_session_semesters', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->nullable()->unique();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('maximum_course_units');
            $table->boolean('registration_enabled')->default(false);
            $table->timestamps();

            $table->unique(['academic_session_id', 'semester_id']);
            $table->index(['academic_session_id', 'registration_enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_session_semesters');
    }
};

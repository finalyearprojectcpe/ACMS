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
        // Makes a set-owned course available in one configured semester.
        Schema::create('course_offerings', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->nullable()->unique();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_semester_id')->constrained()->cascadeOnDelete();
            $table->string('course_title');
            $table->unsignedTinyInteger('credit_units');
            $table->boolean('is_elective')->default(false);
            $table->timestamp('results_published_at')->nullable();
            $table->foreignId('results_published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academic_session_semester_id', 'course_id']);
            $table->index(['academic_session_semester_id', 'is_elective']);
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

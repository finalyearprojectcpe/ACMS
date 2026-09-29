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
        // Records each academic session a course adviser creates for one student set and level.
        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->nullable()->unique();
            $table->foreignId('student_set_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('start_year');
            $table->unsignedSmallInteger('end_year');
            $table->timestamps();

            $table->unique(['student_set_id', 'academic_year_id']);
            $table->unique(['student_set_id', 'start_year', 'end_year']);
            $table->index(['student_set_id', 'start_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_sessions');
    }
};

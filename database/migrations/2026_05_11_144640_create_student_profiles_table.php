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
        // Stores academic identity for student users, including their current year of study.
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('current_academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('entry_academic_session_id')->nullable()->constrained('academic_sessions')->nullOnDelete();
            $table->string('registration_number');
            $table->timestamps();

            $table->index(['department_id', 'current_academic_year_id']);
            $table->index(['department_id', 'entry_academic_session_id']);
            $table->index(['department_id', 'registration_number']);
            $table->unique(['school_id', 'registration_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};

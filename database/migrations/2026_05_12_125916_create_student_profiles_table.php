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
        // Stores each student's adviser-owned academic identity and current year of study.
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('advisor_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_set_id')->constrained()->cascadeOnDelete();
            $table->foreignId('current_academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->string('registration_number');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['student_set_id', 'current_academic_year_id']);
            $table->unique(['advisor_profile_id', 'registration_number']);
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

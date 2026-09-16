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
        // Stores each course adviser's programme identity and the academic limits used across their student sets.
        Schema::create('advisor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('institution_name')->nullable();
            $table->string('programme_name');
            $table->string('programme_abbreviation', 20);
            $table->unsignedTinyInteger('minimum_years_to_degree')->default(4);
            $table->unsignedTinyInteger('maximum_course_units')->default(24);
            $table->string('timezone')->default('Africa/Lagos');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advisor_profiles');
    }
};

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
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('abbreviation', 10)->unique();
            $table->string('slug')->unique();
            $table->unsignedTinyInteger('minimum_years')->default(5);
            $table->foreignId('hod_staff_profile_id')->nullable()->index();
            $table->timestamps();

            $table->index(['faculty_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};

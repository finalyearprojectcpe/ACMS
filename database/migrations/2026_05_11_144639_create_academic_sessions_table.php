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
        // Stores the dated academic sessions managed by one course adviser.
        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advisor_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();

            $table->unique(['advisor_profile_id', 'name']);
            $table->unique(['advisor_profile_id', 'starts_on', 'ends_on'], 'academic_sessions_advisor_dates_unique');
            $table->index(['advisor_profile_id', 'starts_on', 'ends_on']);
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

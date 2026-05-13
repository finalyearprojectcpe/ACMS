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
        Schema::create('course_timetable_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_offering_id')->constrained()->cascadeOnDelete();
            $table->string('day_of_week', 16);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('venue');
            $table->timestamps();

            $table->index(['course_offering_id', 'day_of_week']);
            $table->index(['day_of_week', 'venue', 'starts_at', 'ends_at'], 'course_timetable_slots_venue_time_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_timetable_slots');
    }
};

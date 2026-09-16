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
        // Stores the reusable course catalog owned by a course adviser.
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advisor_profile_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('title');
            $table->string('slug');
            $table->unsignedTinyInteger('credit_units')->default(3);
            $table->timestamps();

            $table->unique(['advisor_profile_id', 'code']);
            $table->unique(['advisor_profile_id', 'slug']);
            $table->index(['advisor_profile_id', 'title']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};

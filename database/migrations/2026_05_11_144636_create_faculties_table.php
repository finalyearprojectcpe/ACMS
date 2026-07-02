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
        // Stores the faculties that belong to each school and their faculty-head workflow.
        Schema::create('faculties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('abbreviation', 10);
            $table->string('slug');
            $table->foreignId('head_staff_profile_id')->nullable()->index();
            $table->foreignId('pending_head_staff_profile_id')->nullable()->index();
            $table->timestamp('head_transfer_requested_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'name']);
            $table->unique(['school_id', 'abbreviation']);
            $table->unique(['school_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faculties');
    }
};

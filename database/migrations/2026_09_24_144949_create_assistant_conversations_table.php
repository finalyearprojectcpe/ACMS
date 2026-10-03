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
        // Keeps each adviser's private assistant history separate from other workspaces.
        Schema::create('assistant_conversations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->nullable()->unique();
            $table->foreignId('advisor_profile_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('student_profile_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->timestamps(6);
            $table->index(['advisor_profile_id', 'updated_at']);
            $table->index(['student_profile_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_conversations');
    }
};

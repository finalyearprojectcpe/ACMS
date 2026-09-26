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
        // Stores the course catalog that belongs exclusively to one student set.
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_set_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('title');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['student_set_id', 'code']);
            $table->unique(['student_set_id', 'slug']);
            $table->index(['student_set_id', 'title']);
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

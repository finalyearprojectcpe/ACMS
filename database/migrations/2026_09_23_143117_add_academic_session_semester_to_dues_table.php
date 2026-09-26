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
        Schema::table('dues', function (Blueprint $table): void {
            $table->foreignId('academic_session_semester_id')
                ->nullable()
                ->after('academic_session_id')
                ->constrained()
                ->restrictOnDelete();
            $table->index(['student_set_id', 'academic_session_semester_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dues', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('academic_session_semester_id');
        });
    }
};

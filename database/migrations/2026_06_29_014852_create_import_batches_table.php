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
        // Groups adviser spreadsheet uploads so mapped rows and import summaries remain auditable.
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advisor_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_set_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('import_key')->nullable();
            $table->string('status')->default('draft');
            $table->string('file_name')->nullable();
            $table->json('mappings')->nullable();
            $table->json('summary')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['advisor_profile_id', 'type', 'status']);
            $table->unique(['advisor_profile_id', 'uploaded_by_user_id', 'type', 'import_key'], 'import_batches_advisor_user_type_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};

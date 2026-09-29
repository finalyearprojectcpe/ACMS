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
        // Tracks transactional email attempts and provider delivery outcomes for adviser and student accounts.
        Schema::create('email_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advisor_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_email');
            $table->string('subject');
            $table->string('mailer')->default('resend');
            $table->string('purpose_key')->nullable()->unique();
            $table->string('status')->default('queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('provider_message_id')->nullable();
            $table->timestamp('provider_event_at')->nullable();
            $table->text('error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['advisor_profile_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['mailer', 'provider_message_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_deliveries');
    }
};

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
        // Stores Paystack subaccounts that receive student due payments for staff advisers.
        Schema::create('payment_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entry_academic_session_id')->nullable()->constrained('academic_sessions')->nullOnDelete();
            $table->string('provider')->default('paystack');
            $table->string('label');
            $table->string('business_name');
            $table->string('bank_code');
            $table->string('bank_name');
            $table->string('account_number');
            $table->string('account_number_last4', 4);
            $table->string('account_name');
            $table->string('paystack_subaccount_code')->nullable()->unique();
            $table->string('status')->default('draft');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamps();

            $table->index(['staff_profile_id', 'status']);
            $table->index(['department_id', 'entry_academic_session_id', 'status'], 'payment_accounts_department_set_status_index');
        });

        // Stores dues created by course advisers for a student set in a department.
        Schema::create('dues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entry_academic_session_id')->constrained('academic_sessions')->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_staff_profile_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->foreignId('payment_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('description')->nullable();
            $table->unsignedInteger('amount_kobo');
            $table->string('currency', 3)->default('NGN');
            $table->date('due_on')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'entry_academic_session_id', 'status']);
            $table->index(['school_id', 'academic_session_id']);
        });

        // Tracks whether each student in the set has paid, still owes, or was waived for a due.
        Schema::create('student_due_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('due_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount_kobo');
            $table->string('status')->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('latest_due_payment_id')->nullable();
            $table->timestamps();

            $table->unique(['due_id', 'student_profile_id']);
            $table->index(['student_profile_id', 'status']);
            $table->index('latest_due_payment_id');
        });

        // Records each Paystack checkout attempt for a student due assignment.
        Schema::create('due_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_due_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('provider')->default('paystack');
            $table->string('reference')->unique();
            $table->string('status')->default('initialized');
            $table->unsignedInteger('amount_kobo');
            $table->string('currency', 3)->default('NGN');
            $table->text('authorization_url')->nullable();
            $table->string('access_code')->nullable();
            $table->string('paystack_transaction_id')->nullable();
            $table->string('channel')->nullable();
            $table->unsignedInteger('fees_kobo')->nullable();
            $table->string('gateway_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('receipt_number')->nullable()->unique();
            $table->json('provider_payload')->nullable();
            $table->timestamps();

            $table->index(['student_profile_id', 'status']);
            $table->index(['school_id', 'created_at']);
        });

        // Stores Paystack webhook payloads so retries are processed once and can be audited.
        Schema::create('paystack_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event');
            $table->string('reference')->nullable();
            $table->string('signature')->nullable();
            $table->string('payload_hash')->unique();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['event', 'reference']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paystack_webhook_events');
        Schema::dropIfExists('due_payments');
        Schema::dropIfExists('student_due_assignments');
        Schema::dropIfExists('dues');
        Schema::dropIfExists('payment_accounts');
    }
};

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
        Schema::create('disbursement_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->nullable()->constrained()->cascadeOnDelete(); // Nullable for settlement disbursements
            $table->foreignId('user_id')->nullable()->constrained(); // Voucher issuer
            $table->string('voucher_code')->index();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('PHP');
            $table->string('mobile'); // Redeemer
            $table->string('bank_code')->nullable();
            $table->string('account_number')->nullable();
            $table->string('settlement_rail')->nullable(); // INSTAPAY/PESONET
            $table->string('gateway')->default('netbank'); // Gateway used
            $table->string('reference_id')->unique(); // For reconciliation
            $table->string('gateway_transaction_id')->nullable(); // From gateway response
            $table->string('status'); // pending, success, failed, timeout
            $table->string('error_type')->nullable(); // network_timeout, insufficient_funds, etc
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable(); // Full exception trace for debugging
            $table->json('request_payload')->nullable(); // What we sent to gateway
            $table->json('response_payload')->nullable(); // What gateway returned
            $table->timestamp('attempted_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes for reporting
            $table->index(['status', 'attempted_at']);
            $table->index(['gateway', 'status']);
            $table->index('error_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disbursement_attempts');
    }
};

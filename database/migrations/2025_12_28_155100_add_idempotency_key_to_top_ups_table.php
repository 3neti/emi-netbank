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
        Schema::table('top_ups', function (Blueprint $table) {
            $table->string('idempotency_key', 255)->nullable()->after('reference_no');
            $table->timestamp('idempotency_created_at')->nullable()->after('idempotency_key');
            $table->index('idempotency_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('top_ups', function (Blueprint $table) {
            $table->dropIndex(['idempotency_key']);
            $table->dropColumn(['idempotency_key', 'idempotency_created_at']);
        });
    }
};

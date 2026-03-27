<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('amount'); // stored in centavos
            $table->string('currency', 3)->default('PHP');
            $table->string('reference_type')->nullable(); // e.g., Voucher::class
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->json('meta')->nullable();
            $table->string('secret')->nullable();
            $table->timestamp('expires_on')->nullable();
            $table->timestamps();
            $table->index(['reference_type', 'reference_id'], 'cash_reference_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash');
    }
};

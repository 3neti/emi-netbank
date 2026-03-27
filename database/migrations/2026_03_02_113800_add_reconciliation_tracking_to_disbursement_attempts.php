<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disbursement_attempts', function (Blueprint $table) {
            $table->unsignedInteger('attempt_count')->default(0)->after('completed_at');
            $table->timestamp('last_checked_at')->nullable()->after('attempt_count');
        });
    }

    public function down(): void
    {
        Schema::table('disbursement_attempts', function (Blueprint $table) {
            $table->dropColumn(['attempt_count', 'last_checked_at']);
        });
    }
};

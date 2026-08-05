<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('disbursement_attempts')) {
            return;
        }

        Schema::table('disbursement_attempts', function (Blueprint $table): void {
            $table->string('mobile')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('disbursement_attempts')) {
            return;
        }

        if (DB::table('disbursement_attempts')->whereNull('mobile')->exists()) {
            throw new RuntimeException(
                'Cannot restore the disbursement attempt mobile requirement while null values exist.',
            );
        }

        Schema::table('disbursement_attempts', function (Blueprint $table): void {
            $table->string('mobile')->nullable(false)->change();
        });
    }
};

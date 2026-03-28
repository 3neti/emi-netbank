<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('disbursement_attempts')) {
            return;
        }

        Schema::table('disbursement_attempts', function (Blueprint $table) {
            if (Schema::hasColumn('disbursement_attempts', 'voucher_id')) {
                $table->renameColumn('voucher_id', 'subject_id');
            }
            if (Schema::hasColumn('disbursement_attempts', 'voucher_code')) {
                $table->renameColumn('voucher_code', 'subject_code');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('disbursement_attempts')) {
            return;
        }

        Schema::table('disbursement_attempts', function (Blueprint $table) {
            if (Schema::hasColumn('disbursement_attempts', 'subject_id')) {
                $table->renameColumn('subject_id', 'voucher_id');
            }
            if (Schema::hasColumn('disbursement_attempts', 'subject_code')) {
                $table->renameColumn('subject_code', 'voucher_code');
            }
        });
    }
};

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
            if (Schema::hasColumn('disbursement_attempts', 'subject_id')) {
                $table->renameColumn('subject_id', 'external_reference_id');
            }
            if (Schema::hasColumn('disbursement_attempts', 'subject_code')) {
                $table->renameColumn('subject_code', 'external_reference_code');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('disbursement_attempts')) {
            return;
        }

        Schema::table('disbursement_attempts', function (Blueprint $table) {
            if (Schema::hasColumn('disbursement_attempts', 'external_reference_id')) {
                $table->renameColumn('external_reference_id', 'subject_id');
            }
            if (Schema::hasColumn('disbursement_attempts', 'external_reference_code')) {
                $table->renameColumn('external_reference_code', 'subject_code');
            }
        });
    }
};

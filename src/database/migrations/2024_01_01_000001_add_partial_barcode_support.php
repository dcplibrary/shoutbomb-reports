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
        $tableName = config('shoutbomb-reports.storage.table_name', 'notice_failure_reports');

        Schema::table($tableName, function (Blueprint $table) {
            // Add field to track if barcode is partial (redacted)
            $table->boolean('barcode_partial')->default(false)->after('patron_barcode')
                ->comment('True if patron_barcode contains only last 4 digits (XXXXXXXXXX####)');

            // Add index for partial barcode fuzzy matching
            $table->index(['barcode_partial', 'patron_barcode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('shoutbomb-reports.storage.table_name', 'notice_failure_reports');

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropIndex(['barcode_partial', 'patron_barcode']);
            $table->dropColumn('barcode_partial');
        });
    }
};

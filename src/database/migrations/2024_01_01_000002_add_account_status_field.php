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
        $tableName = config('shoutbomb-failure-reports.storage.table_name', 'notice_failure_reports');

        Schema::table($tableName, function (Blueprint $table) {
            // Track account status for deleted/unavailable accounts
            $table->string('account_status', 20)->nullable()->after('failure_type')->index();
            // Values: 'active', 'deleted', 'unavailable'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('shoutbomb-failure-reports.storage.table_name', 'notice_failure_reports');

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn('account_status');
        });
    }
};

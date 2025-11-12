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
        $tableName = config('outlook-failure-reports.storage.table_name', 'notice_failure_reports');

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();

            // Outlook message identifiers
            $table->string('outlook_message_id')->unique()->index();
            $table->string('original_message_id')->nullable();

            // Email metadata
            $table->string('subject')->nullable();
            $table->string('from_address')->nullable();

            // Failure details
            $table->string('recipient_email')->nullable()->index();
            $table->string('patron_identifier')->nullable()->index();
            $table->string('notice_type')->nullable()->index(); // SMS, Voice, Email
            $table->text('failure_reason')->nullable();
            $table->string('error_code', 50)->nullable();

            // Timestamps
            $table->timestamp('received_at')->nullable()->index();
            $table->timestamp('processed_at')->nullable()->index();

            // Raw content (optional, for debugging)
            $table->longText('raw_content')->nullable();

            $table->timestamps();

            // Indexes for common queries
            $table->index(['notice_type', 'received_at']);
            $table->index(['error_code', 'received_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('outlook-failure-reports.storage.table_name', 'notice_failure_reports');
        Schema::dropIfExists($tableName);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;

/**
 * @deprecated This migration has been moved to dcplibrary/notices package.
 *             The barcode_partial field is now included in the base table migration.
 *             This file will be removed in v2.0.
 *
 *             To migrate:
 *             1. Install dcplibrary/notices package
 *             2. The barcode_partial field is included in the base table creation
 *             3. No additional migration needed
 */
return new class extends Migration
{
    public function up(): void
    {
        // Migration moved to dcplibrary/notices package
        // The barcode_partial field is now part of the base table schema
    }

    public function down(): void
    {
        // Migration moved to dcplibrary/notices package
    }
};
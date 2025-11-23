<?php

use Illuminate\Database\Migrations\Migration;

/**
 * @deprecated This migration has been moved to dcplibrary/notices package.
 *             The shoutbomb_monthly_stats table is now managed by the notices package.
 *             This file will be removed in v2.0.
 *
 *             To migrate:
 *             1. Install dcplibrary/notices package
 *             2. Run: php artisan migrate (notices package will create the table)
 *             3. Remove this package's migration from your migrations table if needed
 */
return new class extends Migration
{
    public function up(): void
    {
        // Migration moved to dcplibrary/notices package
        // See: dcplibrary/notices/src/Database/Migrations/2025_11_23_000002_create_shoutbomb_monthly_stats_table.php
    }

    public function down(): void
    {
        // Migration moved to dcplibrary/notices package
    }
};
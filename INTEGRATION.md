# Integration with Notices Package

This document explains how to integrate the `shoutbomb-reports` package with the `dcplibrary/notices` package for complete failure tracking.

## Database Schema Overview

### Notices Package Tables

**`notification_logs`** - Main table tracking all notifications
- `patron_id` - Polaris PatronID
- `patron_barcode` - Library card barcode
- `phone` - Normalized phone number (for SMS/Voice)
- `notification_type_id` - Type of notice (1=Overdue, 2=Hold, etc.)
- `delivery_option_id` - Delivery method (3=Voice, 8=SMS)
- `notification_status_id` - Status (12=Success, 14=Failed)
- `notification_date` - When notice was sent

**`shoutbomb_submissions`** - What was submitted to Shoutbomb
- `patron_barcode`
- `phone_number`
- `notification_type` - holds, overdue, renew
- `submitted_at`

**`shoutbomb_deliveries`** - Delivery status from Shoutbomb reports
- `patron_barcode`
- `phone_number`
- `delivery_type` - SMS, Voice
- `status` - Delivered, Failed, Pending, Invalid
- `failure_reason`
- `sent_date`

### Shoutbomb Reports Package Table

**`notice_failure_reports`** - Parsed from Shoutbomb email reports
- `patron_phone` - Phone number from failure email
- `patron_id` - Polaris patron ID
- `patron_barcode` - Library card barcode
- `notice_type` - SMS, Voice
- `failure_type` - opted-out, invalid, voice-not-delivered
- `failure_reason` - Human-readable reason
- `received_at` - When failure email was received

## Linking Strategy

### Method 1: Direct Link via Patron Identifiers

Match failures to notifications using:
```sql
SELECT nl.*, nfr.*
FROM notification_logs nl
JOIN notice_failure_reports nfr ON (
    nfr.patron_phone = nl.phone OR
    nfr.patron_id = nl.patron_id OR
    nfr.patron_barcode = nl.patron_barcode
)
WHERE nl.delivery_option_id IN (3, 8)  -- Voice=3, SMS=8
  AND nfr.received_at >= nl.notification_date
  AND nfr.received_at <= DATE_ADD(nl.notification_date, INTERVAL 7 DAY)
```

### Method 2: Link via Shoutbomb Submissions

```sql
SELECT ss.*, nfr.*
FROM shoutbomb_submissions ss
JOIN notice_failure_reports nfr ON (
    nfr.patron_phone = ss.phone_number OR
    nfr.patron_barcode = ss.patron_barcode
)
WHERE nfr.received_at >= ss.submitted_at
  AND nfr.received_at <= DATE_ADD(ss.submitted_at, INTERVAL 7 DAY)
```

### Method 3: Update Shoutbomb Deliveries

Populate `shoutbomb_deliveries` from failure reports:
```sql
INSERT INTO shoutbomb_deliveries (
    patron_barcode,
    phone_number,
    delivery_type,
    status,
    failure_reason,
    sent_date,
    report_type
)
SELECT
    patron_barcode,
    patron_phone,
    notice_type,
    'Failed',
    failure_reason,
    received_at,
    'Daily'
FROM notice_failure_reports
WHERE NOT EXISTS (
    SELECT 1 FROM shoutbomb_deliveries sd
    WHERE sd.patron_barcode = notice_failure_reports.patron_barcode
      AND sd.phone_number = notice_failure_reports.patron_phone
      AND DATE(sd.sent_date) = DATE(notice_failure_reports.received_at)
)
```

## Recommended Migration

Add a foreign key to link failures back to notifications:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notice_failure_reports', function (Blueprint $table) {
            // Add foreign key to notification_logs
            $table->foreignId('notification_log_id')
                ->nullable()
                ->after('id')
                ->constrained('notification_logs')
                ->nullOnDelete();

            // Add index for faster lookups
            $table->index('notification_log_id');
        });
    }

    public function down(): void
    {
        Schema::table('notice_failure_reports', function (Blueprint $table) {
            $table->dropForeign(['notification_log_id']);
            $table->dropColumn('notification_log_id');
        });
    }
};
```

## Artisan Command to Link Failures

Create `app/Console/Commands/LinkShoutbombFailures.php`:

```php
<?php

namespace App\Console\Commands;

use Dcplibrary\ShoutbombFailureReports\Models\NoticeFailureReport;
use App\Models\NotificationLog; // From notices package
use App\Models\ShoutbombDelivery; // From notices package
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LinkShoutbombFailures extends Command
{
    protected $signature = 'notices:link-shoutbomb-failures
                            {--days=7 : How many days back to check}
                            {--update-deliveries : Update shoutbomb_deliveries table}';

    protected $description = 'Link Shoutbomb failure reports to notification logs';

    public function handle()
    {
        $days = $this->option('days');
        $this->info("Linking failure reports from last {$days} days...");

        // Get unlinked failure reports
        $failures = NoticeFailureReport::whereNull('notification_log_id')
            ->where('received_at', '>=', now()->subDays($days))
            ->get();

        $this->info("Found {$failures->count()} unlinked failure reports");

        $linked = 0;
        $notFound = 0;
        $bar = $this->output->createProgressBar($failures->count());

        foreach ($failures as $failure) {
            // Try to find matching notification log
            $notification = NotificationLog::where(function ($query) use ($failure) {
                    $query->where('phone', $failure->patron_phone)
                          ->orWhere('patron_id', $failure->patron_id)
                          ->orWhere('patron_barcode', $failure->patron_barcode);
                })
                ->whereIn('delivery_option_id', [3, 8]) // Voice=3, SMS=8
                ->where('notification_date', '<=', $failure->received_at)
                ->where('notification_date', '>=', $failure->received_at->subDays(7))
                ->orderBy('notification_date', 'desc')
                ->first();

            if ($notification) {
                $failure->update(['notification_log_id' => $notification->id]);

                // Also update notification status to Failed
                $notification->update(['notification_status_id' => 14]); // 14=Failed

                $linked++;
            } else {
                $notFound++;
            }

            // Optionally update shoutbomb_deliveries
            if ($this->option('update-deliveries')) {
                ShoutbombDelivery::updateOrCreate(
                    [
                        'patron_barcode' => $failure->patron_barcode,
                        'phone_number' => $failure->patron_phone,
                        'sent_date' => $failure->received_at,
                    ],
                    [
                        'delivery_type' => $failure->notice_type,
                        'status' => 'Failed',
                        'failure_reason' => $failure->failure_reason,
                        'report_type' => 'Daily',
                    ]
                );
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Status', 'Count'],
            [
                ['Linked', $linked],
                ['Not Found', $notFound],
                ['Total', $failures->count()],
            ]
        );

        return self::SUCCESS;
    }
}
```

## Scheduled Integration

In `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Check for Shoutbomb reports hourly
    $schedule->command('shoutbomb:check-reports')
        ->hourly()
        ->withoutOverlapping();

    // Link failures to notifications (run 10 min after)
    $schedule->command('notices:link-shoutbomb-failures --update-deliveries')
        ->hourly()
        ->delay(10)
        ->withoutOverlapping();
}
```

## Model Relationships

### NotificationLog Model (Notices Package)

Add this relationship:

```php
public function failureReport()
{
    return $this->hasOne(NoticeFailureReport::class, 'notification_log_id');
}

public function hasFailed()
{
    return $this->failureReport !== null;
}
```

### NoticeFailureReport Model (Shoutbomb Reports Package)

Add this relationship:

```php
public function notificationLog()
{
    return $this->belongsTo(\App\Models\NotificationLog::class);
}
```

## Verification Queries

### Get All Failures for a Patron

```php
$failures = NoticeFailureReport::forPatron($patronBarcode)
    ->with('notificationLog')
    ->recent(30)
    ->get();
```

### Get Success Rate by Notice Type

```sql
SELECT
    nl.notification_type_id,
    COUNT(*) as total_sent,
    COUNT(nfr.id) as failed,
    ROUND((1 - COUNT(nfr.id) / COUNT(*)) * 100, 2) as success_rate
FROM notification_logs nl
LEFT JOIN notice_failure_reports nfr ON nfr.notification_log_id = nl.id
WHERE nl.delivery_option_id IN (3, 8)
  AND nl.notification_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY nl.notification_type_id
```

### Patrons with Recurring Failures

```sql
SELECT
    patron_phone,
    patron_barcode,
    COUNT(*) as failure_count,
    failure_type,
    MAX(received_at) as last_failure
FROM notice_failure_reports
WHERE received_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
GROUP BY patron_phone, patron_barcode, failure_type
HAVING COUNT(*) >= 3
ORDER BY failure_count DESC
```

## Dashboard Integration

Add to your notices dashboard:

```php
// Recent failures
$recentFailures = NoticeFailureReport::recent(7)
    ->with('notificationLog')
    ->limit(10)
    ->get();

// Failure statistics
$failureStats = [
    'opted_out' => NoticeFailureReport::optedOut()->recent(30)->count(),
    'invalid' => NoticeFailureReport::invalid()->recent(30)->count(),
    'voice_failed' => NoticeFailureReport::byFailureType('voice-not-delivered')->recent(30)->count(),
];
```

## Next Steps

1. Run the migration to add `notification_log_id` column
2. Create the `LinkShoutbombFailures` command
3. Add model relationships
4. Schedule automatic linking
5. Update your verification workflow to check for linked failures
6. Add failure metrics to your dashboard

This integration will give you complete visibility into which notices failed and why!

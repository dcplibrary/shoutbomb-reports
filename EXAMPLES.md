# Integration Examples

Examples of how to integrate this package with your notices system.

## Example 1: Link Failure Reports to Notices

Create a command to match failure reports with sent notices:

```php
<?php

namespace App\Console\Commands;

use Dcplibrary\ShoutbombFailureReports\Models\NoticeFailureReport;
use App\Models\Notice; // Your notice model
use Illuminate\Console\Command;

class LinkFailureReportsCommand extends Command
{
    protected $signature = 'notices:link-failure-reports';
    protected $description = 'Link failure reports to sent notices';

    public function handle()
    {
        $this->info('Linking failure reports to notices...');

        // Get unprocessed failure reports
        $failureReports = NoticeFailureReport::unprocessed()->get();

        $linked = 0;
        $notFound = 0;

        foreach ($failureReports as $report) {
            // Try to find matching notice by patron identifier (phone number)
            $notice = Notice::where('patron_phone', $report->patron_identifier)
                ->where('notice_type', $report->notice_type)
                ->where('sent_at', '<=', $report->received_at)
                ->whereNull('failure_report_id')
                ->orderBy('sent_at', 'desc')
                ->first();

            if ($notice) {
                // Link the failure report to the notice
                $notice->update([
                    'status' => 'failed',
                    'failure_report_id' => $report->id,
                    'failure_reason' => $report->failure_reason,
                    'failed_at' => $report->received_at,
                ]);

                $report->markAsProcessed();
                $linked++;

                $this->line("✓ Linked failure report to notice #{$notice->id}");
            } else {
                $notFound++;
                $this->warn("⚠ No matching notice found for {$report->patron_identifier}");
            }
        }

        $this->newLine();
        $this->info("Linking complete!");
        $this->table(
            ['Status', 'Count'],
            [
                ['Linked', $linked],
                ['Not Found', $notFound],
                ['Total', $failureReports->count()],
            ]
        );

        return self::SUCCESS;
    }
}
```

Schedule it to run after checking for failures:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('shoutbomb:check-failure-reports')->hourly();
    $schedule->command('notices:link-failure-reports')->hourly()->delay(5); // 5 min after
}
```

## Example 2: Query Failure Reports in Controllers

```php
<?php

namespace App\Http\Controllers;

use Dcplibrary\ShoutbombFailureReports\Models\NoticeFailureReport;
use Illuminate\Http\Request;

class FailureReportsController extends Controller
{
    /**
     * Display recent failure reports
     */
    public function index(Request $request)
    {
        $query = NoticeFailureReport::query()
            ->orderBy('received_at', 'desc');

        // Filter by notice type
        if ($request->has('type')) {
            $query->byNoticeType($request->type);
        }

        // Filter by date range
        if ($request->has('days')) {
            $query->recent($request->days);
        }

        $reports = $query->paginate(50);

        return view('failure-reports.index', compact('reports'));
    }

    /**
     * Get failure statistics
     */
    public function stats()
    {
        $stats = [
            'total' => NoticeFailureReport::count(),
            'by_type' => NoticeFailureReport::select('notice_type', \DB::raw('count(*) as count'))
                ->groupBy('notice_type')
                ->pluck('count', 'notice_type'),
            'by_error_code' => NoticeFailureReport::select('error_code', \DB::raw('count(*) as count'))
                ->whereNotNull('error_code')
                ->groupBy('error_code')
                ->pluck('count', 'error_code'),
            'recent_7days' => NoticeFailureReport::recent(7)->count(),
            'unprocessed' => NoticeFailureReport::unprocessed()->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Search for patron's failure reports
     */
    public function searchPatron(Request $request)
    {
        $patron = $request->input('patron_identifier');

        $reports = NoticeFailureReport::where('patron_identifier', $patron)
            ->orWhere('recipient_email', $patron)
            ->orderBy('received_at', 'desc')
            ->get();

        return response()->json($reports);
    }
}
```

## Example 3: Add Migration to Notices Table

Add a column to your notices table to link failure reports:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notices', function (Blueprint $table) {
            $table->foreignId('failure_report_id')
                ->nullable()
                ->after('status')
                ->constrained('notice_failure_reports')
                ->nullOnDelete();

            $table->text('failure_reason')->nullable()->after('failure_report_id');
            $table->timestamp('failed_at')->nullable()->after('failure_reason');

            $table->index('failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('notices', function (Blueprint $table) {
            $table->dropForeign(['failure_report_id']);
            $table->dropColumn(['failure_report_id', 'failure_reason', 'failed_at']);
        });
    }
};
```

## Example 4: Dashboard Widget

Create a widget for your dashboard showing recent failures:

```php
<?php

namespace App\View\Components;

use Dcplibrary\ShoutbombFailureReports\Models\NoticeFailureReport;
use Illuminate\View\Component;

class FailureReportsWidget extends Component
{
    public $recentFailures;
    public $stats;

    public function __construct()
    {
        $this->recentFailures = NoticeFailureReport::recent(7)
            ->orderBy('received_at', 'desc')
            ->limit(10)
            ->get();

        $this->stats = [
            'today' => NoticeFailureReport::whereDate('received_at', today())->count(),
            'week' => NoticeFailureReport::recent(7)->count(),
            'unprocessed' => NoticeFailureReport::unprocessed()->count(),
        ];
    }

    public function render()
    {
        return view('components.failure-reports-widget');
    }
}
```

```blade
<!-- resources/views/components/failure-reports-widget.blade.php -->
<div class="bg-white rounded-lg shadow p-6">
    <h3 class="text-lg font-semibold mb-4">Recent Delivery Failures</h3>

    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="text-center">
            <div class="text-2xl font-bold text-red-600">{{ $stats['today'] }}</div>
            <div class="text-sm text-gray-600">Today</div>
        </div>
        <div class="text-center">
            <div class="text-2xl font-bold text-orange-600">{{ $stats['week'] }}</div>
            <div class="text-sm text-gray-600">This Week</div>
        </div>
        <div class="text-center">
            <div class="text-2xl font-bold text-yellow-600">{{ $stats['unprocessed'] }}</div>
            <div class="text-sm text-gray-600">Unprocessed</div>
        </div>
    </div>

    <div class="space-y-2">
        @forelse($recentFailures as $failure)
            <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
                <div class="flex-1">
                    <div class="font-medium text-sm">{{ $failure->patron_identifier }}</div>
                    <div class="text-xs text-gray-500">{{ $failure->failure_reason }}</div>
                </div>
                <div class="text-right">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                        {{ $failure->notice_type }}
                    </span>
                    <div class="text-xs text-gray-500 mt-1">
                        {{ $failure->received_at->diffForHumans() }}
                    </div>
                </div>
            </div>
        @empty
            <p class="text-gray-500 text-sm text-center py-4">No recent failures</p>
        @endforelse
    </div>

    <div class="mt-4 text-center">
        <a href="{{ route('failure-reports.index') }}" class="text-sm text-blue-600 hover:text-blue-800">
            View All Reports →
        </a>
    </div>
</div>
```

Use it in your dashboard:

```blade
<!-- resources/views/dashboard.blade.php -->
<x-failure-reports-widget />
```

## Example 5: API Endpoint for External Monitoring

```php
<?php

// routes/api.php
use App\Http\Controllers\Api\FailureReportsApiController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/failure-reports', [FailureReportsApiController::class, 'index']);
    Route::get('/failure-reports/stats', [FailureReportsApiController::class, 'stats']);
    Route::get('/failure-reports/{id}', [FailureReportsApiController::class, 'show']);
});
```

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Dcplibrary\ShoutbombFailureReports\Models\NoticeFailureReport;
use Illuminate\Http\Request;

class FailureReportsApiController extends Controller
{
    public function index(Request $request)
    {
        $query = NoticeFailureReport::query();

        if ($request->has('type')) {
            $query->byNoticeType($request->type);
        }

        if ($request->has('days')) {
            $query->recent($request->days);
        }

        if ($request->has('unprocessed')) {
            $query->unprocessed();
        }

        return $query->orderBy('received_at', 'desc')
            ->paginate($request->get('per_page', 20));
    }

    public function stats()
    {
        return response()->json([
            'total_failures' => NoticeFailureReport::count(),
            'failures_by_type' => NoticeFailureReport::select('notice_type', \DB::raw('count(*) as count'))
                ->groupBy('notice_type')
                ->get()
                ->pluck('count', 'notice_type'),
            'failures_by_day' => NoticeFailureReport::where('received_at', '>=', now()->subDays(30))
                ->select(\DB::raw('DATE(received_at) as date'), \DB::raw('count(*) as count'))
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
            'top_error_codes' => NoticeFailureReport::select('error_code', \DB::raw('count(*) as count'))
                ->whereNotNull('error_code')
                ->groupBy('error_code')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
        ]);
    }

    public function show($id)
    {
        return NoticeFailureReport::findOrFail($id);
    }
}
```

## Example 6: Event-Driven Processing

Dispatch events when failure reports are processed:

```php
<?php

namespace App\Events;

use Dcplibrary\ShoutbombFailureReports\Models\NoticeFailureReport;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FailureReportProcessed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public NoticeFailureReport $failureReport
    ) {}
}
```

```php
<?php

namespace App\Listeners;

use App\Events\FailureReportProcessed;
use App\Models\Patron;
use App\Notifications\DeliveryFailureNotification;

class NotifyPatronOfFailure
{
    public function handle(FailureReportProcessed $event)
    {
        $report = $event->failureReport;

        // Find patron by identifier
        $patron = Patron::where('phone', $report->patron_identifier)
            ->orWhere('email', $report->recipient_email)
            ->first();

        if ($patron && $patron->notification_email) {
            // Notify patron via their alternative email
            $patron->notify(new DeliveryFailureNotification($report));
        }

        // Or update patron record to disable failed notification type
        if ($report->notice_type === 'SMS' && $report->error_code === '550') {
            $patron->update(['sms_enabled' => false]);
        }
    }
}
```

Register in `EventServiceProvider`:

```php
protected $listen = [
    \App\Events\FailureReportProcessed::class => [
        \App\Listeners\NotifyPatronOfFailure::class,
    ],
];
```

Dispatch event in your linking command:

```php
use App\Events\FailureReportProcessed;

// After linking
event(new FailureReportProcessed($report));
```

## Example 7: Custom Artisan Command for Reports

```php
<?php

namespace App\Console\Commands;

use Dcplibrary\ShoutbombFailureReports\Models\NoticeFailureReport;
use Illuminate\Console\Command;

class FailureReportSummaryCommand extends Command
{
    protected $signature = 'notices:failure-summary {--days=7}';
    protected $description = 'Display a summary of failure reports';

    public function handle()
    {
        $days = $this->option('days');
        $this->info("Failure Report Summary (Last {$days} days)");
        $this->newLine();

        $reports = NoticeFailureReport::recent($days)->get();

        if ($reports->isEmpty()) {
            $this->info('No failure reports found.');
            return self::SUCCESS;
        }

        // Total failures
        $this->line("Total Failures: {$reports->count()}");
        $this->newLine();

        // By notice type
        $byType = $reports->groupBy('notice_type')->map->count();
        $this->info('Failures by Type:');
        $this->table(
            ['Type', 'Count'],
            $byType->map(fn($count, $type) => [$type ?: 'Unknown', $count])
        );
        $this->newLine();

        // By error code
        $byErrorCode = $reports->whereNotNull('error_code')->groupBy('error_code')->map->count();
        if ($byErrorCode->isNotEmpty()) {
            $this->info('Failures by Error Code:');
            $this->table(
                ['Error Code', 'Count'],
                $byErrorCode->map(fn($count, $code) => [$code, $count])
            );
            $this->newLine();
        }

        // Top failure reasons
        $topReasons = $reports->whereNotNull('failure_reason')
            ->groupBy('failure_reason')
            ->map->count()
            ->sortDesc()
            ->take(5);

        if ($topReasons->isNotEmpty()) {
            $this->info('Top 5 Failure Reasons:');
            $this->table(
                ['Reason', 'Count'],
                $topReasons->map(fn($count, $reason) => [
                    \Str::limit($reason, 60),
                    $count
                ])
            );
        }

        return self::SUCCESS;
    }
}
```

## Summary

These examples show how to:

1. Link failure reports to notices automatically
2. Query and display failure data in controllers
3. Add database relationships between notices and failures
4. Create dashboard widgets
5. Expose API endpoints for external monitoring
6. Use event-driven processing
7. Create reporting commands

Customize these examples to fit your specific workflow!

<?php

namespace Dcplibrary\ShoutbombFailureReports\Commands;

use Dcplibrary\ShoutbombFailureReports\Models\NoticeFailureReport;
use Dcplibrary\ShoutbombFailureReports\Parsers\FailureReportParser;
use Dcplibrary\ShoutbombFailureReports\Services\GraphApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckFailureReportsCommand extends Command
{
    protected $signature = 'shoutbomb:check-failure-reports
                            {--dry-run : Display what would be processed without saving}
                            {--limit= : Maximum number of emails to process}
                            {--mark-read : Mark processed emails as read}';

    protected $description = 'Check Outlook for Shoutbomb failure reports and store them in the database';

    protected GraphApiService $graphApi;
    protected FailureReportParser $parser;

    public function __construct(GraphApiService $graphApi)
    {
        parent::__construct();
        $this->graphApi = $graphApi;
        $this->parser = new FailureReportParser();
    }

    public function handle(): int
    {
        $this->info('Starting Shoutbomb failure report check...');

        try {
            // Get filters from config
            $filters = config('shoutbomb-failure-reports.filters');

            // Override with command options
            if ($limit = $this->option('limit')) {
                $filters['max_emails'] = (int) $limit;
            }

            if ($this->option('mark-read')) {
                $filters['mark_as_read'] = true;
            }

            // Fetch messages from Outlook
            $this->info("Fetching messages from Outlook...");
            $messages = $this->graphApi->getMessages($filters);

            if (empty($messages)) {
                $this->info('No matching emails found.');
                return self::SUCCESS;
            }

            $this->info("Found " . count($messages) . " message(s) to process.");
            $this->newLine();

            $processedEmails = 0;
            $processedFailures = 0;
            $skippedCount = 0;

            foreach ($messages as $message) {
                try {
                    $result = $this->processMessage($message, $filters);

                    if ($result > 0) {
                        $processedEmails++;
                        $processedFailures += $result;
                        $this->line("✓ Processed: {$message['subject']} ({$result} failures)");
                    } else {
                        $skippedCount++;
                        $this->warn("✗ Skipped: {$message['subject']}");
                    }
                } catch (\Exception $e) {
                    $skippedCount++;
                    $this->error("Error processing message: {$e->getMessage()}");
                    Log::error('Failed to process failure report', [
                        'message_id' => $message['id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->newLine();
            $this->info("Processing complete!");
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Emails Processed', $processedEmails],
                    ['Individual Failures', $processedFailures],
                    ['Emails Skipped', $skippedCount],
                    ['Total Emails', count($messages)],
                ]
            );

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to check failure reports: {$e->getMessage()}");
            Log::error('Outlook failure report check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }

    /**
     * Process a single message (which may contain multiple failures)
     * Returns the number of failures processed
     */
    protected function processMessage(array $message, array $filters): int
    {
        // Get message body
        $bodyContent = $this->graphApi->getMessageBody($message, 'text');

        // Parse the message (returns array of failures)
        $failures = $this->parser->parse($message, $bodyContent);

        if (empty($failures)) {
            if (config('shoutbomb-failure-reports.storage.log_processing')) {
                Log::info('Skipped message - no failures parsed', [
                    'subject' => $message['subject'] ?? 'unknown',
                ]);
            }
            return 0;
        }

        // Check if this email has already been processed
        if ($this->isEmailProcessed($message['id'])) {
            if (config('shoutbomb-failure-reports.storage.log_processing')) {
                Log::info('Skipped message - email already processed', [
                    'message_id' => $message['id'],
                ]);
            }
            return 0;
        }

        // Dry run mode - just display what would be saved
        if ($this->option('dry-run')) {
            $this->displayParsedFailures($failures);
            return count($failures);
        }

        // Save all failures to database
        $saved = 0;
        DB::beginTransaction();
        try {
            foreach ($failures as $failure) {
                // Validate each failure
                if (!$this->parser->validate($failure)) {
                    continue;
                }

                // Check for duplicates
                if ($this->isFailureDuplicate($failure)) {
                    continue;
                }

                NoticeFailureReport::create($failure);
                $saved++;
            }

            // Mark as read if configured
            if ($filters['mark_as_read'] ?? false) {
                $this->graphApi->markAsRead($message['id']);
            }

            // Move to folder if configured
            if (!empty($filters['move_to_folder'])) {
                $this->graphApi->moveMessage($message['id'], $filters['move_to_folder']);
            }

            DB::commit();

            if (config('shoutbomb-failure-reports.storage.log_processing')) {
                Log::info('Processed Shoutbomb report', [
                    'email_subject' => $message['subject'] ?? 'unknown',
                    'failures_saved' => $saved,
                ]);
            }

            return $saved;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Check if email has already been processed
     */
    protected function isEmailProcessed(string $messageId): bool
    {
        return NoticeFailureReport::where('outlook_message_id', $messageId)->exists();
    }

    /**
     * Check if specific failure already exists
     */
    protected function isFailureDuplicate(array $failure): bool
    {
        return NoticeFailureReport::where('outlook_message_id', $failure['outlook_message_id'])
            ->where(function ($query) use ($failure) {
                $query->where('patron_phone', $failure['patron_phone'])
                    ->orWhere('patron_id', $failure['patron_id']);
            })
            ->exists();
    }

    /**
     * Display parsed failures in dry-run mode
     */
    protected function displayParsedFailures(array $failures): void
    {
        $this->newLine();
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("Parsed " . count($failures) . " Failure(s) (Dry Run):");
        $this->newLine();

        foreach ($failures as $index => $failure) {
            $this->line("Failure #" . ($index + 1));
            $this->table(
                ['Field', 'Value'],
                [
                    ['Subject', $failure['subject'] ?? 'N/A'],
                    ['Patron Phone', $failure['patron_phone'] ?? 'N/A'],
                    ['Patron ID', $failure['patron_id'] ?? 'N/A'],
                    ['Patron Barcode', $failure['patron_barcode'] ?? 'N/A'],
                    ['Patron Name', $failure['patron_name'] ?? 'N/A'],
                    ['Notice Type', $failure['notice_type'] ?? 'N/A'],
                    ['Failure Type', $failure['failure_type'] ?? 'N/A'],
                    ['Failure Reason', $failure['failure_reason'] ?? 'N/A'],
                    ['Notice Description', $failure['notice_description'] ?? 'N/A'],
                    ['Attempt Count', $failure['attempt_count'] ?? 'N/A'],
                    ['Received At', $failure['received_at'] ?? 'N/A'],
                ]
            );
            $this->newLine();
        }
    }
}

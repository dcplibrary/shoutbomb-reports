<?php

namespace Dcplibrary\OutlookFailureReports\Commands;

use Dcplibrary\OutlookFailureReports\Models\NoticeFailureReport;
use Dcplibrary\OutlookFailureReports\Parsers\FailureReportParser;
use Dcplibrary\OutlookFailureReports\Services\GraphApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckFailureReportsCommand extends Command
{
    protected $signature = 'outlook:check-failure-reports
                            {--dry-run : Display what would be processed without saving}
                            {--limit= : Maximum number of emails to process}
                            {--mark-read : Mark processed emails as read}';

    protected $description = 'Check Outlook for failure reports and store them in the database';

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
        $this->info('Starting Outlook failure report check...');

        try {
            // Get filters from config
            $filters = config('outlook-failure-reports.filters');

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

            $this->info("Found {$this->count($messages)} message(s) to process.");

            $processedCount = 0;
            $errorCount = 0;

            foreach ($messages as $message) {
                try {
                    $result = $this->processMessage($message, $filters);

                    if ($result) {
                        $processedCount++;
                        $this->line("✓ Processed: {$message['subject']}");
                    } else {
                        $errorCount++;
                        $this->warn("✗ Skipped: {$message['subject']}");
                    }
                } catch (\Exception $e) {
                    $errorCount++;
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
                ['Status', 'Count'],
                [
                    ['Processed', $processedCount],
                    ['Errors/Skipped', $errorCount],
                    ['Total', count($messages)],
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
     * Process a single message
     */
    protected function processMessage(array $message, array $filters): bool
    {
        // Get message body
        $bodyContent = $this->graphApi->getMessageBody($message, 'text');

        // Parse the message
        $parsedData = $this->parser->parse($message, $bodyContent);

        // Validate parsed data
        if (!$this->parser->validate($parsedData)) {
            if (config('outlook-failure-reports.storage.log_processing')) {
                Log::info('Skipped message - validation failed', [
                    'subject' => $message['subject'] ?? 'unknown',
                ]);
            }
            return false;
        }

        // Check if already processed (avoid duplicates)
        if ($this->isDuplicate($parsedData['outlook_message_id'])) {
            if (config('outlook-failure-reports.storage.log_processing')) {
                Log::info('Skipped message - already processed', [
                    'message_id' => $parsedData['outlook_message_id'],
                ]);
            }
            return false;
        }

        // Dry run mode - just display what would be saved
        if ($this->option('dry-run')) {
            $this->displayParsedData($parsedData);
            return true;
        }

        // Save to database
        DB::beginTransaction();
        try {
            NoticeFailureReport::create($parsedData);

            // Mark as read if configured
            if ($filters['mark_as_read'] ?? false) {
                $this->graphApi->markAsRead($message['id']);
            }

            // Move to folder if configured
            if (!empty($filters['move_to_folder'])) {
                $this->graphApi->moveMessage($message['id'], $filters['move_to_folder']);
            }

            DB::commit();

            if (config('outlook-failure-reports.storage.log_processing')) {
                Log::info('Processed failure report', [
                    'recipient' => $parsedData['recipient_email'],
                    'patron' => $parsedData['patron_identifier'],
                ]);
            }

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Check if message has already been processed
     */
    protected function isDuplicate(string $messageId): bool
    {
        return NoticeFailureReport::where('outlook_message_id', $messageId)->exists();
    }

    /**
     * Display parsed data in dry-run mode
     */
    protected function displayParsedData(array $data): void
    {
        $this->newLine();
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("Parsed Data (Dry Run):");
        $this->table(
            ['Field', 'Value'],
            [
                ['Subject', $data['subject']],
                ['Recipient Email', $data['recipient_email'] ?? 'N/A'],
                ['Patron ID', $data['patron_identifier'] ?? 'N/A'],
                ['Notice Type', $data['notice_type'] ?? 'N/A'],
                ['Failure Reason', $data['failure_reason'] ?? 'N/A'],
                ['Error Code', $data['error_code'] ?? 'N/A'],
                ['Received At', $data['received_at']],
            ]
        );
    }

    /**
     * Count helper for messages
     */
    protected function count($messages): int
    {
        return is_array($messages) || $messages instanceof \Countable
            ? count($messages)
            : 0;
    }
}

<?php

namespace Dcplibrary\ShoutbombFailureReports\Parsers;

use Illuminate\Support\Facades\Log;

class FailureReportParser
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config ?: config('shoutbomb-failure-reports.parsing', []);
    }

    /**
     * Parse a Shoutbomb failure report email and extract all failures
     * Returns an array of failure records (one email can have multiple failures)
     */
    public function parse(array $message, ?string $bodyContent = null): array
    {
        if (!$bodyContent) {
            $bodyContent = $message['body']['content'] ?? '';
        }

        // Strip HTML tags if content is HTML
        if (($message['body']['contentType'] ?? '') === 'html') {
            $bodyContent = strip_tags($bodyContent);
        }

        $failures = [];

        // Check if this is a Shoutbomb report
        if (!$this->isShoutbombReport($message, $bodyContent)) {
            Log::warning('Email does not appear to be a Shoutbomb report', [
                'subject' => $message['subject'] ?? 'unknown',
            ]);
            return [];
        }

        // Extract common metadata
        $metadata = [
            'outlook_message_id' => $message['id'] ?? null,
            'subject' => $message['subject'] ?? null,
            'received_at' => $message['receivedDateTime'] ?? null,
            'from_address' => $message['from']['emailAddress']['address'] ?? null,
            'raw_content' => config('shoutbomb-failure-reports.storage.store_raw_content', false)
                ? $bodyContent
                : null,
        ];

        // Detect report type from subject
        $subject = $message['subject'] ?? '';

        if (stripos($subject, 'Voice notices that were not delivered') !== false) {
            // Voice failure report format
            $failures = $this->parseVoiceFailures($bodyContent, $metadata);
        } elseif (stripos($subject, 'Shoutbomb Rpt') !== false) {
            // Monthly report format - parse multiple sections

            // Invalid/removed patron barcodes (redacted format)
            $invalidBarcodes = $this->parseInvalidBarcodesSection($bodyContent, $metadata);
            $failures = array_merge($failures, $invalidBarcodes);

            // Daily sections may also be present in monthly reports
            $optedOutFailures = $this->parseOptedOutSection($bodyContent, $metadata);
            $failures = array_merge($failures, $optedOutFailures);

            $invalidFailures = $this->parseInvalidSection($bodyContent, $metadata);
            $failures = array_merge($failures, $invalidFailures);
        } else {
            // Daily SMS failure report format
            // Parse opted-out patrons
            $optedOutFailures = $this->parseOptedOutSection($bodyContent, $metadata);
            $failures = array_merge($failures, $optedOutFailures);

            // Parse invalid phone numbers
            $invalidFailures = $this->parseInvalidSection($bodyContent, $metadata);
            $failures = array_merge($failures, $invalidFailures);
        }

        Log::info("Parsed Shoutbomb report: found " . count($failures) . " failures");

        return $failures;
    }

    /**
     * Check if this is a Shoutbomb report email
     */
    protected function isShoutbombReport(array $message, string $content): bool
    {
        $subject = $message['subject'] ?? '';
        $from = $message['from']['emailAddress']['address'] ?? '';

        // Check subject contains expected keywords
        if (stripos($subject, 'Invalid patron phone number') !== false ||
            stripos($subject, 'Voice notices that were not delivered') !== false ||
            stripos($subject, 'Shoutbomb Rpt') !== false) {
            return true;
        }

        // Check from Shoutbomb or DCPL Notifications
        if (stripos($from, 'shoutbomb') !== false ||
            stripos($from, 'DCPL Notifications') !== false) {
            return true;
        }

        // Check body contains expected sections
        if (stripos($content, 'opted-out from SMS or MMS') !== false ||
            stripos($content, 'seem to be invalid') !== false ||
            stripos($content, 'Daviess County Public Library') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Parse the "OPTED-OUT" section
     */
    protected function parseOptedOutSection(string $content, array $metadata): array
    {
        $failures = [];

        // Find the opted-out section
        if (preg_match('/OPTED-OUT from SMS or MMS.*?\n(.*?)(?=\n\s*Hello|\n\s*These patron|$)/is', $content, $sectionMatch)) {
            $section = $sectionMatch[1];
            $failures = $this->parseFailureLines($section, $metadata, 'opted-out');
        }

        return $failures;
    }

    /**
     * Parse the "INVALID" section
     */
    protected function parseInvalidSection(string $content, array $metadata): array
    {
        $failures = [];

        // Find the invalid section
        if (preg_match('/seem to be invalid.*?\n(.*?)(?=\n\s*Hello|\n\s*$)/is', $content, $sectionMatch)) {
            $section = $sectionMatch[1];
            $failures = $this->parseFailureLines($section, $metadata, 'invalid');
        }

        return $failures;
    }

    /**
     * Parse individual failure lines in format:
     * phone :: patron_id :: barcode :: attempt_count :: notice_type
     * Example: 2703143931 :: 23307015354998 :: 143090 :: 3 :: SMS
     */
    protected function parseFailureLines(string $section, array $metadata, string $failureType): array
    {
        $failures = [];
        $lines = explode("\n", $section);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            // Parse the line format: phone :: patron_id :: barcode :: attempts :: type
            $parts = array_map('trim', explode('::', $line));

            if (count($parts) >= 4) {
                $failure = array_merge($metadata, [
                    'patron_phone' => $parts[0] ?? null,
                    'patron_id' => $parts[1] ?? null,
                    'patron_barcode' => $parts[2] ?? null,
                    'attempt_count' => isset($parts[3]) ? (int)$parts[3] : null,
                    'notice_type' => $parts[4] ?? 'SMS',
                    'failure_reason' => $this->getFailureReason($failureType),
                    'failure_type' => $failureType,
                ]);

                $failures[] = $failure;
            } else {
                Log::debug("Could not parse Shoutbomb line: {$line}");
            }
        }

        return $failures;
    }

    /**
     * Parse Voice failure report (different format)
     * Format: phone | patron_id | library_name | patron_name | notice_description
     * Example: 8125739956 | 23307015354303| Daviess County Public Library| FLOREZ-ROBINSON, KATHERINE | Overdue item message
     */
    protected function parseVoiceFailures(string $content, array $metadata): array
    {
        $failures = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and header lines
            if (empty($line) || stripos($line, 'Hello') !== false || stripos($line, 'Date:') !== false) {
                continue;
            }

            // Check if line contains pipe-delimited data
            if (strpos($line, '|') === false) {
                continue;
            }

            // Parse the line format: phone | patron_id | library | patron_name | notice_type
            $parts = array_map('trim', explode('|', $line));

            if (count($parts) >= 4) {
                $failure = array_merge($metadata, [
                    'patron_phone' => $parts[0] ?? null,
                    'patron_id' => $parts[1] ?? null,
                    'patron_barcode' => null,
                    'patron_name' => $parts[3] ?? null,
                    'notice_description' => $parts[4] ?? null,
                    'attempt_count' => null,
                    'notice_type' => 'Voice',
                    'failure_reason' => 'Voice notice not delivered',
                    'failure_type' => 'voice-not-delivered',
                ]);

                $failures[] = $failure;
            }
        }

        return $failures;
    }

    /**
     * Parse invalid/removed patron barcodes section from monthly reports
     * Format: XXXXXXXXXX#### (last 4 digits visible)
     * Example: XXXXXXXXXX2144
     */
    protected function parseInvalidBarcodesSection(string $content, array $metadata): array
    {
        $failures = [];

        // Find the invalid barcodes section
        // Look for: "patron barcodes found to no longer be valid and have been removed"
        if (preg_match('/patron barcodes.*?no longer be valid.*?removed.*?\n(.*?)(?=\n\s*\.{20,}|\n\s*The following are patrons|$)/is', $content, $sectionMatch)) {
            $section = $sectionMatch[1];
            $lines = explode("\n", $section);

            foreach ($lines as $line) {
                $line = trim($line);

                // Skip empty lines and separators
                if (empty($line) || preg_match('/^[*.=-]+$/', $line)) {
                    continue;
                }

                // Match redacted barcode pattern: XXXXXXXXXX#### (10 X's + 4 digits)
                if (preg_match('/X{5,}(\d{4})/', $line, $matches)) {
                    $lastFourDigits = $matches[1];

                    $failure = array_merge($metadata, [
                        'patron_phone' => null,
                        'patron_id' => null,
                        'patron_barcode' => $lastFourDigits, // Store just the last 4 digits
                        'barcode_partial' => true, // Flag as partial for fuzzy matching
                        'patron_name' => null,
                        'attempt_count' => null,
                        'notice_type' => null,
                        'notice_description' => null,
                        'failure_reason' => 'Patron barcode removed from system - no longer valid',
                        'failure_type' => 'invalid-barcode-removed',
                    ]);

                    $failures[] = $failure;

                    Log::debug("Parsed invalid barcode (partial): {$lastFourDigits}");
                }
            }
        }

        return $failures;
    }

    /**
     * Get human-readable failure reason
     */
    protected function getFailureReason(string $failureType): string
    {
        return match($failureType) {
            'opted-out' => 'Patron opted-out from SMS/MMS messages',
            'invalid' => 'Invalid phone number',
            'voice-not-delivered' => 'Voice notice not delivered',
            'invalid-barcode-removed' => 'Patron barcode removed from system - no longer valid',
            default => 'Unknown failure',
        };
    }

    /**
     * Validate a single parsed failure
     */
    public function validate(array $parsedData): bool
    {
        // For partial barcodes, we only need the last 4 digits
        if (!empty($parsedData['barcode_partial']) && !empty($parsedData['patron_barcode'])) {
            return true;
        }

        // For regular failures, we need patron phone or patron ID
        if (empty($parsedData['patron_phone']) && empty($parsedData['patron_id'])) {
            Log::warning('Failed to parse critical data from failure line');
            return false;
        }

        return true;
    }
}

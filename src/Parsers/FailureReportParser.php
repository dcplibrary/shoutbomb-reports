<?php

namespace Dcplibrary\OutlookFailureReports\Parsers;

use Illuminate\Support\Facades\Log;

class FailureReportParser
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config ?: config('outlook-failure-reports.parsing', []);
    }

    /**
     * Parse a failure report email and extract relevant data
     */
    public function parse(array $message, ?string $bodyContent = null): ?array
    {
        if (!$bodyContent) {
            $bodyContent = $message['body']['content'] ?? '';
        }

        // Strip HTML tags if content is HTML
        if (($message['body']['contentType'] ?? '') === 'html') {
            $bodyContent = strip_tags($bodyContent);
        }

        $parsedData = [
            'outlook_message_id' => $message['id'] ?? null,
            'subject' => $message['subject'] ?? null,
            'received_at' => $message['receivedDateTime'] ?? null,
            'from_address' => $message['from']['emailAddress']['address'] ?? null,
            'recipient_email' => $this->extractRecipientEmail($bodyContent),
            'failure_reason' => $this->extractFailureReason($bodyContent),
            'original_message_id' => $this->extractOriginalMessageId($bodyContent),
            'raw_content' => config('outlook-failure-reports.storage.store_raw_content', false)
                ? $bodyContent
                : null,
        ];

        // Additional extraction for SMS/Voice notice failures
        $parsedData = array_merge($parsedData, $this->extractNoticeSpecificData($bodyContent, $message));

        return $parsedData;
    }

    /**
     * Extract recipient email from failure report
     */
    protected function extractRecipientEmail(string $content): ?string
    {
        $patterns = $this->config['recipient_patterns'] ?? [];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Extract failure reason from report
     */
    protected function extractFailureReason(string $content): ?string
    {
        $patterns = $this->config['reason_patterns'] ?? [];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                return trim($matches[1]);
            }
        }

        // Fallback: look for common error keywords
        $errorKeywords = [
            'mailbox unavailable',
            'user unknown',
            'does not exist',
            'mailbox full',
            'quota exceeded',
            'rejected',
            'blocked',
            'spam',
        ];

        foreach ($errorKeywords as $keyword) {
            if (stripos($content, $keyword) !== false) {
                // Extract the sentence containing the keyword
                $sentences = preg_split('/[.!?]\s+/', $content);
                foreach ($sentences as $sentence) {
                    if (stripos($sentence, $keyword) !== false) {
                        return trim($sentence);
                    }
                }
            }
        }

        return null;
    }

    /**
     * Extract original message ID
     */
    protected function extractOriginalMessageId(string $content): ?string
    {
        $patterns = $this->config['message_id_patterns'] ?? [];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Extract notice-specific data for SMS/Voice failures
     * Customize this based on your notice failure report format
     */
    protected function extractNoticeSpecificData(string $content, array $message): array
    {
        $data = [
            'notice_type' => null,
            'patron_identifier' => null,
            'error_code' => null,
        ];

        // Detect if this is an SMS or Voice notice failure
        if (preg_match('/\b(SMS|Voice|Text)\b/i', $content, $matches)) {
            $data['notice_type'] = strtoupper($matches[1]);
        }

        // Extract phone number if present (common in SMS/Voice failures)
        if (preg_match('/\b(\+?1?\s*\(?[0-9]{3}\)?[\s.-]?[0-9]{3}[\s.-]?[0-9]{4})\b/', $content, $matches)) {
            $data['patron_identifier'] = preg_replace('/\D/', '', $matches[1]);
        }

        // Extract error codes (e.g., SMTP codes like 550, 554, etc.)
        if (preg_match('/\b(5\d{2}|4\d{2})\b/', $content, $matches)) {
            $data['error_code'] = $matches[1];
        }

        // Try to find patron email or ID
        if (!$data['patron_identifier'] && preg_match('/\b(?:patron|user|member)\s*(?:ID|#)?:?\s*([A-Z0-9]+)\b/i', $content, $matches)) {
            $data['patron_identifier'] = $matches[1];
        }

        return $data;
    }

    /**
     * Validate parsed data
     */
    public function validate(array $parsedData): bool
    {
        // At minimum, we need recipient email or patron identifier
        if (empty($parsedData['recipient_email']) && empty($parsedData['patron_identifier'])) {
            Log::warning('Failed to parse critical data from failure report', [
                'subject' => $parsedData['subject'] ?? 'unknown',
            ]);
            return false;
        }

        return true;
    }

    /**
     * Extract all email addresses from content (helper method)
     */
    protected function extractAllEmails(string $content): array
    {
        preg_match_all('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', $content, $matches);
        return array_unique($matches[0]);
    }
}

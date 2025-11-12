<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Microsoft Graph API Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your Azure AD application credentials for accessing
    | Microsoft Graph API to read Outlook emails.
    |
    */

    'graph' => [
        // Azure AD Tenant ID
        'tenant_id' => env('OUTLOOK_TENANT_ID'),

        // Azure AD Application (client) ID
        'client_id' => env('OUTLOOK_CLIENT_ID'),

        // Azure AD Application client secret
        'client_secret' => env('OUTLOOK_CLIENT_SECRET'),

        // User email address to access
        'user_email' => env('OUTLOOK_USER_EMAIL'),

        // Graph API version
        'api_version' => env('OUTLOOK_API_VERSION', 'v1.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Filtering Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how to filter and identify failure report emails
    |
    */

    'filters' => [
        // Folder to monitor (null for inbox, or specify folder name)
        'folder' => env('OUTLOOK_FOLDER', null),

        // Subject line filters (partial match, case-insensitive)
        'subject_contains' => env('OUTLOOK_SUBJECT_FILTER', 'Undelivered'),

        // Sender email filters (array or comma-separated)
        'from_addresses' => env('OUTLOOK_FROM_FILTER', 'postmaster@,mailer-daemon@'),

        // Maximum number of emails to process per run
        'max_emails' => env('OUTLOOK_MAX_EMAILS', 50),

        // Only process unread emails
        'unread_only' => env('OUTLOOK_UNREAD_ONLY', true),

        // Mark emails as read after processing
        'mark_as_read' => env('OUTLOOK_MARK_AS_READ', true),

        // Move processed emails to folder (null to keep in place)
        'move_to_folder' => env('OUTLOOK_MOVE_TO_FOLDER', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Parsing Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how failure reports are parsed
    |
    */

    'parsing' => [
        // Patterns to extract recipient email from failure reports
        'recipient_patterns' => [
            '/(?:To|Recipient):\s*<?([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})>?/i',
            '/(?:Original recipient|Failed recipient):\s*<?([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})>?/i',
            '/<?([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})>?\s*(?:could not be delivered|delivery failed)/i',
        ],

        // Patterns to extract failure reason
        'reason_patterns' => [
            '/(?:Diagnostic code|Reason):\s*(.+?)(?:\n|$)/i',
            '/(?:Status|Error):\s*(\d{3}\s*.+?)(?:\n|$)/i',
            '/(?:Message from|Remote server said):\s*(.+?)(?:\n|$)/i',
        ],

        // Patterns to extract original message ID
        'message_id_patterns' => [
            '/Message-ID:\s*<?([^>\s]+)>?/i',
            '/Original message ID:\s*<?([^>\s]+)>?/i',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how failure data is stored
    |
    */

    'storage' => [
        // Database table name for storing failure reports
        'table_name' => env('OUTLOOK_FAILURE_TABLE', 'notice_failure_reports'),

        // Store raw email content for debugging
        'store_raw_content' => env('OUTLOOK_STORE_RAW', false),

        // Log processing to Laravel log
        'log_processing' => env('OUTLOOK_LOG_PROCESSING', true),
    ],
];

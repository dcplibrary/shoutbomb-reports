# Shoutbomb Reports Package

A Laravel package for reading and parsing Shoutbomb report emails from Microsoft 365 Outlook using the Microsoft Graph API. Designed specifically to integrate with the [Daviess County Public Library Notices Package](https://github.com/dcplibrary/notices) to track SMS and Voice notification delivery.

## Overview

This package automatically:
- Connects to your Microsoft 365 Outlook mailbox
- Reads Shoutbomb report emails (daily failures, monthly summaries, delivery statistics)
- Parses failure details (opted-out patrons, invalid phone numbers, undelivered notices)
- Stores the data in your database for verification and reporting
- Integrates with your existing notice verification workflow

## Why This Package?

Shoutbomb sends various report emails including failure reports and monthly summaries. This package:
- **Automates** the manual process of checking Shoutbomb emails
- **Extracts** critical information (patron ID, phone number, failure reason, delivery stats)
- **Stores** data in a structured format for analysis
- **Integrates** with your notice verification system

## Features

- ✅ **Microsoft Graph API Integration** - Secure, OAuth2-based authentication
- ✅ **Smart Parsing** - Extracts recipient, failure reason, error codes, and notice-specific data
- ✅ **Configurable Filtering** - Filter by subject, sender, folder, or read status
- ✅ **Duplicate Prevention** - Automatically skips already-processed emails
- ✅ **Dry Run Mode** - Test parsing without saving to database
- ✅ **Auto-organization** - Mark as read, move to folders after processing
- ✅ **Extensible** - Easy to customize parsing for different failure report formats

## Requirements

- PHP 8.1 or higher
- Laravel 10.x or 11.x
- Microsoft 365 account with Outlook
- Azure AD application (for Graph API access)

## Installation

### 1. Install via Composer

```bash
composer require dcplibrary/shoutbomb-reports
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --tag=shoutbomb-reports-config
```

This creates `config/shoutbomb-reports.php`

### 3. Publish Migrations

```bash
php artisan vendor:publish --tag=shoutbomb-reports-migrations
php artisan migrate
```

This creates the `notice_failure_reports` table.

## Azure AD Setup

### 1. Register Application in Azure

1. Go to [Azure Portal](https://portal.azure.com)
2. Navigate to **Azure Active Directory** → **App registrations**
3. Click **New registration**
4. Name: "Outlook Failure Reports"
5. Supported account types: "Accounts in this organizational directory only"
6. Click **Register**

### 2. Configure API Permissions

1. Go to **API permissions**
2. Click **Add a permission**
3. Select **Microsoft Graph** → **Application permissions**
4. Add these permissions:
   - `Mail.Read` (Read mail in all mailboxes)
   - `Mail.ReadWrite` (if you want to mark as read/move emails)
5. Click **Grant admin consent**

### 3. Create Client Secret

1. Go to **Certificates & secrets**
2. Click **New client secret**
3. Description: "Outlook Failure Reports"
4. Expiration: Choose appropriate duration
5. Click **Add**
6. **Copy the secret value immediately** (you won't see it again!)

### 4. Get IDs

From the **Overview** page, copy:
- **Application (client) ID**
- **Directory (tenant) ID**

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# Azure AD Configuration
SHOUTBOMB_TENANT_ID=your-tenant-id
SHOUTBOMB_CLIENT_ID=your-client-id
SHOUTBOMB_CLIENT_SECRET=your-client-secret

# User mailbox to monitor
SHOUTBOMB_USER_EMAIL=your-email@dcplibrary.org

# Email Filtering
SHOUTBOMB_FOLDER=null                    # null for inbox, or folder name
SHOUTBOMB_SUBJECT_FILTER=Undelivered     # Filter by subject
SHOUTBOMB_FROM_FILTER=postmaster@,mailer-daemon@
SHOUTBOMB_MAX_EMAILS=50
SHOUTBOMB_UNREAD_ONLY=true
SHOUTBOMB_MARK_AS_READ=true
SHOUTBOMB_MOVE_TO_FOLDER=null            # Move to folder after processing

# Storage
SHOUTBOMB_FAILURE_TABLE=notice_failure_reports
SHOUTBOMB_STORE_RAW=false                # Store raw email content (for debugging)
SHOUTBOMB_LOG_PROCESSING=true
```

### Config File

The published config file (`config/shoutbomb-reports.php`) contains:

- **Graph API settings** - Tenant, client credentials, API version
- **Filtering rules** - Subject, sender, folder filters
- **Parsing patterns** - Regex patterns for extracting data
- **Storage options** - Table name, logging preferences

You can customize parsing patterns for your specific failure report formats.

## Usage

### Basic Command

Check for new failure reports and process them:

```bash
php artisan shoutbomb:check-reports
```

### Command Options

```bash
# Dry run - see what would be processed without saving
php artisan shoutbomb:check-reports --dry-run

# Limit number of emails to process
php artisan shoutbomb:check-reports --limit=10

# Force mark as read (override config)
php artisan shoutbomb:check-reports --mark-read
```

### Scheduled Execution

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Check every 15 minutes during business hours
    $schedule->command('shoutbomb:check-reports')
        ->everyFifteenMinutes()
        ->weekdays()
        ->between('8:00', '18:00');

    // Or check every hour
    $schedule->command('shoutbomb:check-reports')
        ->hourly();
}
```

### Programmatic Usage

```php
use Dcplibrary\OutlookFailureReports\Services\GraphApiService;
use Dcplibrary\OutlookFailureReports\Parsers\FailureReportParser;

// Get messages
$graphApi = app(GraphApiService::class);
$messages = $graphApi->getMessages([
    'unread_only' => true,
    'max_emails' => 10,
]);

// Parse a message
$parser = new FailureReportParser();
$parsedData = $parser->parse($message);
```

## Integration with Notices Package

### Linking Failure Reports to Notices

The failure reports can be linked to your notices via:

1. **Recipient Email** - Match against patron email in notices
2. **Patron Identifier** - Extracted phone number or patron ID
3. **Original Message ID** - Link to original notice message ID

Example query:

```php
use Dcplibrary\OutlookFailureReports\Models\NoticeFailureReport;

// Get recent SMS failures
$smsFailures = NoticeFailureReport::byNoticeType('SMS')
    ->recent(7)
    ->get();

// Get failures for specific patron
$patronFailures = NoticeFailureReport::where('patron_identifier', $phoneNumber)
    ->orderBy('received_at', 'desc')
    ->get();

// Get unprocessed failures
$unprocessed = NoticeFailureReport::unprocessed()->get();
```

### Adding to Verification Workflow

You can create a custom verification step in your notices package:

```php
// In your notices verification logic
use Dcplibrary\OutlookFailureReports\Models\NoticeFailureReport;

public function verifyNoticeDelivery($notice)
{
    // Check if there's a failure report for this notice
    $failure = NoticeFailureReport::where('patron_identifier', $notice->patron_phone)
        ->where('received_at', '>=', $notice->sent_at)
        ->first();

    if ($failure) {
        $notice->status = 'failed';
        $notice->failure_reason = $failure->failure_reason;
        $notice->save();

        // Mark failure report as processed
        $failure->markAsProcessed();
    }
}
```

## Database Schema

The `notice_failure_reports` table contains:

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| outlook_message_id | string | Unique Outlook message ID |
| original_message_id | string | Original notice message ID |
| subject | string | Email subject |
| from_address | string | Sender email |
| recipient_email | string | Failed recipient email |
| patron_identifier | string | Phone number or patron ID |
| notice_type | string | SMS, Voice, or Email |
| failure_reason | text | Why delivery failed |
| error_code | string | SMTP/Error code (e.g., 550) |
| received_at | timestamp | When failure report received |
| processed_at | timestamp | When linked to notice |
| raw_content | text | Raw email content (optional) |
| created_at | timestamp | Record creation |
| updated_at | timestamp | Record update |

## Customizing Parsing

### Custom Patterns

Edit `config/shoutbomb-reports.php` to add custom regex patterns:

```php
'parsing' => [
    'recipient_patterns' => [
        '/Your custom pattern here/i',
        // ...
    ],
    'reason_patterns' => [
        '/Another custom pattern/i',
        // ...
    ],
],
```

### Extending the Parser

Create a custom parser:

```php
use Dcplibrary\OutlookFailureReports\Parsers\FailureReportParser;

class CustomFailureParser extends FailureReportParser
{
    protected function extractNoticeSpecificData(string $content, array $message): array
    {
        $data = parent::extractNoticeSpecificData($content, $message);

        // Add your custom extraction logic
        if (preg_match('/Library Card: (\d+)/', $content, $matches)) {
            $data['library_card'] = $matches[1];
        }

        return $data;
    }
}
```

## Troubleshooting

### Common Issues

**"Unauthorized" or "Access Denied"**
- Verify Azure AD app permissions are granted
- Ensure admin consent was granted
- Check tenant ID and client ID are correct

**"No emails found"**
- Check filter settings in config
- Verify user email is correct
- Test with `--dry-run` to see what would be processed

**"Failed to parse"**
- Enable raw content storage: `SHOUTBOMB_STORE_RAW=true`
- Check logs: `storage/logs/laravel.log`
- Adjust parsing patterns in config

**Token expired**
- Tokens are cached for 50 minutes
- Clear cache: `php artisan cache:clear`

### Debug Mode

Enable debug logging:

```env
SHOUTBOMB_LOG_PROCESSING=true
SHOUTBOMB_STORE_RAW=true
LOG_LEVEL=debug
```

Run with dry-run to see parsed data:

```bash
php artisan shoutbomb:check-reports --dry-run
```

## Security Considerations

- Store Azure credentials securely (use `.env`, never commit)
- Use application permissions (not delegated) for unattended operation
- Regularly rotate client secrets
- Monitor API usage in Azure portal
- Consider using Azure Key Vault for production secrets

## Testing

Create a test failure report email and send it to your monitored inbox, then run:

```bash
php artisan shoutbomb:check-reports --dry-run
```

Verify the parsing is correct before running without `--dry-run`.

## API Reference

### GraphApiService

```php
// Get access token
$token = $graphApi->getAccessToken();

// Get messages with filters
$messages = $graphApi->getMessages([
    'unread_only' => true,
    'subject_contains' => 'Undelivered',
    'max_emails' => 50,
]);

// Get single message
$message = $graphApi->getMessage($messageId);

// Mark as read
$graphApi->markAsRead($messageId);

// Move to folder
$graphApi->moveMessage($messageId, 'Processed');

// Get message body
$body = $graphApi->getMessageBody($message, 'text');
```

### FailureReportParser

```php
// Parse email
$parsedData = $parser->parse($message, $bodyContent);

// Validate parsed data
$isValid = $parser->validate($parsedData);
```

### NoticeFailureReport Model

```php
// Query scopes
NoticeFailureReport::unprocessed()->get();
NoticeFailureReport::byNoticeType('SMS')->get();
NoticeFailureReport::recent(7)->get();

// Mark as processed
$report->markAsProcessed();
```

## Roadmap

- [ ] Support for multiple mailboxes
- [ ] Webhook support (instead of polling)
- [ ] Integration with specific Shoutbomb failure report formats
- [ ] Dashboard for viewing failure reports
- [ ] Export failure reports to CSV
- [ ] Automatic patron notification blocking

## Contributing

Contributions are welcome! Please submit pull requests to the repository.

## License

MIT License

## Support

For issues specific to this package, please open an issue on GitHub.

For Azure AD / Graph API questions, refer to [Microsoft Graph documentation](https://docs.microsoft.com/en-us/graph/).

## Credits

Created by [Brian Lashbrook](mailto:blashbrook@dcplibrary.org) for Daviess County Public Library.

Integrates with [Daviess County Public Library Notices Package](https://github.com/dcplibrary/notices).

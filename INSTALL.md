# Installation Guide

Step-by-step guide to install and configure the Outlook Failure Reports package.

## Prerequisites

- Laravel 10.x or 11.x project
- PHP 8.1+
- Microsoft 365 account
- Azure AD admin access (to register app)

## Step 1: Install Package

```bash
composer require dcplibrary/shoutbomb-reports
```

## Step 2: Publish Assets

```bash
# Publish config file
php artisan vendor:publish --tag=shoutbomb-reports-config

# Publish and run migrations
php artisan vendor:publish --tag=shoutbomb-reports-migrations
php artisan migrate
```

## Step 3: Azure AD Application Setup

### A. Register Application

1. Go to [Azure Portal](https://portal.azure.com)
2. Navigate to: **Azure Active Directory** → **App registrations**
3. Click **+ New registration**
4. Fill in:
   - **Name**: `Outlook Failure Reports`
   - **Supported account types**: `Accounts in this organizational directory only (Single tenant)`
   - **Redirect URI**: Leave blank (we're using client credentials flow)
5. Click **Register**

### B. Configure API Permissions

1. In your app, go to **API permissions** (left sidebar)
2. Click **+ Add a permission**
3. Select **Microsoft Graph**
4. Choose **Application permissions** (NOT Delegated permissions)
5. Search for and add:
   - `Mail.Read` - Read mail in all mailboxes
   - `Mail.ReadWrite` - Read and write mail in all mailboxes (if you want to mark as read/move)
6. Click **Add permissions**
7. **IMPORTANT**: Click **Grant admin consent for [Your Organization]**
   - This requires admin privileges
   - The status must show green checkmarks

### C. Create Client Secret

1. Go to **Certificates & secrets** (left sidebar)
2. Click **+ New client secret**
3. Description: `Outlook Failure Reports Secret`
4. Expires: Choose based on your security policy (6 months, 12 months, etc.)
5. Click **Add**
6. **IMMEDIATELY COPY THE SECRET VALUE** - You cannot see it again!
   - Save it temporarily in a secure place

### D. Collect Application Details

From the **Overview** page, copy:

1. **Application (client) ID** - A GUID like `12345678-1234-1234-1234-123456789012`
2. **Directory (tenant) ID** - Another GUID

## Step 4: Configure Laravel

### A. Add to .env

Add these lines to your `.env` file:

```env
SHOUTBOMB_TENANT_ID=paste-your-tenant-id-here
SHOUTBOMB_CLIENT_ID=paste-your-client-id-here
SHOUTBOMB_CLIENT_SECRET=paste-your-client-secret-here
SHOUTBOMB_USER_EMAIL=your-monitored-mailbox@dcplibrary.org
```

### B. Configure Filters (Optional)

Customize how emails are filtered:

```env
# Only look at undelivered emails
SHOUTBOMB_SUBJECT_FILTER=Undelivered

# Filter by sender
SHOUTBOMB_FROM_FILTER=postmaster@,mailer-daemon@

# Process unread only
SHOUTBOMB_UNREAD_ONLY=true

# Mark as read after processing
SHOUTBOMB_MARK_AS_READ=true
```

## Step 5: Test the Setup

### Test with Dry Run

```bash
php artisan shoutbomb:check-reports --dry-run
```

This will:
- Connect to Outlook
- Fetch matching emails
- Parse them
- **Display the results WITHOUT saving to database**

### Expected Output

```
Starting Outlook failure report check...
Fetching messages from Outlook...
Found 3 message(s) to process.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Parsed Data (Dry Run):
+------------------+--------------------------------+
| Field            | Value                          |
+------------------+--------------------------------+
| Subject          | Undelivered Mail Returned...   |
| Recipient Email  | patron@example.com             |
| Patron ID        | 2025551234                     |
| Notice Type      | SMS                            |
| Failure Reason   | Invalid phone number           |
| Error Code       | 550                            |
+------------------+--------------------------------+

✓ Processed: Undelivered Mail...

Processing complete!
+----------------+-------+
| Status         | Count |
+----------------+-------+
| Processed      | 3     |
| Errors/Skipped | 0     |
| Total          | 3     |
+----------------+-------+
```

### Test with Actual Save

Once dry-run looks good:

```bash
php artisan shoutbomb:check-reports
```

Verify data in database:

```bash
php artisan tinker
>>> \Dcplibrary\OutlookFailureReports\Models\NoticeFailureReport::count()
=> 3
>>> \Dcplibrary\OutlookFailureReports\Models\NoticeFailureReport::first()
```

## Step 6: Schedule Automatic Checking

Edit `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Check for failure reports every 15 minutes during business hours
    $schedule->command('shoutbomb:check-reports')
        ->everyFifteenMinutes()
        ->weekdays()
        ->between('8:00', '18:00')
        ->withoutOverlapping();
}
```

Or for 24/7 monitoring:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('shoutbomb:check-reports')
        ->hourly()
        ->withoutOverlapping();
}
```

## Step 7: Organize Your Outlook (Optional)

### Create a Folder for Processed Emails

1. In Outlook, create a folder called "Processed Failure Reports"
2. Update `.env`:

```env
SHOUTBOMB_MOVE_TO_FOLDER=Processed Failure Reports
```

Now processed emails will automatically move to that folder.

### Create a Rule for Incoming Failures

In Outlook, create a rule to move failure reports to a specific folder:

1. Create folder: "Failure Reports"
2. Create rule: If subject contains "Undelivered" → Move to "Failure Reports"
3. Update `.env`:

```env
SHOUTBOMB_FOLDER=Failure Reports
```

## Troubleshooting

### "401 Unauthorized"

**Problem**: Azure AD authentication failed

**Solutions**:
- Verify `SHOUTBOMB_TENANT_ID` is correct
- Verify `SHOUTBOMB_CLIENT_ID` is correct
- Verify `SHOUTBOMB_CLIENT_SECRET` is correct and not expired
- Check that admin consent was granted

### "403 Forbidden"

**Problem**: App doesn't have permission to access the mailbox

**Solutions**:
- Ensure API permissions include `Mail.Read` or `Mail.ReadWrite`
- Ensure **Application permissions** were added (not Delegated)
- Ensure **Admin consent was granted** (green checkmarks in Azure)
- Wait 5-10 minutes after granting consent

### "No emails found"

**Problem**: Filters are too restrictive or no matching emails

**Solutions**:
- Check `SHOUTBOMB_USER_EMAIL` is correct
- Temporarily remove filters:
  ```env
  SHOUTBOMB_SUBJECT_FILTER=
  SHOUTBOMB_FROM_FILTER=
  SHOUTBOMB_UNREAD_ONLY=false
  ```
- Check the mailbox has actual emails

### "Failed to parse"

**Problem**: Email format doesn't match parsing patterns

**Solutions**:
- Enable raw content storage: `SHOUTBOMB_STORE_RAW=true`
- Run with dry-run and examine output
- Customize parsing patterns in `config/shoutbomb-reports.php`
- Check `storage/logs/laravel.log` for details

### Client Secret Expired

**Problem**: "invalid_client" error

**Solutions**:
- Go to Azure Portal → Your app → Certificates & secrets
- Delete old secret
- Create new secret
- Update `SHOUTBOMB_CLIENT_SECRET` in `.env`

## Next Steps

- Integrate with your [notices package](https://github.com/dcplibrary/notices)
- Customize parsing patterns for your specific failure report formats
- Set up monitoring/alerts for failed notices
- Create reports on failure trends

## Getting Help

- Check `storage/logs/laravel.log` for detailed errors
- Enable debug mode: `SHOUTBOMB_LOG_PROCESSING=true`
- Review [Microsoft Graph API docs](https://docs.microsoft.com/en-us/graph/)
- Open an issue on GitHub

## Security Reminder

- Never commit `.env` file to version control
- Rotate client secrets regularly (every 6-12 months)
- Use minimum required permissions
- Monitor API usage in Azure Portal

# Email Ingestion Service

A robust email ingestion service that receives emails via SMTP and stores them in a database. This service supports multiple database backends (MySQL, PostgreSQL, MSSQL) and provides comprehensive email parsing with attachment handling.

## Features

- **SMTP Server**: Built-in SMTP server to receive emails
- **Email Parsing**: Complete email parsing including headers, body (text/HTML), and attachments
- **Multiple Database Support**: MySQL, PostgreSQL, and Microsoft SQL Server
- **Error Handling**: Comprehensive error handling and validation
- **Logging**: Detailed logging system with file output
- **Attachment Support**: Base64 encoded attachment storage
- **Configurable**: Environment-based configuration
- **Authentication**: Optional SMTP authentication

## Architecture

The service consists of several key components:

1. **SMTP Server** (`src/server/smtpServer.js`): Receives incoming emails
2. **Email Processor** (`src/parsers/emailProcessor.js`): Parses and validates email data
3. **Database Manager** (`src/database/dbManager.js`): Handles database operations for multiple database types
4. **Logger** (`src/utils/logger.js`): Provides logging functionality
5. **Configuration** (`src/config/config.js`): Centralized configuration management

## Prerequisites

- Node.js (v14 or higher)
- One of the following databases:
  - MySQL 5.7+
  - PostgreSQL 10+
  - Microsoft SQL Server 2016+

## Installation

1. Clone the repository:
```bash
git clone <repository-url>
cd email-ingester
```

2. Install dependencies:
```bash
npm install
```

3. Set up your database:

Choose the appropriate schema file for your database:

**MySQL:**
```bash
mysql -u root -p < database/schema-mysql.sql
```

**PostgreSQL:**
```bash
psql -U postgres -f database/schema-postgresql.sql
```

**Microsoft SQL Server:**
```bash
sqlcmd -S localhost -U sa -i database/schema-mssql.sql
```

4. Configure the application:

Copy the example environment file and edit it:
```bash
cp .env.example .env
```

Edit `.env` with your configuration:
```env
# SMTP Configuration
SMTP_HOST=0.0.0.0
SMTP_PORT=2525

# Database Configuration
DB_TYPE=mysql
DB_HOST=localhost
DB_PORT=3306
DB_USER=root
DB_PASSWORD=your_password
DB_NAME=email_ingester
```

## Configuration Options

### SMTP Server Configuration

- `SMTP_HOST`: Host to bind the SMTP server (default: 0.0.0.0)
- `SMTP_PORT`: Port for the SMTP server (default: 2525)
- `SMTP_SECURE`: Enable TLS/SSL (default: false)
- `SMTP_REQUIRE_AUTH`: Require authentication (default: false)
- `SMTP_AUTH_OPTIONAL`: Make authentication optional (default: true)
- `SMTP_USERNAME`: Username for SMTP authentication
- `SMTP_PASSWORD`: Password for SMTP authentication

### Database Configuration

- `DB_TYPE`: Database type (mysql, postgresql, or mssql)
- `DB_HOST`: Database host
- `DB_PORT`: Database port
- `DB_USER`: Database user
- `DB_PASSWORD`: Database password
- `DB_NAME`: Database name
- `DB_ENCRYPT`: Enable encryption for MSSQL (default: true)
- `DB_TRUST_CERT`: Trust server certificate for MSSQL (default: true)

### Application Configuration

- `DEBUG`: Enable debug logging (default: false)
- `MAX_EMAIL_SIZE`: Maximum email size in bytes (default: 10485760 = 10MB)

## Usage

### Start the service:

```bash
npm start
```

For development with auto-restart:
```bash
npm run dev
```

### Send a test email:

You can test the service using any SMTP client or command-line tool:

**Using `swaks` (Swiss Army Knife SMTP):**
```bash
swaks --to recipient@example.com \
      --from sender@example.com \
      --server localhost:2525 \
      --header "Subject: Test Email" \
      --body "This is a test email"
```

**Using Python:**
```python
import smtplib
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart

msg = MIMEMultipart()
msg['From'] = 'sender@example.com'
msg['To'] = 'recipient@example.com'
msg['Subject'] = 'Test Email'
msg.attach(MIMEText('This is a test email', 'plain'))

server = smtplib.SMTP('localhost', 2525)
server.send_message(msg)
server.quit()
```

**Using Node.js (nodemailer):**
```javascript
const nodemailer = require('nodemailer');

const transporter = nodemailer.createTransport({
  host: 'localhost',
  port: 2525,
  secure: false
});

transporter.sendMail({
  from: 'sender@example.com',
  to: 'recipient@example.com',
  subject: 'Test Email',
  text: 'This is a test email'
});
```

## Database Schema

The service creates a single `emails` table with the following structure:

| Column | Type | Description |
|--------|------|-------------|
| id | INT/SERIAL | Primary key |
| message_id | VARCHAR | Email message ID |
| from_address | VARCHAR | Sender email address |
| to_address | VARCHAR | Recipient email address |
| cc_address | VARCHAR | CC addresses |
| bcc_address | VARCHAR | BCC addresses |
| subject | VARCHAR | Email subject |
| body_text | TEXT | Plain text body |
| body_html | TEXT | HTML body |
| received_date | DATETIME | Date email was received |
| attachments_count | INT | Number of attachments |
| attachments_data | TEXT | JSON array of attachment data |
| headers | JSON/TEXT | Email headers |
| priority | VARCHAR | Email priority |
| created_at | TIMESTAMP | Record creation timestamp |

## Logging

Logs are stored in the `logs/` directory:

- `info.log`: Informational messages
- `warn.log`: Warning messages
- `error.log`: Error messages
- `debug.log`: Debug messages (when DEBUG=true)
- `all.log`: All messages combined

## Error Handling

The service includes comprehensive error handling:

1. **Email Validation**: Validates required fields (from, to, date)
2. **Email Format Validation**: Basic email address format checking
3. **Database Errors**: Catches and logs database connection and insertion errors
4. **SMTP Errors**: Handles SMTP protocol errors
5. **Graceful Shutdown**: Properly closes connections on SIGINT/SIGTERM

## Security Considerations

1. **Authentication**: Enable SMTP authentication for production use
2. **TLS/SSL**: Enable secure mode for encrypted connections
3. **Network**: Bind to localhost (127.0.0.1) if not accepting external emails
4. **Database**: Use strong database credentials and restrict access
5. **Validation**: The service validates email data before insertion
6. **Size Limits**: Configure MAX_EMAIL_SIZE to prevent memory issues

## Troubleshooting

### Cannot connect to database
- Verify database credentials in `.env`
- Ensure database server is running
- Check database name exists
- Verify network connectivity

### SMTP server won't start
- Check if port is already in use: `netstat -an | grep <port>`
- Ensure you have permission to bind to the port (ports < 1024 require root)
- Check firewall settings

### Emails not being stored
- Check logs in `logs/error.log`
- Verify database connection is established
- Ensure database table exists
- Check email validation isn't failing

### Port permission denied
If you get "EACCES" error on Linux for ports < 1024, either:
- Use a port >= 1024 (e.g., 2525)
- Run with sudo (not recommended)
- Use authbind or setcap

## Production Deployment

For production deployment:

1. Use a process manager like PM2:
```bash
npm install -g pm2
pm2 start src/index.js --name email-ingester
pm2 save
pm2 startup
```

2. Enable TLS/SSL:
```env
SMTP_SECURE=true
```

3. Enable authentication:
```env
SMTP_REQUIRE_AUTH=true
SMTP_USERNAME=your_username
SMTP_PASSWORD=your_secure_password
```

4. Use a reverse proxy (nginx) for additional security
5. Set up log rotation for log files
6. Monitor using PM2 or similar tools
7. Set up database backups

## API Integration

To integrate with your application, query the `emails` table:

```sql
-- Get recent emails
SELECT * FROM emails ORDER BY received_date DESC LIMIT 10;

-- Search by sender
SELECT * FROM emails WHERE from_address LIKE '%example.com%';

-- Get emails with attachments
SELECT * FROM emails WHERE attachments_count > 0;
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

ISC

## Support

For issues and questions, please open an issue on the repository.

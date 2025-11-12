const EmailSMTPServer = require('./server/smtpServer');
const database = require('./database/dbManager');
const config = require('./config/config');
const logger = require('./utils/logger');

// Main application
class EmailIngester {
  constructor() {
    this.smtpServer = null;
  }

  async start() {
    try {
      logger.info('Starting Email Ingestion Service...');

      // Initialize database connection
      logger.info('Connecting to database...');
      await database.initialize(config.database);

      // Start SMTP server
      logger.info('Starting SMTP server...');
      this.smtpServer = new EmailSMTPServer(config.smtp);
      this.smtpServer.start();

      logger.info('Email Ingestion Service started successfully!');
      logger.info(`Listening for emails on ${config.smtp.host}:${config.smtp.port}`);
    } catch (error) {
      logger.error('Failed to start Email Ingestion Service:', error);
      process.exit(1);
    }
  }

  async stop() {
    logger.info('Stopping Email Ingestion Service...');

    if (this.smtpServer) {
      this.smtpServer.stop();
    }

    await database.close();

    logger.info('Email Ingestion Service stopped');
  }
}

// Create and start the application
const app = new EmailIngester();

// Handle graceful shutdown
process.on('SIGINT', async () => {
  logger.info('Received SIGINT signal');
  await app.stop();
  process.exit(0);
});

process.on('SIGTERM', async () => {
  logger.info('Received SIGTERM signal');
  await app.stop();
  process.exit(0);
});

// Handle uncaught exceptions
process.on('uncaughtException', (error) => {
  logger.error('Uncaught Exception:', error);
  process.exit(1);
});

process.on('unhandledRejection', (reason, promise) => {
  logger.error('Unhandled Rejection at:', promise, 'reason:', reason);
  process.exit(1);
});

// Start the application
app.start();

module.exports = app;

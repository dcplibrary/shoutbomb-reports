const { SMTPServer } = require('smtp-server');
const { simpleParser } = require('mailparser');
const logger = require('../utils/logger');
const emailProcessor = require('../parsers/emailProcessor');

class EmailSMTPServer {
  constructor(config) {
    this.config = config;
    this.server = null;
  }

  start() {
    this.server = new SMTPServer({
      // Server configuration
      secure: this.config.secure || false,
      authOptional: this.config.authOptional !== false,

      // Authentication handler (if needed)
      onAuth: (auth, session, callback) => {
        if (this.config.requireAuth) {
          if (auth.username === this.config.username && auth.password === this.config.password) {
            callback(null, { user: auth.username });
          } else {
            callback(new Error('Invalid username or password'));
          }
        } else {
          callback(null, { user: 'anonymous' });
        }
      },

      // Data handler - processes incoming emails
      onData: (stream, session, callback) => {
        logger.info('Receiving email...');

        let emailData = '';
        stream.on('data', (chunk) => {
          emailData += chunk;
        });

        stream.on('end', async () => {
          try {
            // Parse the email
            const parsed = await simpleParser(emailData);
            logger.info(`Email received from: ${parsed.from?.text || 'Unknown'}, Subject: ${parsed.subject || 'No subject'}`);

            // Process the email and store in database
            await emailProcessor.process(parsed);

            callback(null, 'Message accepted');
          } catch (error) {
            logger.error('Error processing email:', error);
            callback(error);
          }
        });

        stream.on('error', (error) => {
          logger.error('Stream error:', error);
          callback(error);
        });
      },

      // Error handler
      onError: (error) => {
        logger.error('SMTP Server error:', error);
      }
    });

    // Start listening
    this.server.listen(this.config.port, this.config.host, () => {
      logger.info(`SMTP Server running on ${this.config.host}:${this.config.port}`);
      if (!this.config.secure) {
        logger.warn('Server is running in non-secure mode (no TLS)');
      }
    });

    // Handle server errors
    this.server.on('error', (error) => {
      logger.error('Server error:', error);
    });
  }

  stop() {
    if (this.server) {
      this.server.close(() => {
        logger.info('SMTP Server stopped');
      });
    }
  }
}

module.exports = EmailSMTPServer;

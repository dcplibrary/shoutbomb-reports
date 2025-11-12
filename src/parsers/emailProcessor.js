const logger = require('../utils/logger');
const database = require('../database/dbManager');

class EmailProcessor {
  /**
   * Process a parsed email and store it in the database
   * @param {Object} parsedEmail - Parsed email object from mailparser
   */
  async process(parsedEmail) {
    try {
      // Extract email data
      const emailData = this.extractEmailData(parsedEmail);

      // Validate the data
      const validationResult = this.validateEmailData(emailData);
      if (!validationResult.valid) {
        logger.error('Email validation failed:', validationResult.errors);
        throw new Error(`Validation failed: ${validationResult.errors.join(', ')}`);
      }

      // Store in database
      await database.insertEmail(emailData);

      logger.info(`Email processed successfully: ${emailData.subject}`);
      return { success: true, emailData };
    } catch (error) {
      logger.error('Error processing email:', error);
      throw error;
    }
  }

  /**
   * Extract relevant data from parsed email
   * @param {Object} parsedEmail - Parsed email object
   * @returns {Object} Extracted email data
   */
  extractEmailData(parsedEmail) {
    const data = {
      messageId: parsedEmail.messageId || null,
      from: parsedEmail.from?.text || parsedEmail.from?.value?.[0]?.address || 'unknown',
      to: parsedEmail.to?.text || parsedEmail.to?.value?.[0]?.address || 'unknown',
      cc: parsedEmail.cc?.text || null,
      bcc: parsedEmail.bcc?.text || null,
      subject: parsedEmail.subject || 'No subject',
      bodyText: parsedEmail.text || '',
      bodyHtml: parsedEmail.html || null,
      receivedDate: parsedEmail.date || new Date(),
      attachments: [],
      headers: this.extractHeaders(parsedEmail.headers),
      priority: parsedEmail.priority || 'normal'
    };

    // Process attachments if any
    if (parsedEmail.attachments && parsedEmail.attachments.length > 0) {
      data.attachments = parsedEmail.attachments.map(attachment => ({
        filename: attachment.filename,
        contentType: attachment.contentType,
        size: attachment.size,
        content: attachment.content.toString('base64') // Store as base64
      }));
    }

    return data;
  }

  /**
   * Extract important headers from email
   * @param {Map} headers - Email headers map
   * @returns {Object} Important headers as object
   */
  extractHeaders(headers) {
    const importantHeaders = {};

    if (headers) {
      const headerKeys = ['x-mailer', 'x-originating-ip', 'received', 'return-path', 'reply-to'];
      headerKeys.forEach(key => {
        const value = headers.get(key);
        if (value) {
          importantHeaders[key] = value;
        }
      });
    }

    return importantHeaders;
  }

  /**
   * Validate email data before storing
   * @param {Object} emailData - Email data to validate
   * @returns {Object} Validation result
   */
  validateEmailData(emailData) {
    const errors = [];

    // Basic validation rules
    if (!emailData.from || emailData.from === 'unknown') {
      errors.push('From address is required');
    }

    if (!emailData.to || emailData.to === 'unknown') {
      errors.push('To address is required');
    }

    if (!emailData.receivedDate) {
      errors.push('Received date is required');
    }

    // Email address format validation (basic)
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const fromEmail = this.extractEmailAddress(emailData.from);
    if (fromEmail && !emailRegex.test(fromEmail)) {
      errors.push('Invalid from email format');
    }

    return {
      valid: errors.length === 0,
      errors
    };
  }

  /**
   * Extract email address from a string that may contain name and email
   * @param {String} emailString - Email string
   * @returns {String} Email address
   */
  extractEmailAddress(emailString) {
    const match = emailString.match(/([^\s@]+@[^\s@]+\.[^\s@]+)/);
    return match ? match[1] : emailString;
  }
}

module.exports = new EmailProcessor();

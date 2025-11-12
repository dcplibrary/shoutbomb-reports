const logger = require('../utils/logger');

class DatabaseManager {
  constructor() {
    this.db = null;
    this.dbType = null;
  }

  /**
   * Initialize database connection based on type
   * @param {Object} config - Database configuration
   */
  async initialize(config) {
    this.dbType = config.type.toLowerCase();

    try {
      switch (this.dbType) {
        case 'mysql':
          await this.initMySQL(config);
          break;
        case 'postgresql':
        case 'postgres':
          await this.initPostgreSQL(config);
          break;
        case 'mssql':
        case 'sqlserver':
          await this.initMSSQL(config);
          break;
        default:
          throw new Error(`Unsupported database type: ${config.type}`);
      }

      logger.info(`Database connection established: ${this.dbType}`);
    } catch (error) {
      logger.error('Database initialization failed:', error);
      throw error;
    }
  }

  /**
   * Initialize MySQL connection
   */
  async initMySQL(config) {
    const mysql = require('mysql2/promise');

    this.db = await mysql.createPool({
      host: config.host,
      port: config.port || 3306,
      user: config.user,
      password: config.password,
      database: config.database,
      waitForConnections: true,
      connectionLimit: 10,
      queueLimit: 0
    });

    // Test connection
    await this.db.query('SELECT 1');
  }

  /**
   * Initialize PostgreSQL connection
   */
  async initPostgreSQL(config) {
    const { Pool } = require('pg');

    this.db = new Pool({
      host: config.host,
      port: config.port || 5432,
      user: config.user,
      password: config.password,
      database: config.database,
      max: 10
    });

    // Test connection
    const client = await this.db.connect();
    await client.query('SELECT 1');
    client.release();
  }

  /**
   * Initialize MSSQL connection
   */
  async initMSSQL(config) {
    const sql = require('mssql');

    this.db = await sql.connect({
      server: config.host,
      port: config.port || 1433,
      user: config.user,
      password: config.password,
      database: config.database,
      options: {
        encrypt: config.encrypt !== false,
        trustServerCertificate: config.trustServerCertificate !== false
      }
    });
  }

  /**
   * Insert email data into database
   * @param {Object} emailData - Email data to insert
   */
  async insertEmail(emailData) {
    try {
      switch (this.dbType) {
        case 'mysql':
          return await this.insertEmailMySQL(emailData);
        case 'postgresql':
        case 'postgres':
          return await this.insertEmailPostgreSQL(emailData);
        case 'mssql':
        case 'sqlserver':
          return await this.insertEmailMSSQL(emailData);
        default:
          throw new Error(`Database type not initialized`);
      }
    } catch (error) {
      logger.error('Error inserting email into database:', error);
      throw error;
    }
  }

  /**
   * Insert email into MySQL database
   */
  async insertEmailMySQL(emailData) {
    const query = `
      INSERT INTO emails (
        message_id, from_address, to_address, cc_address, bcc_address,
        subject, body_text, body_html, received_date, attachments_count,
        attachments_data, headers, priority, created_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    `;

    const values = [
      emailData.messageId,
      emailData.from,
      emailData.to,
      emailData.cc,
      emailData.bcc,
      emailData.subject,
      emailData.bodyText,
      emailData.bodyHtml,
      emailData.receivedDate,
      emailData.attachments.length,
      JSON.stringify(emailData.attachments),
      JSON.stringify(emailData.headers),
      emailData.priority
    ];

    const [result] = await this.db.query(query, values);
    return result.insertId;
  }

  /**
   * Insert email into PostgreSQL database
   */
  async insertEmailPostgreSQL(emailData) {
    const query = `
      INSERT INTO emails (
        message_id, from_address, to_address, cc_address, bcc_address,
        subject, body_text, body_html, received_date, attachments_count,
        attachments_data, headers, priority, created_at
      ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, NOW())
      RETURNING id
    `;

    const values = [
      emailData.messageId,
      emailData.from,
      emailData.to,
      emailData.cc,
      emailData.bcc,
      emailData.subject,
      emailData.bodyText,
      emailData.bodyHtml,
      emailData.receivedDate,
      emailData.attachments.length,
      JSON.stringify(emailData.attachments),
      JSON.stringify(emailData.headers),
      emailData.priority
    ];

    const result = await this.db.query(query, values);
    return result.rows[0].id;
  }

  /**
   * Insert email into MSSQL database
   */
  async insertEmailMSSQL(emailData) {
    const sql = require('mssql');

    const request = this.db.request();
    request.input('message_id', sql.NVarChar, emailData.messageId);
    request.input('from_address', sql.NVarChar, emailData.from);
    request.input('to_address', sql.NVarChar, emailData.to);
    request.input('cc_address', sql.NVarChar, emailData.cc);
    request.input('bcc_address', sql.NVarChar, emailData.bcc);
    request.input('subject', sql.NVarChar, emailData.subject);
    request.input('body_text', sql.NVarChar(sql.MAX), emailData.bodyText);
    request.input('body_html', sql.NVarChar(sql.MAX), emailData.bodyHtml);
    request.input('received_date', sql.DateTime, emailData.receivedDate);
    request.input('attachments_count', sql.Int, emailData.attachments.length);
    request.input('attachments_data', sql.NVarChar(sql.MAX), JSON.stringify(emailData.attachments));
    request.input('headers', sql.NVarChar(sql.MAX), JSON.stringify(emailData.headers));
    request.input('priority', sql.NVarChar, emailData.priority);

    const query = `
      INSERT INTO emails (
        message_id, from_address, to_address, cc_address, bcc_address,
        subject, body_text, body_html, received_date, attachments_count,
        attachments_data, headers, priority, created_at
      ) VALUES (
        @message_id, @from_address, @to_address, @cc_address, @bcc_address,
        @subject, @body_text, @body_html, @received_date, @attachments_count,
        @attachments_data, @headers, @priority, GETDATE()
      );
      SELECT SCOPE_IDENTITY() AS id;
    `;

    const result = await request.query(query);
    return result.recordset[0].id;
  }

  /**
   * Close database connection
   */
  async close() {
    if (this.db) {
      switch (this.dbType) {
        case 'mysql':
          await this.db.end();
          break;
        case 'postgresql':
        case 'postgres':
          await this.db.end();
          break;
        case 'mssql':
        case 'sqlserver':
          await this.db.close();
          break;
      }
      logger.info('Database connection closed');
    }
  }
}

module.exports = new DatabaseManager();

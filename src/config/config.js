require('dotenv').config();

const config = {
  smtp: {
    host: process.env.SMTP_HOST || '0.0.0.0',
    port: parseInt(process.env.SMTP_PORT) || 2525,
    secure: process.env.SMTP_SECURE === 'true',
    requireAuth: process.env.SMTP_REQUIRE_AUTH === 'true',
    authOptional: process.env.SMTP_AUTH_OPTIONAL !== 'false',
    username: process.env.SMTP_USERNAME || '',
    password: process.env.SMTP_PASSWORD || ''
  },

  database: {
    type: process.env.DB_TYPE || 'mysql', // mysql, postgresql, mssql
    host: process.env.DB_HOST || 'localhost',
    port: parseInt(process.env.DB_PORT) || 3306,
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'email_ingester',
    // MSSQL specific options
    encrypt: process.env.DB_ENCRYPT === 'true',
    trustServerCertificate: process.env.DB_TRUST_CERT !== 'false'
  },

  app: {
    debug: process.env.DEBUG === 'true',
    maxEmailSize: parseInt(process.env.MAX_EMAIL_SIZE) || 10485760 // 10MB default
  }
};

// Validation
function validateConfig() {
  const errors = [];

  if (!config.database.type) {
    errors.push('Database type is required (DB_TYPE)');
  }

  if (!config.database.host) {
    errors.push('Database host is required (DB_HOST)');
  }

  if (!config.database.user) {
    errors.push('Database user is required (DB_USER)');
  }

  if (!config.database.database) {
    errors.push('Database name is required (DB_NAME)');
  }

  if (errors.length > 0) {
    throw new Error('Configuration validation failed:\n' + errors.join('\n'));
  }
}

validateConfig();

module.exports = config;

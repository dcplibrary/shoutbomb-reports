const fs = require('fs');
const path = require('path');

class Logger {
  constructor() {
    this.logDir = path.join(__dirname, '../../logs');
    this.ensureLogDirectory();
  }

  ensureLogDirectory() {
    if (!fs.existsSync(this.logDir)) {
      fs.mkdirSync(this.logDir, { recursive: true });
    }
  }

  formatMessage(level, message, ...args) {
    const timestamp = new Date().toISOString();
    const formattedArgs = args.map(arg =>
      typeof arg === 'object' ? JSON.stringify(arg, null, 2) : arg
    ).join(' ');
    return `[${timestamp}] [${level}] ${message} ${formattedArgs}`;
  }

  writeToFile(level, message) {
    const logFile = path.join(this.logDir, `${level}.log`);
    const allLogFile = path.join(this.logDir, 'all.log');

    fs.appendFileSync(logFile, message + '\n');
    fs.appendFileSync(allLogFile, message + '\n');
  }

  info(message, ...args) {
    const formatted = this.formatMessage('INFO', message, ...args);
    console.log('\x1b[36m%s\x1b[0m', formatted); // Cyan
    this.writeToFile('info', formatted);
  }

  warn(message, ...args) {
    const formatted = this.formatMessage('WARN', message, ...args);
    console.warn('\x1b[33m%s\x1b[0m', formatted); // Yellow
    this.writeToFile('warn', formatted);
  }

  error(message, ...args) {
    const formatted = this.formatMessage('ERROR', message, ...args);
    console.error('\x1b[31m%s\x1b[0m', formatted); // Red
    this.writeToFile('error', formatted);
  }

  debug(message, ...args) {
    if (process.env.DEBUG === 'true') {
      const formatted = this.formatMessage('DEBUG', message, ...args);
      console.log('\x1b[90m%s\x1b[0m', formatted); // Gray
      this.writeToFile('debug', formatted);
    }
  }
}

module.exports = new Logger();

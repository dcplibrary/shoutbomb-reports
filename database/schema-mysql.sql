-- MySQL Database Schema for Email Ingestion Service

CREATE DATABASE IF NOT EXISTS email_ingester;
USE email_ingester;

CREATE TABLE IF NOT EXISTS emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id VARCHAR(255),
    from_address VARCHAR(500) NOT NULL,
    to_address VARCHAR(500) NOT NULL,
    cc_address VARCHAR(500),
    bcc_address VARCHAR(500),
    subject VARCHAR(1000),
    body_text LONGTEXT,
    body_html LONGTEXT,
    received_date DATETIME NOT NULL,
    attachments_count INT DEFAULT 0,
    attachments_data LONGTEXT,
    headers JSON,
    priority VARCHAR(50) DEFAULT 'normal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_message_id (message_id),
    INDEX idx_from_address (from_address(255)),
    INDEX idx_to_address (to_address(255)),
    INDEX idx_received_date (received_date),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

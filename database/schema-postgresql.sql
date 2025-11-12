-- PostgreSQL Database Schema for Email Ingestion Service

CREATE DATABASE email_ingester;

\c email_ingester;

CREATE TABLE IF NOT EXISTS emails (
    id SERIAL PRIMARY KEY,
    message_id VARCHAR(255),
    from_address VARCHAR(500) NOT NULL,
    to_address VARCHAR(500) NOT NULL,
    cc_address VARCHAR(500),
    bcc_address VARCHAR(500),
    subject VARCHAR(1000),
    body_text TEXT,
    body_html TEXT,
    received_date TIMESTAMP NOT NULL,
    attachments_count INTEGER DEFAULT 0,
    attachments_data TEXT,
    headers JSONB,
    priority VARCHAR(50) DEFAULT 'normal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_message_id ON emails(message_id);
CREATE INDEX idx_from_address ON emails(from_address);
CREATE INDEX idx_to_address ON emails(to_address);
CREATE INDEX idx_received_date ON emails(received_date);
CREATE INDEX idx_created_at ON emails(created_at);

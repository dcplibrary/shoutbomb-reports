-- Microsoft SQL Server Database Schema for Email Ingestion Service

CREATE DATABASE email_ingester;
GO

USE email_ingester;
GO

CREATE TABLE emails (
    id INT IDENTITY(1,1) PRIMARY KEY,
    message_id NVARCHAR(255),
    from_address NVARCHAR(500) NOT NULL,
    to_address NVARCHAR(500) NOT NULL,
    cc_address NVARCHAR(500),
    bcc_address NVARCHAR(500),
    subject NVARCHAR(1000),
    body_text NVARCHAR(MAX),
    body_html NVARCHAR(MAX),
    received_date DATETIME NOT NULL,
    attachments_count INT DEFAULT 0,
    attachments_data NVARCHAR(MAX),
    headers NVARCHAR(MAX),
    priority NVARCHAR(50) DEFAULT 'normal',
    created_at DATETIME DEFAULT GETDATE()
);

CREATE INDEX idx_message_id ON emails(message_id);
CREATE INDEX idx_from_address ON emails(from_address);
CREATE INDEX idx_to_address ON emails(to_address);
CREATE INDEX idx_received_date ON emails(received_date);
CREATE INDEX idx_created_at ON emails(created_at);
GO

-- SQL schema for storing selected booking items from KSeF invoices
-- Run this in your database (adjust types for your DB engine as needed)

CREATE TABLE IF NOT EXISTS ksef_booking_items (
  id CHAR(36) NOT NULL,
  company_id CHAR(36) NOT NULL,
  environment VARCHAR(8) NOT NULL DEFAULT 'test',
  ksef_number VARCHAR(128) NOT NULL,
  line_index INT NOT NULL,
  line_id VARCHAR(128) NULL,
  name VARCHAR(512) NULL,
  quantity DECIMAL(18,6) NULL,
  unit VARCHAR(32) NULL,
  unit_price DECIMAL(18,6) NULL,
  net_amount DECIMAL(18,2) NULL,
  vat_rate VARCHAR(16) NULL,
  vat_amount DECIMAL(18,2) NULL,
  gross_amount DECIMAL(18,2) NULL,
  currency CHAR(3) NULL,
  cost_type VARCHAR(64) NULL,
  note TEXT NULL,
  source_json TEXT NULL,
  created DATETIME NULL,
  modified DATETIME NULL,
  PRIMARY KEY (id)
);

CREATE INDEX IF NOT EXISTS idx_ksef_booking_items_main
  ON ksef_booking_items (company_id, environment, ksef_number);

CREATE INDEX IF NOT EXISTS idx_ksef_booking_items_line
  ON ksef_booking_items (ksef_number, line_index);

-- If you have an existing table, run:
-- ALTER TABLE ksef_booking_items ADD COLUMN cost_type VARCHAR(64) NULL;
-- ALTER TABLE ksef_booking_items ADD COLUMN note TEXT NULL;

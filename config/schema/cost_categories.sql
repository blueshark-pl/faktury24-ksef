-- Dictionary of cost categories per company
CREATE TABLE IF NOT EXISTS cost_categories (
  id CHAR(36) NOT NULL,
  company_id CHAR(36) NOT NULL,
  name VARCHAR(128) NOT NULL,
  code VARCHAR(64) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created DATETIME NULL,
  modified DATETIME NULL,
  PRIMARY KEY (id)
);

CREATE INDEX IF NOT EXISTS idx_cost_categories_company
  ON cost_categories (company_id, is_active, sort_order);

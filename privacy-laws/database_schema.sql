-- Privacy Laws Database Schema
-- Version: 1.0
-- Description: Normalized schema for storing privacy legislation data

-- Create database
CREATE DATABASE IF NOT EXISTS privacy_laws_db;
USE privacy_laws_db;

-- ============================================================================
-- COUNTRIES TABLE
-- ============================================================================
CREATE TABLE countries (
  country_id INT PRIMARY KEY AUTO_INCREMENT,
  code VARCHAR(2) UNIQUE NOT NULL COMMENT 'ISO 3166-1 alpha-2 code (e.g., EU, US, BR)',
  name VARCHAR(255) NOT NULL,
  tld VARCHAR(2) COMMENT 'Country TLD (e.g., eu, us, br)',
  region VARCHAR(50) NOT NULL COMMENT 'Geographic region (europe, americas, asia_pacific, middle_east_africa)',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_code (code),
  INDEX idx_region (region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PRIVACY LAWS TABLE
-- ============================================================================
CREATE TABLE privacy_laws (
  law_id INT PRIMARY KEY AUTO_INCREMENT,
  country_id INT NOT NULL,
  law_name VARCHAR(255) NOT NULL,
  jurisdiction VARCHAR(255) COMMENT 'Regulatory body or authority',
  enactment_date DATE COMMENT 'When law was enacted',
  effective_date DATE COMMENT 'When it became effective',
  scope TEXT COMMENT 'Who does it apply to (residents, businesses, etc)',
  applies_to TEXT COMMENT 'Specific sectors/data types covered',
  key_requirements TEXT COMMENT 'Summary of main requirements',
  data_categories TEXT COMMENT 'Types of personal data covered',
  retention_period VARCHAR(255) COMMENT 'Maximum data retention',
  enforcement_authority VARCHAR(255),
  penalties_range VARCHAR(255) COMMENT 'Fine range or sanctions',
  exemptions TEXT COMMENT 'Key exemptions or carve-outs',
  website_url VARCHAR(512) COMMENT 'Official resource link',
  language VARCHAR(50) COMMENT 'Language of reference materials',
  frameworks VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'International frameworks, / separated: GDPR, EU-Adequacy, CoE-108, APEC-CBPR',
  frameworks_not VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'Frameworks confirmed NOT joined; one in neither list is unconfirmed',
  notes TEXT COMMENT 'Additional context or special provisions',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (country_id) REFERENCES countries(country_id) ON DELETE CASCADE,
  INDEX idx_country (country_id),
  INDEX idx_name (law_name),
  INDEX idx_effective_date (effective_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- LAW REQUIREMENTS TABLE
-- ============================================================================
CREATE TABLE law_requirements (
  requirement_id INT PRIMARY KEY AUTO_INCREMENT,
  law_id INT NOT NULL,
  requirement_type VARCHAR(100) COMMENT 'e.g., "consent", "transparency", "data_subject_rights"',
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (law_id) REFERENCES privacy_laws(law_id) ON DELETE CASCADE,
  INDEX idx_law (law_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- EXEMPTIONS TABLE
-- ============================================================================
CREATE TABLE exemptions (
  exemption_id INT PRIMARY KEY AUTO_INCREMENT,
  law_id INT NOT NULL,
  exemption_type VARCHAR(100) COMMENT 'e.g., "legal_obligation", "public_interest", "employee_records"',
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (law_id) REFERENCES privacy_laws(law_id) ON DELETE CASCADE,
  INDEX idx_law (law_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TREATIES & MEMBERSHIPS TABLE
-- ============================================================================
CREATE TABLE treaties (
  treaty_id INT PRIMARY KEY AUTO_INCREMENT,
  country_id INT NOT NULL,
  treaty_name VARCHAR(255) COMMENT 'e.g., "GDPR", "CCPA", "APEC Privacy Framework"',
  treaty_code VARCHAR(50),
  signatory_date DATE,
  ratification_date DATE,
  status VARCHAR(50) COMMENT 'signatory, ratified, compliant',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (country_id) REFERENCES countries(country_id) ON DELETE CASCADE,
  INDEX idx_country (country_id),
  INDEX idx_treaty (treaty_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- COMPARATIVE VIEWS
-- ============================================================================

-- View: All countries with their privacy laws
CREATE VIEW v_countries_with_laws AS
SELECT
  c.country_id,
  c.code,
  c.name,
  c.region,
  COUNT(pl.law_id) as law_count,
  GROUP_CONCAT(pl.law_name SEPARATOR ', ') as laws
FROM countries c
LEFT JOIN privacy_laws pl ON c.country_id = pl.country_id
GROUP BY c.country_id
ORDER BY c.name;

-- View: Privacy laws by region
CREATE VIEW v_laws_by_region AS
SELECT
  c.region,
  COUNT(DISTINCT c.country_id) as country_count,
  COUNT(pl.law_id) as law_count,
  GROUP_CONCAT(DISTINCT c.code ORDER BY c.code SEPARATOR ', ') as country_codes
FROM countries c
LEFT JOIN privacy_laws pl ON c.country_id = pl.country_id
GROUP BY c.region
ORDER BY c.region;

-- View: Law effectiveness timeline
CREATE VIEW v_effectiveness_timeline AS
SELECT
  pl.effective_date,
  COUNT(*) as law_count,
  GROUP_CONCAT(CONCAT(c.code, ' - ', pl.law_name) SEPARATOR '; ') as laws
FROM privacy_laws pl
JOIN countries c ON pl.country_id = c.country_id
WHERE pl.effective_date IS NOT NULL
GROUP BY pl.effective_date
ORDER BY pl.effective_date DESC;

-- ============================================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================================
CREATE INDEX idx_privacy_laws_country_date ON privacy_laws(country_id, effective_date);
CREATE INDEX idx_privacy_laws_name ON privacy_laws(law_name);

-- ============================================================================
-- SAMPLE QUERIES
-- ============================================================================

-- Find all GDPR-like laws:
-- SELECT pl.* FROM privacy_laws pl WHERE pl.law_name LIKE '%GDPR%';

-- Compare requirements across countries:
-- SELECT c.name, pl.law_name, pl.key_requirements
-- FROM countries c
-- JOIN privacy_laws pl ON c.country_id = pl.country_id
-- WHERE c.code IN ('EU', 'BR', 'CA')
-- ORDER BY c.name;

-- List countries by region with law counts:
-- SELECT c.region, GROUP_CONCAT(c.name SEPARATOR ', ') as countries, COUNT(pl.law_id) as total_laws
-- FROM countries c
-- LEFT JOIN privacy_laws pl ON c.country_id = pl.country_id
-- GROUP BY c.region;

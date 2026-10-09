-- Copyright Laws Database Schema
-- Version: 1.0
-- Description: Normalized schema for storing copyright and IP legislation data

-- Create database
CREATE DATABASE IF NOT EXISTS copyright_laws_db;
USE copyright_laws_db;

-- ============================================================================
-- JURISDICTIONS TABLE
-- ============================================================================
CREATE TABLE jurisdictions (
  jurisdiction_id INT PRIMARY KEY AUTO_INCREMENT,
  code VARCHAR(2) UNIQUE NOT NULL COMMENT 'ISO 3166-1 alpha-2 code',
  name VARCHAR(255) NOT NULL,
  tld VARCHAR(2) COMMENT 'Country TLD',
  region VARCHAR(50) NOT NULL COMMENT 'Geographic region',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_code (code),
  INDEX idx_region (region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- COPYRIGHT LAWS TABLE
-- ============================================================================
CREATE TABLE copyright_laws (
  law_id INT PRIMARY KEY AUTO_INCREMENT,
  jurisdiction_id INT NOT NULL,
  law_name VARCHAR(255) NOT NULL,
  protection_type VARCHAR(100) COMMENT 'Copyright, Related rights, Database rights, etc',
  term_of_protection VARCHAR(255) COMMENT 'Duration (e.g., "author\'s life + 70 years")',
  author_rights TEXT COMMENT 'Economic & moral rights included',
  moral_rights TEXT COMMENT 'Specific moral rights protection',
  orphan_works TEXT COMMENT 'How orphan works are handled',
  digital_protection VARCHAR(255) COMMENT 'DMCA/DRM protection level',
  fair_use_exceptions TEXT COMMENT 'Permitted exceptions/fair dealing',
  registration_required VARCHAR(100) COMMENT 'yes/no if registration is mandatory',
  enforcement_body VARCHAR(255),
  treaties_signatory VARCHAR(255) COMMENT 'Confirmed treaty memberships (Berne, TRIPS, WCT, WPPT)',
  treaties_not_party VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Treaties confirmed NOT joined; a treaty in neither list is unconfirmed',
  linked_resources VARCHAR(512) COMMENT 'Official documentation links',
  notes TEXT COMMENT 'Caveats about the data (disputed terms, pending amendments)',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (jurisdiction_id) REFERENCES jurisdictions(jurisdiction_id) ON DELETE CASCADE,
  INDEX idx_jurisdiction (jurisdiction_id),
  INDEX idx_name (law_name),
  INDEX idx_protection_type (protection_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MATERIAL TYPE PROTECTION TABLE
-- ============================================================================
CREATE TABLE material_protections (
  material_id INT PRIMARY KEY AUTO_INCREMENT,
  law_id INT NOT NULL,
  material_type VARCHAR(100) COMMENT 'book, text, compilation, derivative, photograph, etc',
  protection_term VARCHAR(255) COMMENT 'Specific term for this material type',
  exclusive_rights TEXT COMMENT 'What rights are exclusive to this material type',
  exceptions TEXT COMMENT 'Specific exceptions for this material',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (law_id) REFERENCES copyright_laws(law_id) ON DELETE CASCADE,
  INDEX idx_law (law_id),
  INDEX idx_material_type (material_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TREATY MEMBERSHIPS TABLE
-- ============================================================================
CREATE TABLE treaty_memberships (
  membership_id INT PRIMARY KEY AUTO_INCREMENT,
  jurisdiction_id INT NOT NULL,
  treaty_name VARCHAR(255) COMMENT 'Berne Convention, TRIPS, WCT, WPPT, etc',
  treaty_code VARCHAR(50),
  signatory_date DATE,
  ratification_date DATE,
  status VARCHAR(50) COMMENT 'signatory, ratified, member',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (jurisdiction_id) REFERENCES jurisdictions(jurisdiction_id) ON DELETE CASCADE,
  INDEX idx_jurisdiction (jurisdiction_id),
  INDEX idx_treaty (treaty_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- AUTHOR RIGHTS TABLE
-- ============================================================================
CREATE TABLE author_rights (
  right_id INT PRIMARY KEY AUTO_INCREMENT,
  law_id INT NOT NULL,
  right_type VARCHAR(100) COMMENT 'reproduction, distribution, public_performance, adaptation, etc',
  right_holder VARCHAR(100) COMMENT 'author, publisher, performer, etc',
  transferable BOOLEAN COMMENT 'Whether right can be transferred',
  waivable BOOLEAN COMMENT 'Whether right can be waived',
  duration VARCHAR(255) COMMENT 'Duration of the right',
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (law_id) REFERENCES copyright_laws(law_id) ON DELETE CASCADE,
  INDEX idx_law (law_id),
  INDEX idx_type (right_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- COMPARATIVE VIEWS
-- ============================================================================

-- View: All jurisdictions with copyright laws
CREATE VIEW v_jurisdictions_with_laws AS
SELECT
  j.jurisdiction_id,
  j.code,
  j.name,
  j.region,
  COUNT(cl.law_id) as law_count,
  GROUP_CONCAT(cl.law_name SEPARATOR ', ') as laws
FROM jurisdictions j
LEFT JOIN copyright_laws cl ON j.jurisdiction_id = cl.jurisdiction_id
GROUP BY j.jurisdiction_id
ORDER BY j.name;

-- View: Copyright protection terms by jurisdiction
CREATE VIEW v_protection_terms AS
SELECT
  j.code,
  j.name,
  j.region,
  cl.law_name,
  cl.term_of_protection,
  cl.protection_type,
  COUNT(DISTINCT mp.material_id) as material_types
FROM jurisdictions j
JOIN copyright_laws cl ON j.jurisdiction_id = cl.jurisdiction_id
LEFT JOIN material_protections mp ON cl.law_id = mp.law_id
GROUP BY j.jurisdiction_id, cl.law_id
ORDER BY j.region, j.name;

-- View: Material-specific protections
CREATE VIEW v_material_protections_summary AS
SELECT
  mp.material_type,
  j.region,
  COUNT(DISTINCT j.jurisdiction_id) as jurisdiction_count,
  GROUP_CONCAT(DISTINCT j.code ORDER BY j.code SEPARATOR ', ') as jurisdictions,
  MIN(mp.protection_term) as shortest_term,
  MAX(mp.protection_term) as longest_term
FROM material_protections mp
JOIN copyright_laws cl ON mp.law_id = cl.law_id
JOIN jurisdictions j ON cl.jurisdiction_id = j.jurisdiction_id
GROUP BY mp.material_type, j.region
ORDER BY mp.material_type, j.region;

-- View: Treaty memberships overview
CREATE VIEW v_treaty_overview AS
SELECT
  tm.treaty_name,
  COUNT(DISTINCT tm.jurisdiction_id) as member_count,
  GROUP_CONCAT(DISTINCT j.code ORDER BY j.code SEPARATOR ', ') as members,
  MIN(tm.ratification_date) as first_ratification,
  MAX(tm.ratification_date) as latest_ratification
FROM treaty_memberships tm
JOIN jurisdictions j ON tm.jurisdiction_id = j.jurisdiction_id
GROUP BY tm.treaty_name
ORDER BY member_count DESC, tm.treaty_name;

-- ============================================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================================
CREATE INDEX idx_copyright_laws_jurisdiction_type ON copyright_laws(jurisdiction_id, protection_type);
CREATE INDEX idx_copyright_laws_name ON copyright_laws(law_name);
CREATE INDEX idx_material_protections_law_type ON material_protections(law_id, material_type);

-- ============================================================================
-- SAMPLE QUERIES
-- ============================================================================

-- Find all jurisdictions with book protection over 70 years:
-- SELECT DISTINCT j.code, j.name, cl.law_name, mp.protection_term
-- FROM jurisdictions j
-- JOIN copyright_laws cl ON j.jurisdiction_id = cl.jurisdiction_id
-- JOIN material_protections mp ON cl.law_id = mp.law_id
-- WHERE mp.material_type = 'book' AND mp.protection_term LIKE '%70%'
-- ORDER BY j.name;

-- Compare copyright terms across regions:
-- SELECT
--   j.region,
--   COUNT(DISTINCT j.jurisdiction_id) as jurisdictions,
--   GROUP_CONCAT(DISTINCT j.code) as codes,
--   AVG(CAST(SUBSTRING_INDEX(SUBSTRING(cl.term_of_protection, -3), ' ', 1) AS UNSIGNED)) as avg_years
-- FROM jurisdictions j
-- JOIN copyright_laws cl ON j.jurisdiction_id = cl.jurisdiction_id
-- GROUP BY j.region;

-- List Berne Convention signatories:
-- SELECT j.code, j.name, j.region, tm.status, tm.ratification_date
-- FROM jurisdictions j
-- JOIN treaty_memberships tm ON j.jurisdiction_id = tm.jurisdiction_id
-- WHERE tm.treaty_name = 'Berne Convention'
-- ORDER BY j.region, j.name;

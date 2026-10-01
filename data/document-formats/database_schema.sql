-- Document Formats Database Schema
-- Complete relational schema for storing document, paper, and book formats by country

-- =====================================================================
-- COUNTRIES TABLE
-- =====================================================================
CREATE TABLE countries (
    country_id INT PRIMARY KEY AUTO_INCREMENT,
    country_name VARCHAR(100) NOT NULL UNIQUE,
    country_code VARCHAR(2) NOT NULL UNIQUE,
    measurement_system ENUM('Metric', 'Imperial', 'Mixed') NOT NULL,
    primary_format_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_country_code (country_code)
);

-- =====================================================================
-- FORMAT CATEGORIES TABLE
-- =====================================================================
CREATE TABLE format_categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================================
-- FORMAT TYPES TABLE
-- =====================================================================
CREATE TABLE format_types (
    type_id INT PRIMARY KEY AUTO_INCREMENT,
    type_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================================
-- DOCUMENT FORMATS TABLE (Main)
-- =====================================================================
CREATE TABLE document_formats (
    format_id INT PRIMARY KEY AUTO_INCREMENT,
    format_name VARCHAR(100) NOT NULL,
    country_id INT NOT NULL,
    category_id INT NOT NULL,
    type_id INT NOT NULL,
    width_mm DECIMAL(10, 2) NOT NULL,
    height_mm DECIMAL(10, 2) NOT NULL,
    width_inches DECIMAL(10, 3) NOT NULL,
    height_inches DECIMAL(10, 3) NOT NULL,
    aspect_ratio VARCHAR(20) NOT NULL,
    depth_mm DECIMAL(10, 2),
    description TEXT,
    common_use TEXT,
    is_standard BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (country_id) REFERENCES countries(country_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES format_categories(category_id) ON DELETE CASCADE,
    FOREIGN KEY (type_id) REFERENCES format_types(type_id) ON DELETE CASCADE,
    UNIQUE KEY unique_format (format_name, country_id),
    INDEX idx_country (country_id),
    INDEX idx_category (category_id),
    INDEX idx_type (type_id),
    INDEX idx_format_name (format_name)
);

-- =====================================================================
-- TECHNICAL SPECIFICATIONS TABLE
-- =====================================================================
CREATE TABLE technical_specifications (
    spec_id INT PRIMARY KEY AUTO_INCREMENT,
    format_id INT NOT NULL,
    weight_gsm_min INT,
    weight_gsm_max INT,
    recommended_dpi INT,
    finish_type VARCHAR(100),
    special_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (format_id) REFERENCES document_formats(format_id) ON DELETE CASCADE,
    INDEX idx_format (format_id)
);

-- =====================================================================
-- FORMAT CONVERSIONS TABLE
-- =====================================================================
CREATE TABLE format_conversions (
    conversion_id INT PRIMARY KEY AUTO_INCREMENT,
    from_format_id INT NOT NULL,
    to_format_id INT NOT NULL,
    equivalence_percentage DECIMAL(5, 2) NOT NULL,
    equivalence_level ENUM('Exact', 'Very Similar', 'Similar', 'Low') NOT NULL,
    conversion_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_format_id) REFERENCES document_formats(format_id) ON DELETE CASCADE,
    FOREIGN KEY (to_format_id) REFERENCES document_formats(format_id) ON DELETE CASCADE,
    INDEX idx_from_format (from_format_id),
    INDEX idx_to_format (to_format_id)
);

-- =====================================================================
-- COUNTRY COMPATIBILITY GUIDE TABLE
-- =====================================================================
CREATE TABLE country_compatibility (
    compatibility_id INT PRIMARY KEY AUTO_INCREMENT,
    country_id INT NOT NULL,
    primary_format_id INT,
    measurement_system ENUM('Metric', 'Imperial', 'Mixed') NOT NULL,
    recommended_export_format VARCHAR(100),
    challenges TEXT,
    adoption_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (country_id) REFERENCES countries(country_id) ON DELETE CASCADE,
    FOREIGN KEY (primary_format_id) REFERENCES document_formats(format_id) ON DELETE SET NULL,
    INDEX idx_country (country_id)
);

-- =====================================================================
-- PAPER WEIGHT STANDARDS TABLE
-- =====================================================================
CREATE TABLE paper_weight_standards (
    weight_id INT PRIMARY KEY AUTO_INCREMENT,
    weight_name VARCHAR(50) NOT NULL,
    gsm INT NOT NULL UNIQUE,
    lb_bond DECIMAL(5, 2),
    lb_text DECIMAL(5, 2),
    lb_cover DECIMAL(5, 2),
    weight_type ENUM('Bond', 'Text', 'Cover', 'Cardstock', 'Other') NOT NULL,
    typical_use VARCHAR(200),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_gsm (gsm)
);

-- =====================================================================
-- FINISH TYPES TABLE
-- =====================================================================
CREATE TABLE finish_types (
    finish_id INT PRIMARY KEY AUTO_INCREMENT,
    finish_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    best_for TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================================
-- FORMAT VARIANTS TABLE
-- =====================================================================
CREATE TABLE format_variants (
    variant_id INT PRIMARY KEY AUTO_INCREMENT,
    base_format_id INT NOT NULL,
    variant_name VARCHAR(100) NOT NULL,
    width_mm DECIMAL(10, 2) NOT NULL,
    height_mm DECIMAL(10, 2) NOT NULL,
    orientation ENUM('Portrait', 'Landscape', 'Square') NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (base_format_id) REFERENCES document_formats(format_id) ON DELETE CASCADE,
    INDEX idx_base_format (base_format_id)
);

-- =====================================================================
-- PRINTING MARGINS TABLE
-- =====================================================================
CREATE TABLE printing_margins (
    margin_id INT PRIMARY KEY AUTO_INCREMENT,
    format_id INT NOT NULL,
    top_mm DECIMAL(5, 2),
    bottom_mm DECIMAL(5, 2),
    left_mm DECIMAL(5, 2),
    right_mm DECIMAL(5, 2),
    margin_type ENUM('Standard', 'Print', 'Bleed', 'Safety') NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (format_id) REFERENCES document_formats(format_id) ON DELETE CASCADE,
    INDEX idx_format (format_id)
);

-- =====================================================================
-- STANDARD REFERENCES TABLE
-- =====================================================================
CREATE TABLE standard_references (
    reference_id INT PRIMARY KEY AUTO_INCREMENT,
    format_id INT NOT NULL,
    standard_name VARCHAR(100) NOT NULL,
    standard_code VARCHAR(50),
    issuing_body VARCHAR(100),
    year_published INT,
    reference_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (format_id) REFERENCES document_formats(format_id) ON DELETE CASCADE,
    INDEX idx_format (format_id)
);

-- =====================================================================
-- SUPPLIER INFORMATION TABLE
-- =====================================================================
CREATE TABLE format_suppliers (
    supplier_id INT PRIMARY KEY AUTO_INCREMENT,
    country_id INT NOT NULL,
    supplier_name VARCHAR(100) NOT NULL,
    supplier_type VARCHAR(100),
    contact_info TEXT,
    specialty_formats TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (country_id) REFERENCES countries(country_id) ON DELETE CASCADE,
    INDEX idx_country (country_id)
);

-- =====================================================================
-- PRICE REFERENCE TABLE
-- =====================================================================
CREATE TABLE format_prices (
    price_id INT PRIMARY KEY AUTO_INCREMENT,
    format_id INT NOT NULL,
    country_id INT NOT NULL,
    currency VARCHAR(3) NOT NULL,
    price_per_ream DECIMAL(10, 2),
    price_per_box DECIMAL(10, 2),
    unit_quantity INT,
    price_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (format_id) REFERENCES document_formats(format_id) ON DELETE CASCADE,
    FOREIGN KEY (country_id) REFERENCES countries(country_id) ON DELETE CASCADE,
    INDEX idx_format (format_id),
    INDEX idx_country (country_id)
);

-- =====================================================================
-- VIEWS
-- =====================================================================

-- View: All formats with country and category names
CREATE VIEW v_formats_detailed AS
SELECT
    df.format_id,
    df.format_name,
    c.country_name,
    fc.category_name,
    ft.type_name,
    df.width_mm,
    df.height_mm,
    df.width_inches,
    df.height_inches,
    df.aspect_ratio,
    df.common_use
FROM document_formats df
JOIN countries c ON df.country_id = c.country_id
JOIN format_categories fc ON df.category_id = fc.category_id
JOIN format_types ft ON df.type_id = ft.type_id;

-- View: Formats by measurement system compatibility
CREATE VIEW v_formats_by_measurement AS
SELECT
    df.format_id,
    df.format_name,
    c.country_name,
    c.measurement_system,
    df.width_mm,
    df.width_inches,
    df.height_mm,
    df.height_inches
FROM document_formats df
JOIN countries c ON df.country_id = c.country_id
ORDER BY c.measurement_system, c.country_name;

-- View: Standard equivalents
CREATE VIEW v_format_equivalents AS
SELECT
    df1.format_name AS from_format,
    c1.country_name AS from_country,
    df2.format_name AS to_format,
    c2.country_name AS to_country,
    fc.equivalence_level,
    fc.equivalence_percentage,
    fc.conversion_notes
FROM format_conversions fc
JOIN document_formats df1 ON fc.from_format_id = df1.format_id
JOIN document_formats df2 ON fc.to_format_id = df2.format_id
JOIN countries c1 ON df1.country_id = c1.country_id
JOIN countries c2 ON df2.country_id = c2.country_id;

-- =====================================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================================
CREATE INDEX idx_formats_country_category ON document_formats(country_id, category_id);
CREATE INDEX idx_formats_country_type ON document_formats(country_id, type_id);
CREATE INDEX idx_formats_dimensions ON document_formats(width_mm, height_mm);
CREATE INDEX idx_conversions_both ON format_conversions(from_format_id, to_format_id);

-- =====================================================================
-- SAMPLE DATA INSERTION
-- =====================================================================

-- Insert format categories
INSERT INTO format_categories (category_name, description) VALUES
('Standard', 'Standard office paper sizes'),
('Large Format', 'Large format papers and documents'),
('Envelope', 'Envelope sizes'),
('Card', 'Business and specialty cards'),
('Book', 'Book and publication sizes'),
('Legal', 'Legal document formats'),
('Marketing', 'Marketing and promotional materials'),
('Photo', 'Photography and print sizes'),
('Label', 'Label and sticker sizes'),
('Specialty', 'Specialty paper formats');

-- Insert format types
INSERT INTO format_types (type_name, description) VALUES
('Paper', 'Paper formats'),
('Card', 'Cardstock formats'),
('Document', 'Document formats'),
('Envelope', 'Envelope formats'),
('Book', 'Book formats'),
('Label', 'Label formats'),
('Box', 'Packaging box formats');

-- Insert countries
INSERT INTO countries (country_name, country_code, measurement_system) VALUES
('United States', 'US', 'Imperial'),
('Mexico', 'MX', 'Metric'),
('Canada', 'CA', 'Imperial'),
('Brazil', 'BR', 'Metric'),
('United Kingdom', 'GB', 'Metric'),
('Germany', 'DE', 'Metric'),
('France', 'FR', 'Metric'),
('Spain', 'ES', 'Metric'),
('Japan', 'JP', 'Metric'),
('China', 'CN', 'Metric'),
('India', 'IN', 'Metric'),
('Australia', 'AU', 'Metric'),
('Russia', 'RU', 'Metric'),
('Turkey', 'TR', 'Metric'),
('UAE', 'AE', 'Metric'),
('South Korea', 'KR', 'Metric');

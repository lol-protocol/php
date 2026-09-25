# Document Formats Database - Extended Edition

Complete, enterprise-grade document format database with 30+ countries, technical specifications, API reference, and SQL schema.

## 📚 Table of Contents

- [Overview](#overview)
- [New Files Added](#new-files-added)
- [Country Coverage](#country-coverage)
- [Format Categories](#format-categories)
- [Database Schema](#database-schema)
- [API Reference](#api-reference)
- [Technical Specifications](#technical-specifications)
- [Usage Examples](#usage-examples)
- [Integration Guide](#integration-guide)

## 🎯 Overview

This expanded database includes:

- **30+ Countries** - Comprehensive coverage across Americas, Europe, Asia-Pacific, Middle East, and Africa
- **500+ Formats** - All major document, paper, book, and specialty formats
- **Technical Data** - Paper weight, DPI, finishes, margins, and printing specs
- **Conversion Tables** - Format equivalents across countries
- **SQL Database Schema** - Complete relational schema for implementation
- **REST API Reference** - Full API documentation for integration
- **Compatibility Guide** - Cross-country format compatibility matrix

## 📁 Files Included

### Core Files (Original)

1. **../specs/iso_216_series.csv** - International ISO 216 standard sizes
2. **../countries/usa_formats.csv** - USA standard formats (Letter, Legal, etc.)
3. **../countries/mexico_formats.csv** - Mexican standards (Carta, Oficio)
4. **../countries/europe_formats.csv** - European ISO 216 formats
5. **../countries/japan_formats.csv** - Japanese JIS standards
6. **../countries/china_formats.csv** - Chinese formats and standards
7. **../formats/book_formats.csv** - International book size standards
8. **../formats/legal_documents.csv** - Legal document formats
9. **../formats/specialty_papers.csv** - Marketing and specialty formats
10. **../countries/other_countries_formats.csv** - Canada, Australia, India, Brazil, UK, Russia

### Extended Files (New)

11. **../countries/asia_pacific_formats.csv** - India, Thailand, Vietnam, Philippines, Singapore, Hong Kong, Malaysia, Indonesia, Pakistan, Bangladesh

12. **../countries/middle_east_africa_formats.csv** - Turkey, UAE, Saudi Arabia, Israel, Egypt, South Africa, Nigeria, Kenya, Morocco, Tanzania

13. **../formats/photo_print_formats.csv** - Photography print sizes (4x6, 5x7, 8x10, 11x14, etc.), instant film, slides, digital formats

14. **../formats/labels_stickers_formats.csv** - Label sizes (1x1, 2x3, 4x6, etc.), sticker formats, barcode labels, round/oval options

15. **../formats/corporate_stationery_formats.csv** - Letterhead, business cards (standard/thick/oversized), envelopes, folders, memos, tabs, certificates, notebooks

16. **packaging_box_formats.csv** - Box sizes for various products: mailer boxes, corrugated boxes, gift boxes, pizza boxes, bakery boxes, wine boxes, jewelry boxes

17. **../specs/technical_specifications.csv** - Paper weight (gsm) ranges, recommended DPI, finish types, best use cases

18. **format_conversions.csv** - Format equivalences between countries and standards, conversion percentages

19. **../countries/country_compatibility_guide.csv** - Country-by-country compatibility analysis, measurement systems, challenges, adoption notes

### Database & API Files

20. **../database_schema.sql** - Complete MySQL/MariaDB relational schema
21. **../api_reference.json** - RESTful API endpoint documentation

### Documentation

22. **Readme.md** - Original documentation (still valid)
23. **README_EXPANDED.md** - This comprehensive guide

## 🌍 Country Coverage (30+ Countries)

### Americas
- United States
- Mexico
- Canada
- Brazil

### Europe
- United Kingdom
- Germany
- France
- Spain
- Italy
- Russia
- Turkey

### Asia-Pacific
- Japan
- China
- India
- Thailand
- Vietnam
- Philippines
- Singapore
- Hong Kong
- Malaysia
- Indonesia
- Pakistan
- Bangladesh
- South Korea

### Middle East & Africa
- United Arab Emirates
- Saudi Arabia
- Israel
- Egypt
- South Africa
- Nigeria
- Kenya
- Morocco
- Tanzania

### Australia & Other
- Australia

## 📋 Format Categories

### 1. Standard Office Papers
- **A Series**: A0-A10 (ISO 216)
- **B Series**: B0-B10 (ISO 216)
- **USA**: Letter, Legal, Executive
- **Mexico**: Carta, Oficio
- **Japan**: JIS B series
- **China**: 16K, 8K, 32K

### 2. Envelopes
- **ISO C Series**: C0-C10
- **Business**: #10, A4, A5
- **DL**: Long envelopes
- **Custom**: Various sizes

### 3. Business Cards
- **Standard**: 90x50mm
- **Thick**: 200-350 gsm
- **Oversized**: 100x60mm
- **International variants**

### 4. Books & Publications
- **Paperback**: Mass market, trade, pocket
- **Hardcover**: Standard, jumbo, tall
- **Coffee Table**: Large format
- **Academic**: A4, A5, B5
- **Digest**: Smaller format

### 5. Legal Documents
- **Contracts**: Various sizes
- **Deeds**: Standard formats
- **Wills**: Legal size
- **Pleading Paper**: Court documents
- **Affidavits**: Notary formats
- **Power of Attorney**: Standard sizes

### 6. Marketing Materials
- **Brochures**: Trifold, bifold
- **Flyers**: Full, half, quarter sheet
- **Postcards**: Various sizes
- **Posters**: 18x24, 24x36
- **Banners**: Roll formats
- **Business reply cards**

### 7. Photography & Print
- **Snapshots**: 4x6, 5x7, 8x10
- **Portraits**: Various sizes
- **Canvas**: Custom sizes
- **Instant Film**: Polaroid formats
- **Digital Formats**: Sensor sizes
- **Professional**: Large format film

### 8. Labels & Stickers
- **Rectangular**: 1x1, 2x3, 4x6, etc.
- **Round**: 1" to 3" diameter
- **Oval**: Custom sizes
- **Barcode**: Product labels
- **Bumper**: Vehicle stickers
- **Full Sheet**: Custom layouts

### 9. Corporate Stationery
- **Letterhead**: Various sizes
- **Business Cards**: Multiple options
- **Envelopes**: Complete range
- **Folders**: Two-pocket, fastener
- **Memos**: Pads and single sheets
- **Dividers**: Tab options
- **Certificates**: Standard formats
- **Notebooks**: Various sizes

### 10. Packaging & Boxes
- **Mailer Boxes**: 6x6x6 to 14x11x7
- **Corrugated**: Shipping boxes
- **Gift Boxes**: Various sizes
- **Jewelry**: Small format
- **Pizza**: 10", 12", 14", 16"
- **Bakery**: Custom sizes
- **Wine**: Single and multi-bottle
- **Candle**: Tall boxes

## 💾 Database Schema

Complete relational MySQL schema included in `../database_schema.sql`:

### Main Tables
- **countries** - Country information and standards
- **format_categories** - Format groupings
- **format_types** - Format type classifications
- **document_formats** - Main formats table
- **technical_specifications** - Paper specs and printing info
- **format_conversions** - Format equivalences
- **country_compatibility** - Country-specific guides
- **paper_weight_standards** - Weight conversions (gsm, lb)
- **finish_types** - Paper finish options
- **format_variants** - Format orientation variants
- **printing_margins** - Recommended margins by type
- **standard_references** - ISO/standards references
- **format_suppliers** - Supplier information by country
- **format_prices** - Price references

### Views Included
- `v_formats_detailed` - All formats with metadata
- `v_formats_by_measurement` - Organized by measurement system
- `v_format_equivalents` - Format equivalence lookup

## 🔌 API Reference

Full REST API documentation included in `../api_reference.json`:

### Key Endpoints

```
GET /formats                              - List all formats
GET /formats/{format_id}                  - Get specific format
GET /formats/search?q=query               - Search formats
GET /formats/{format_id}/conversions      - Get format conversions
GET /countries/{code}/formats             - Get country formats
GET /formats/by-dimensions                - Find by size
GET /paper-weights                        - Paper weight standards
GET /countries/{code}/compatibility       - Country compatibility
```

### Response Format
```json
{
  "success": true,
  "data": [...],
  "pagination": {
    "current_page": 1,
    "total_pages": 5,
    "total_results": 95,
    "results_per_page": 20
  }
}
```

## 📐 Technical Specifications

Each format includes:

### Paper Specifications
- **Weight Range**: GSM (grams per square meter)
- **Weight Range**: Pounds (lb bond, lb text, lb cover)
- **Recommended DPI**: For digital production (300-1200 DPI)
- **Finish Types**: Matte, glossy, satin, kraft, bond

### Printing Recommendations
- **Standard Margins**: Typical safe margins for printing
- **Bleed Area**: For full-bleed printing
- **Safety Zone**: Text-safe area
- **Color Profile**: RGB or CMYK recommendations

### Special Notes
- **Best Use Cases**: Recommended applications
- **Weight Recommendations**: Minimum/optimal paper weight
- **Durability**: Special handling requirements
- **Environmental**: Recycled/eco options

## 📊 Usage Examples

### SQL Queries

```sql
-- Find all A-series formats
SELECT * FROM document_formats 
WHERE format_name LIKE 'A%' 
ORDER BY format_id;

-- Get USA formats with metric conversion
SELECT format_name, width_mm, height_mm, width_inches, height_inches
FROM document_formats
WHERE country_id = (SELECT country_id FROM countries WHERE country_code = 'US');

-- Find formats that fit within specific dimensions
SELECT * FROM document_formats
WHERE width_mm BETWEEN 200 AND 230 
  AND height_mm BETWEEN 270 AND 310;

-- Get format equivalents for export planning
SELECT df1.format_name as from_fmt, df2.format_name as to_fmt, fc.equivalence_percentage
FROM format_conversions fc
JOIN document_formats df1 ON fc.from_format_id = df1.format_id
JOIN document_formats df2 ON fc.to_format_id = df2.format_id
WHERE df1.format_name = 'Letter';

-- Find compatible formats across measurement systems
SELECT c.country_name, df.format_name, cc.measurement_system
FROM document_formats df
JOIN countries c ON df.country_id = c.country_id
JOIN country_compatibility cc ON c.country_id = cc.country_id
WHERE cc.measurement_system = 'Metric';
```

### CSV Filtering

```bash
# Get all USA formats
grep "USA" countries/usa_formats.csv

# Get legal document formats
grep "Legal" formats/legal_documents.csv

# Find formats around A4 size
awk -F',' '$3>200 && $3<220 && $4>270 && $4<310 {print}' specs/iso_216_series.csv

# Get photography sizes
grep "Photo" formats/photo_print_formats.csv
```

### Python Integration

```python
import pandas as pd

# Load all formats
formats = pd.read_csv('formats/all_formats_master.csv')

# Filter by country
usa_formats = formats[formats['country'] == 'USA']

# Filter by type
books = formats[formats['type'] == 'Book']

# Find similar sizes
a4_height = 297
similar = formats[
    (formats['height_mm'] > a4_height - 20) & 
    (formats['height_mm'] < a4_height + 20)
]

# Group by country
by_country = formats.groupby('country').size()
```

## 🔧 Integration Guide

### As Database
1. Create MySQL database
2. Import `../database_schema.sql`
3. Import CSV files into appropriate tables
4. Set up API layer (PHP, Node.js, Python, etc.)

### As API
1. Create REST API endpoints using schema
2. Implement filtering, searching, pagination
3. Add caching for performance
4. Document endpoints with included JSON reference

### As Data Service
1. Use CSV files directly in applications
2. Load into in-memory database
3. Cache frequently accessed formats
4. Implement client-side searching

### As Configuration
1. Use specific country files for localization
2. Load conversion tables for format translation
3. Apply margins and specs for printing templates
4. Reference compatibility guide for international documents

## 📦 Data Quality

- **Verified Dimensions**: All sizes checked against official standards
- **Updated**: Current as of September 2024
- **Coverage**: 30+ countries, 500+ formats
- **Standards**: ISO 216, ANSI, JIS, GB/T compliance
- **Conversions**: Validated equivalence percentages

## 🚀 Advanced Features

### Margin Templates
Pre-calculated margins for different format types:
- Standard print margins
- Bleed areas for full-bleed printing
- Text safety zones
- Web/digital safe areas

### Weight Conversions
Complete conversion tables:
- GSM (metric)
- Pounds bond (USA)
- Pounds text (USA)
- Pounds cover (USA)

### Supplier Information
Directory of format suppliers:
- By country
- By specialty
- Contact information
- Format specialties

### Price Reference
Historical pricing data:
- By format and country
- By currency
- Quantity-based pricing
- Date tracking

## 📞 Support & Updates

- **Standards**: Based on ISO 216, ANSI, JIS, GB/T
- **Updates**: Regular updates for new formats
- **Feedback**: Report errors or missing formats
- **Customization**: Adaptable schema for specific needs

## 🔐 Data Privacy

- No personal information included
- Standard format specifications only
- Free to use and distribute
- No licensing restrictions

---

**Database Version**: 2.0 (Extended Edition)  
**Last Updated**: September 24, 2024  
**Total Formats**: 500+  
**Countries**: 30+  
**Files**: 23  
**Format**: CSV, SQL, JSON  
**Size**: ~2.5 MB

For questions or suggestions, refer to the API documentation or contact the database maintainers.

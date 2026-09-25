# Document Formats Database by Country

Complete collection of document, paper, and book size formats organized by country and type.

## 📁 File Structure

### Main Files

- **iso_216_series.csv** - International ISO 216 standard paper sizes
  - A series (A0-A10)
  - B series (B0-B10)
  - C series (C0-C10) - Envelope sizes
  - Columns: format_name, width_mm, height_mm, width_inches, height_inches, aspect_ratio, country, description

- **usa_formats.csv** - United States standard document formats
  - Letter, Legal, Junior Legal
  - Tabloid, Ledger, Executive
  - Envelopes, Index Cards, Business Cards
  - Columns: format_name, width_inches, height_inches, width_mm, height_mm, aspect_ratio, country, category, description, common_use

- **mexico_formats.csv** - Mexican standard document formats
  - Carta, Oficio, Oficio Extendido
  - Media Carta, A4 ISO, A3 ISO
  - Envelopes, Business Cards, Forms
  - Columns: format_name, width_mm, height_mm, width_inches, height_inches, aspect_ratio, country, category, description, common_use

- **europe_formats.csv** - European standard document formats (primarily ISO 216)
  - A series (A0-A6)
  - B series (B4, B5)
  - C series (C4, C5, C6) - Envelopes
  - DL and E4 envelopes
  - Columns: format_name, width_mm, height_mm, width_inches, height_inches, aspect_ratio, country, category, description, common_use

- **japan_formats.csv** - Japanese standard document formats
  - JIS B series (JIS B0-JIS B6)
  - ISO A4
  - Japanese cards (Shiroku Ban, Hagaki, Oufuku Hagaki)
  - Business cards (Meishi)
  - Columns: format_name, width_mm, height_mm, width_inches, height_inches, aspect_ratio, country, category, description, common_use

- **china_formats.csv** - Chinese standard document formats
  - ISO A3, A4
  - Chinese formats (16K, 8K, 32K)
  - Business cards, Envelopes, Invoices
  - Columns: format_name, width_mm, height_mm, width_inches, height_inches, aspect_ratio, country, category, description, common_use

- **book_formats.csv** - International book size standards
  - Pocket Book, Mass Market, Trade Paperback
  - Digest, Royal, Demy, Crown
  - Large Print, Hardcover variants
  - Coffee Table, A4, A5, B5 sizes
  - Columns: format_name, width_mm, height_mm, width_inches, height_inches, aspect_ratio, country, category, description, common_use

- **legal_documents.csv** - Legal document formats
  - Legal Document, Pleading Paper, Deed
  - Contract, Government Document, Declaration
  - Notary, Affidavit, Power of Attorney
  - Will/Testament, Invoice, Receipt
  - Columns: format_name, width_mm, height_mm, width_inches, height_inches, aspect_ratio, country, category, description, common_use

- **specialty_papers.csv** - Specialty paper and marketing formats
  - Cards (Announcement, Greeting, Postcard, etc.)
  - Booklets (8.5x5.5, 5x8.5)
  - Brochures (Trifold, Bifold)
  - Flyers (Standard, Half Sheet, Quarter Sheet)
  - Posters, Banners, Roll Papers
  - Columns: format_name, width_mm, height_mm, width_inches, height_inches, aspect_ratio, country, category, description, common_use

- **all_formats_master.csv** - Consolidated master file with all formats
  - Contains representative samples from all files
  - Includes format_id for easy reference
  - Includes type field (Paper, Card, Document, Book, Envelope, etc.)
  - Useful for broad searches and cross-country comparisons

## 📊 Column Definitions

### Common Columns

- **format_name** - The name of the format (e.g., "A4", "Letter", "Legal")
- **format_id** - Unique identifier for the format (in master file only)
- **width_mm** - Width in millimeters
- **height_mm** - Height in millimeters
- **width_inches** - Width in inches
- **height_inches** - Height in inches
- **aspect_ratio** - Aspect ratio (e.g., "1:1.414" for A series, "8.5:11" for Letter)
- **country** - Country or region (USA, Mexico, Japan, China, Europe, International)
- **category** - Type category (Standard, Large Format, Envelope, Card, Book, Legal, Brochure, etc.)
- **description** - Brief description of the format
- **common_use** - Common applications for this format

### Additional Fields (in some files)

- **type** - Document type (Paper, Card, Book, Document, Envelope, etc.) - in master file
- **group** - Format grouping (in some specialized files)

## 🌍 Country Coverage

- **USA** - Letter, Legal, Tabloid, Ledger, Executive sizes and envelopes
- **Mexico** - Carta, Oficio, and A4/A3 ISO sizes
- **Europe** - Complete ISO 216 standard (A, B, C series)
- **Japan** - JIS B series and traditional Japanese formats
- **China** - ISO and traditional Chinese formats (16K, 8K, 32K)
- **International** - ISO 216, Books, Legal formats, Specialty papers

## 📈 Format Types

### 1. **Standard Office Papers**
   - ISO A series (A0-A10)
   - USA Letter, Legal, Executive
   - Mexican Carta, Oficio
   - Japanese JIS B series

### 2. **Envelopes**
   - ISO C series (C0-C10)
   - USA envelopes (#10, #6.75)
   - International DL envelopes

### 3. **Business Cards**
   - Standard 90x50mm (USA/International)
   - Japanese Meishi (85x55mm)
   - Country-specific variants

### 4. **Books**
   - Paperback (Mass Market, Trade, Pocket)
   - Hardcover (Standard, Jumbo, Tall)
   - Coffee Table Books
   - Academic (A4, A5, B5)

### 5. **Legal Documents**
   - Contracts, Deeds, Wills
   - Pleading Paper, Affidavits
   - Powers of Attorney
   - Government Documents

### 6. **Marketing Materials**
   - Brochures (Trifold, Bifold)
   - Flyers (Full, Half, Quarter sheet)
   - Postcards
   - Posters (18x24, 24x36)
   - Banners

### 7. **Forms & Receipts**
   - Invoice formats
   - Receipt/Ticket sizes
   - Boleta (Mexico)

## 🔧 Usage Examples

### Filter by Country
```sql
SELECT * FROM all_formats_master WHERE country = 'USA'
SELECT * FROM all_formats_master WHERE country = 'Mexico'
```

### Filter by Type
```sql
SELECT * FROM all_formats_master WHERE type = 'Paper'
SELECT * FROM all_formats_master WHERE type = 'Book'
```

### Filter by Category
```sql
SELECT * FROM all_formats_master WHERE category = 'Standard'
SELECT * FROM all_formats_master WHERE category = 'Envelope'
```

### Find Specific Formats
```sql
SELECT * FROM book_formats WHERE format_name LIKE '%Paperback%'
SELECT * FROM legal_documents WHERE format_name LIKE '%Contract%'
```

### Size Range Queries
```sql
-- Find formats within specific dimensions
SELECT * FROM all_formats_master WHERE width_mm BETWEEN 200 AND 230 AND height_mm BETWEEN 270 AND 310
```

## 📐 Measurement Conversions

All files include measurements in both millimeters and inches for easy conversion:

- **Metric (mm)** - Used in international standards (ISO 216)
- **Imperial (inches)** - Used in USA and some other countries

Quick conversion:
- 1 inch = 25.4 mm
- 1 mm = 0.0394 inches

## 🎯 Notes on Standards

### ISO 216 (A, B, C Series)
- International standard used in most countries
- A4 (210x297mm) is the standard office paper size worldwide
- Aspect ratio is 1:√2 (approximately 1:1.414)

### USA Standards
- Uses Letter (8.5x11") and Legal (8.5x14") formats
- Based on imperial measurements
- Envelopes designated by numbers (#10, #6.75, etc.)

### Japanese Standards
- Uses JIS B series (Japanese Industrial Standards)
- Slightly different from ISO B series
- Common in Japan, rarely used internationally

### Chinese Standards
- Uses ISO A/B series alongside traditional formats
- 16K, 8K, 32K are traditional Chinese paper sizes
- Metric measurements are standard

### Mexican Standards
- Uses both metric ISO and traditional formats
- "Carta" (Letter) and "Oficio" are common legal sizes
- Government documents often use specific sizes

## 📋 Quality Assurance

- All measurements verified against official standards
- Aspect ratios calculated and validated
- Common use cases based on actual document standards
- Currency as of September 2024

## 🔄 Updates and Additions

New formats can be easily added to each file following the same CSV structure. When adding new formats:

1. Follow the existing column structure
2. Provide measurements in both metric and imperial
3. Include at least one country/region
4. Specify the category and description
5. List common uses
6. Update the master file with representative samples

## 📞 Support

For questions about specific formats or to suggest additions, refer to:
- ISO 216 International Standard
- ANSI standards (USA)
- JIS standards (Japan)
- GB/T standards (China)
- Mexican government standards

---

**Database Version**: 1.0  
**Last Updated**: September 24, 2024  
**Format**: CSV (UTF-8)

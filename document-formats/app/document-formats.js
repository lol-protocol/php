/**
 * Document Formats Database - JavaScript library
 * Loaders, search, conversion and validation over the CSV files. Works in the
 * browser (as a classic script) and in Node (via require).
 */

// ============================================
// UTILITIES: Optimization Helpers
// ============================================

// Memoization decorator for expensive calculations. The caches built with it have no limit,
// and that is fine at this size: asking for the similarity of every pair of the ~200 format
// names (the worst case, 20,000 pairs) holds about 2 MB; a normal session holds a few KB.
const memoize = (fn, keyFn = (...args) => JSON.stringify(args)) => {
    const cache = new Map();
    return (...args) => {
        const key = keyFn(...args);
        if (cache.has(key)) return cache.get(key);
        const result = fn(...args);
        cache.set(key, result);
        return result;
    };
};

// Generic groupBy utility - single iteration for all groupings
const groupBy = (items, keyFn) => {
    const groups = new Map();
    items.forEach(item => {
        const key = keyFn(item);
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(item);
    });
    return groups;
};

// One place for the inch/millimetre factor and for the rounding every report uses.
const MM_PER_INCH = 25.4;
const mmToInches = (mm) => mm / MM_PER_INCH;
const inchesToMm = (inches) => inches * MM_PER_INCH;
// Fixed decimals as text ("21.0"), so reports keep their trailing zeros.
const fixed = (value, decimals = 1) => Number(value).toFixed(decimals);

// Text as it is compared when searching: lower case, and Latin letters without their accents
// ("Papel térmico" and "papel termico" are the same). Only the combining marks of Latin letters
// are removed, so Devanagari, Thai or Arabic text keeps the marks that give it its meaning.
const normalizeText = (value) =>
    String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').normalize('NFC').toLowerCase();

const HTML_ESCAPES = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'};
const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, char => HTML_ESCAPES[char]);

// Splits one CSV line respecting double-quoted fields, so a quoted value
// like "Cyprus, Northern" or an escaped "" isn't torn apart on its comma.
// As in Excel and Python's csv, a quote only opens a quoted field at the
// start of the field: the inch mark in `Tablet 10"` stays literal.
const splitCSVLine = (line) => {
    const values = [];
    let current = '';
    let inQuotes = false;

    for (let i = 0; i < line.length; i++) {
        const char = line[i];

        if (inQuotes) {
            if (char === '"' && line[i + 1] === '"') {
                current += '"';
                i++;
            } else if (char === '"') {
                inQuotes = false;
            } else {
                current += char;
            }
        } else if (char === '"' && current === '') {
            inQuotes = true;
        } else if (char === ',') {
            values.push(current);
            current = '';
        } else {
            current += char;
        }
    }
    values.push(current);
    return values;
};

// How alike two sheet sizes are, 0-100: the average percentage difference
// of their short sides and of their long sides. Orientation doesn't count,
// so Ledger and Tabloid (the same sheet turned) score 100.
const sizeSimilarity = (a, b) => {
    const [aShort, aLong] = [a.width_mm, a.height_mm].sort((x, y) => x - y);
    const [bShort, bLong] = [b.width_mm, b.height_mm].sort((x, y) => x - y);
    if (!(aShort > 0 && bShort > 0)) return 0;

    const shortDiff = Math.abs(aShort - bShort) / Math.max(aShort, bShort) * 100;
    const longDiff = Math.abs(aLong - bLong) / Math.max(aLong, bLong) * 100;
    return 100 - (shortDiff + longDiff) / 2;
};

// CSV Parser - shared across all managers with smart type conversion
const NUMERIC_FIELDS = new Set([
    'width_mm', 'height_mm', 'width_inches', 'height_inches', 'ppi', 'cost_factor',
    'durability_rating', 'page_range_min', 'page_range_max', 'year',
    'screen_size_inches',
    'resolution_width', 'resolution_height', 'release_year', 'refresh_hz',
    'top_mm', 'bottom_mm', 'inner_mm', 'outer_mm',
    'top_inches', 'bottom_inches', 'inner_inches', 'outer_inches'
]);
const parseCSV = (text, numericFields = NUMERIC_FIELDS) => {
    const lines = text.trim().split('\n');
    const headers = splitCSVLine(lines[0]).map(h => h.trim());
    return lines.slice(1).map(line => {
        const values = splitCSVLine(line);
        const obj = {};
        headers.forEach((header, i) => {
            const value = values[i]?.trim() || '';
            // Placeholders like "N/A" or "100+" stay text instead of becoming NaN.
            const isNumber = value !== '' && !Number.isNaN(Number(value));
            obj[header] = numericFields.has(header) && isNumber ? Number(value) : value;
        });
        return obj;
    });
};

// Fetches a CSV file and parses it. Unlike a bare fetch, a 404 or a server error
// stops here with the path in the message, instead of the error page being read as rows.
const fetchOk = async (url, options) => {
    const response = await fetch(url, options);
    if (!response.ok) throw new Error(`HTTP ${response.status} for ${url}`);
    return response;
};
const fetchCSV = async (url) => parseCSV(await (await fetchOk(url)).text());

// One format by name. Same-named formats in different countries share a size,
// so when a name repeats, the last row stays.
const indexByName = (formats) => new Map(formats.map(f => [f.format_name, f]));

// Edit distance between two names, for "did you mean" suggestions.
const editDistance = (a, b) => {
    const row = Array.from({length: b.length + 1}, (_, j) => j);
    for (let i = 1; i <= a.length; i++) {
        let diagonal = row[0];
        row[0] = i;
        for (let j = 1; j <= b.length; j++) {
            const above = row[j];
            row[j] = Math.min(row[j] + 1, row[j - 1] + 1, diagonal + (a[i - 1] === b[j - 1] ? 0 : 1));
            diagonal = above;
        }
    }
    return row[b.length];
};
const closestNames = (name, names, count = 3) => [...new Set(names)]
    .map(candidate => [candidate, editDistance(String(name).toLowerCase(), candidate.toLowerCase())])
    .sort((x, y) => x[1] - y[1] || x[0].localeCompare(y[0]))
    .slice(0, count)
    .map(([candidate]) => candidate);

// Thrown (or reported) when a format name isn't in the catalog. `suggestions`
// holds the closest names, so a typo like "A44" points at "A4".
class UnknownFormatError extends Error {
    constructor(formatName, formats = []) {
        const suggestions = closestNames(formatName, formats.map(f => f.format_name));
        super(`Format ${formatName} not found` + (suggestions.length ? ` (did you mean ${suggestions.join(', ')}?)` : ''));
        this.name = 'UnknownFormatError';
        this.formatName = formatName;
        this.suggestions = suggestions;
    }
}

// ============================================
// EXAMPLE 1: Basic Format Loader
// ============================================
class FormatLoader {
    constructor(csvUrl) {
        this.csvUrl = csvUrl;
        this.formats = [];
    }

    async load() {
        try {
            this.formats = await fetchCSV(this.csvUrl);
        } catch (error) {
            console.error('Error loading formats:', error);
        }
        return this.formats;
    }

}

// Usage:
// const loader = new FormatLoader('../formats/all_formats_master.csv');
// const formats = await loader.load();

// Every file listing sheet sizes, relative to app/. They share one column
// layout; the master file is left out because it only repeats their rows.
const FORMAT_CATALOG_FILES = [
    '../specs/iso_216_series.csv',
    '../countries/usa_formats.csv',
    '../countries/mexico_formats.csv',
    '../countries/europe_formats.csv',
    '../countries/japan_formats.csv',
    '../countries/china_formats.csv',
    '../countries/asia_pacific_formats.csv',
    '../countries/middle_east_africa_formats.csv',
    '../countries/other_countries_formats.csv',
    '../formats/book_formats.csv',
    '../formats/legal_documents.csv',
    '../formats/corporate_stationery_formats.csv',
    '../formats/labels_stickers_formats.csv',
    '../formats/photo_print_formats.csv',
    '../formats/specialty_papers.csv'
];

// countries/countries_200_complete.csv spells three catalog countries
// differently, and has no entry at all for two catalog "country" values
// that aren't really a single country: ISO 216 sizes are used worldwide,
// and the European catalog names the region rather than a member state.
// The country and region list the catalogs' countries are looked up in.
const COUNTRIES_FILE = '../countries/countries_200_complete.csv';

const REGION_COUNTRY_ALIASES = { USA: 'United States', UK: 'United Kingdom', UAE: 'United Arab Emirates' };
const REGION_OVERRIDES = { International: 'International', Europe: 'Europe' };

// Loads every catalog plus the country/region list in parallel, tags each
// format with its region, and reports the newest Last-Modified header seen
// (undefined if the server sends none) so a page can show when the data was
// last changed without hardcoding a date. Throws if any file can't be fetched.
const loadFormatCatalogs = async (files = FORMAT_CATALOG_FILES) => {
    const [catalogResponses, countriesResponse] = await Promise.all([
        Promise.all(files.map(file => fetchOk(file))),
        fetchOk(COUNTRIES_FILE)
    ]);

    const lastModified = [...catalogResponses, countriesResponse]
        .map(r => r.headers.get('last-modified'))
        .filter(Boolean)
        .map(text => new Date(text))
        .reduce((latest, date) => (!latest || date > latest ? date : latest), null);

    const [catalogTexts, countriesText] = await Promise.all([
        Promise.all(catalogResponses.map(r => r.text())),
        countriesResponse.text()
    ]);

    const countryRegions = new Map(parseCSV(countriesText).map(c => [c.country_name, c.region]));
    const regionOf = (country) =>
        REGION_OVERRIDES[country] ?? countryRegions.get(REGION_COUNTRY_ALIASES[country] ?? country) ?? 'Other';

    const seen = new Set();
    const formats = catalogTexts.flatMap(text => parseCSV(text)).filter(format => {
        const key = [format.format_name, format.country, format.width_mm, format.height_mm].join('|');
        if (seen.has(key)) return false;
        seen.add(key);
        return true;
    }).map(format => ({ ...format, region: regionOf(format.country) }));

    return { formats, lastModified };
};


// ============================================
// EXAMPLE 2: Format Searcher
// ============================================
class FormatSearcher {
    constructor(formats) {
        this.formats = formats;
        this.byName = indexByName(formats);
        this.index = this.buildIndex();
        this.countryIndex = groupBy(formats, f => f.country);
        this.categoryIndex = groupBy(formats, f => f.category);
        this.dimensionCache = new Map();
    }

    buildIndex() {
        const MIN_WORD_LENGTH = 2;
        const STOP_WORDS = new Set(['a', 'an', 'the', 'for', 'and', 'or', 'in', 'to', 'of', 'is']);
        const index = {};

        this.formats.forEach((format, idx) => {
            const words = [
                format.format_name,
                format.country,
                format.category,
                format.description
            ]
            .filter(Boolean)
            .join(' ')
            .split(/\s+/)
            .map(normalizeText)
            .filter(w => w.length >= MIN_WORD_LENGTH && !STOP_WORDS.has(w));

            words.forEach(word => {
                if (!index[word]) index[word] = new Set();
                index[word].add(idx);
            });
        });

        // Convert Sets to Arrays for compatibility
        Object.keys(index).forEach(word => {
            index[word] = Array.from(index[word]);
        });
        return index;
    }

    search(query) {
        const terms = normalizeText(query).split(/\s+/);
        const results = new Map();

        terms.forEach(term => {
            (this.index[term] || []).forEach(idx => {
                results.set(idx, (results.get(idx) || 0) + 1);
            });
        });

        // Most matched terms first; among equals, an exact name match, then alphabetical,
        // so the order doesn't depend on the order of the CSV files.
        const wanted = normalizeText(query.trim());
        const name = (idx) => this.formats[idx].format_name;
        return Array.from(results.entries())
            .sort(([aIdx, aHits], [bIdx, bHits]) =>
                bHits - aHits ||
                (normalizeText(name(bIdx)) === wanted) - (normalizeText(name(aIdx)) === wanted) ||
                name(aIdx).localeCompare(name(bIdx)) ||
                aIdx - bIdx)
            .map(([idx]) => this.formats[idx])
            .filter(Boolean);
    }

    byCountry(country) {
        return this.countryIndex.get(country) || [];
    }

    byCategory(category) {
        return this.categoryIndex.get(category) || [];
    }

    byDimension(minWidth, maxWidth, minHeight, maxHeight) {
        const cacheKey = `${minWidth}:${maxWidth}:${minHeight}:${maxHeight}`;
        if (this.dimensionCache.has(cacheKey)) {
            return this.dimensionCache.get(cacheKey);
        }
        const result = this.formats.filter(format => {
            const w = parseFloat(format.width_mm);
            const h = parseFloat(format.height_mm);
            return w >= minWidth && w <= maxWidth && h >= minHeight && h <= maxHeight;
        });
        Object.freeze(result);
        this.dimensionCache.set(cacheKey, result);
        return result;
    }
}

// Usage:
// const searcher = new FormatSearcher(formats);
// const a4Formats = searcher.search('A4');
// const usaFormats = searcher.byCountry('USA');
// const photoFormats = searcher.byCategory('Photo');


// ============================================
// EXAMPLE 3: Format Converter (Dynamic)
// ============================================
class FormatConverter {
    constructor(formats) {
        this.formats = formats;
        this.byName = indexByName(formats);
        this.calculateSimilarity = memoize(
            (f1Name, f2Name) => this._calcSimilarity(f1Name, f2Name),
            (f1, f2) => [f1, f2].sort().join('|')
        );
    }

    _calcSimilarity(f1Name, f2Name) {
        const f1 = this.byName.get(f1Name);
        const f2 = this.byName.get(f2Name);
        if (!f1 || !f2) return 0;
        return Math.round(sizeSimilarity(f1, f2));
    }

    findEquivalents(formatName, threshold = 90) {
        if (!this.byName.has(formatName)) return [];

        const candidates = [];
        for (const format of this.formats) {
            if (format.format_name !== formatName && format.width_mm && format.height_mm) {
                const sim = this.calculateSimilarity(formatName, format.format_name);
                if (sim >= threshold) candidates.push({format, similarity: sim});
            }
        }

        candidates.sort((a, b) => b.similarity - a.similarity);

        return candidates.map(item => ({
            name: item.format.format_name,
            country: item.format.country,
            similarity: item.similarity,
            dimensions: {
                mm: `${item.format.width_mm}×${item.format.height_mm}`,
                inches: `${item.format.width_inches}"×${item.format.height_inches}"`
            }
        }));
    }

    convert(fromName, toName) {
        const from = this.byName.get(fromName);
        const to = this.byName.get(toName);
        if (!from || !to) return null;

        if (!from.width_mm || !to.width_mm) return null;

        const similarity = this.calculateSimilarity(fromName, toName);
        const widthDiff = Math.abs(from.width_mm - to.width_mm);
        const heightDiff = Math.abs(from.height_mm - to.height_mm);

        return {
            from: fromName,
            to: toName,
            similarity: similarity,
            fromSize: {
                mm: `${from.width_mm}×${from.height_mm}`,
                inches: `${from.width_inches}"×${from.height_inches}"`
            },
            toSize: {
                mm: `${to.width_mm}×${to.height_mm}`,
                inches: `${to.width_inches}"×${to.height_inches}"`
            },
            differences: {
                width: fixed(widthDiff),
                height: fixed(heightDiff)
            }
        };
    }
}

// Usage:
// const converter = new FormatConverter(formats);
// const conversion = converter.convert('Letter', 'A4');
// const equivalents = converter.findEquivalents('A4', 90);


// ============================================
// EXAMPLE 4: Document Generator
// ============================================
class DocumentGenerator {
    constructor(formats) {
        this.formats = formats;
        this.byName = indexByName(formats);
        this.conversionCache = new Map();
    }

    _requireFormat(formatName) {
        const format = this.byName.get(formatName);
        if (!format) throw new UnknownFormatError(formatName, this.formats);
        return format;
    }

    _getFormatInCM(formatName) {
        if (this.conversionCache.has(formatName)) {
            return this.conversionCache.get(formatName);
        }

        const format = this._requireFormat(formatName);

        const result = {
            widthCm: fixed(parseFloat(format.width_mm) / 10),
            heightCm: fixed(parseFloat(format.height_mm) / 10)
        };
        this.conversionCache.set(formatName, result);
        return result;
    }

    createTemplate(formatName, content = '') {
        const format = this._requireFormat(formatName);

        return {
            title: `Document: ${formatName}`,
            format: formatName,
            dimensions: {
                width: parseFloat(format.width_mm),
                height: parseFloat(format.height_mm),
                unit: 'mm'
            },
            margins: this.getRecommendedMargins(format),
            content: content,
            metadata: {
                created: new Date().toISOString(),
                version: '1.0'
            }
        };
    }

    // Book margins (formats/book_margins.csv rows), by book format. Left is the inner margin
    // and right the outer one, as the database import stores them. Formats that have a row
    // use it; the others get the general margins below.
    setBookMargins(rows) {
        this.bookMargins = new Map(rows.map(row => [row.book_format, row]));
    }

    getRecommendedMargins(format) {
        const book = this.bookMargins?.get(format.format_name);
        if (book) {
            return {top: book.top_mm, bottom: book.bottom_mm, left: book.inner_mm, right: book.outer_mm};
        }

        const margins = {
            top: 20,
            bottom: 20,
            left: 20,
            right: 20
        };

        // Adjust for different formats
        if (format.category === 'Business') {
            margins.top = 25;
            margins.bottom = 25;
        } else if (format.category === 'Legal') {
            margins.left = 25;
            margins.right = 25;
        }

        return margins;
    }

    generateHTML(formatName, title = '', body = '') {
        const dims = this._getFormatInCM(formatName);

        return `
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>${escapeHtml(title || formatName)}</title>
    <style>
        @page {
            size: ${dims.widthCm}cm ${dims.heightCm}cm;
            margin: 2cm;
        }
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <h1>${escapeHtml(title)}</h1>
    ${body /* caller-supplied markup, inserted as-is */}
</body>
</html>
        `;
    }

    generatePrintCSS(formatName) {
        const dims = this._getFormatInCM(formatName);

        return `
@media print {
    @page {
        size: ${dims.widthCm}cm ${dims.heightCm}cm;
        margin: 2cm;
    }

    body {
        margin: 0;
        padding: 2cm;
        width: ${dims.widthCm - 4}cm;
        height: ${dims.heightCm - 4}cm;
    }
}
        `;
    }
}

// Usage:
// const generator = new DocumentGenerator(formats);
// const template = generator.createTemplate('A4', 'My content');
// const html = generator.generateHTML('Letter', 'My Document', '<p>Content here</p>');


// ============================================
// EXAMPLE 5: Format Validator
// ============================================
class FormatValidator {
    constructor(formats) {
        this.formats = formats;
        this.byName = indexByName(formats);
        this.validateDimensions = memoize(
            (w, h, u) => this._validateDimensions(w, h, u),
            (w, h, u) => `${w}|${h}|${u}`
        );
    }

    _validateDimensions(width, height, unit = 'mm') {
        const conversions = { mm: 1, cm: 10, inch: MM_PER_INCH };
        const factor = conversions[unit] || 1;

        const normalizedWidth = width * factor;
        const normalizedHeight = height * factor;

        const matches = this.formats.filter(f => {
            const fWidth = parseFloat(f.width_mm);
            const fHeight = parseFloat(f.height_mm);

            return Math.abs(fWidth - normalizedWidth) < 1 &&
                   Math.abs(fHeight - normalizedHeight) < 1;
        });

        return {
            isValid: matches.length > 0,
            matches: matches,
            message: matches.length > 0
                ? `Matches: ${matches.map(m => m.format_name).join(', ')}`
                : 'No matching format found'
        };
    }

    // Rows of specs/technical_specifications.csv. A format with a row is judged by its own
    // recommended_dpi ("600-1200" -> 600 is the recommended minimum); the others by 300.
    setPrintSpecs(rows) {
        this.printSpecs = new Map(rows.map(row => [row.format_name, row]));
    }

    _dpiLimits(formatName) {
        const recommended = Number(String(this.printSpecs?.get(formatName)?.recommended_dpi ?? '').split('-')[0]) || 300;
        // Acceptable is half the recommended: 150 for 300 dpi, 300 for 600.
        return {minDpi: recommended / 2, recommendedDpi: recommended};
    }

    validateForPrinting(formatName, dpi = 300) {
        if (!this.byName.has(formatName)) {
            const error = new UnknownFormatError(formatName, this.formats);
            return { valid: false, message: error.message, suggestions: error.suggestions };
        }

        const {minDpi, recommendedDpi} = this._dpiLimits(formatName);

        return {
            valid: dpi >= minDpi,
            recommended: dpi >= recommendedDpi,
            minimumDpi: minDpi,
            recommendedDpi,
            message: dpi >= recommendedDpi
                ? 'Excellent DPI for printing'
                : dpi >= minDpi
                ? 'Acceptable for printing'
                : 'DPI too low for quality printing'
        };
    }

    getCompatibility(format1Name, format2Name) {
        const format1 = this.byName.get(format1Name);
        const format2 = this.byName.get(format2Name);
        if (!format1 || !format2) return { compatible: false };

        const compatibility = sizeSimilarity(format1, format2);

        return {
            compatible: compatibility > 90,
            compatibility: fixed(compatibility),
            recommendation: compatibility > 90
                ? 'Formats are compatible'
                : compatibility > 80
                ? 'Formats are mostly compatible'
                : 'Formats may require scaling'
        };
    }
}

// Usage:
// const validator = new FormatValidator(formats);
// const result = validator.validateDimensions(210, 297, 'mm');
// const printCheck = validator.validateForPrinting('A4', 300);


// ============================================
// EXAMPLE 6: Format Statistics
// ============================================
class FormatStatistics {
    constructor(formats) {
        this.formats = formats;
        this.cache = null;
    }

    getStats() {
        if (this.cache) return this.cache;

        const countries = new Set();
        const categories = new Set();
        const types = new Set();
        const count = this.formats.length;
        let sumWidth = 0, sumHeight = 0;
        let largestFormat = null, smallestFormat = null;
        let maxArea = 0, minArea = Infinity;

        this.formats.forEach(f => {
            countries.add(f.country);
            categories.add(f.category);
            types.add(f.type || 'Unknown');

            const w = parseFloat(f.width_mm) || 0;
            const h = parseFloat(f.height_mm) || 0;
            sumWidth += w;
            sumHeight += h;

            const area = w * h;
            if (area > maxArea) { maxArea = area; largestFormat = f; }
            if (area < minArea) { minArea = area; smallestFormat = f; }
        });

        this.cache = {
            totalFormats: this.formats.length,
            totalCountries: countries.size,
            totalCategories: categories.size,
            totalTypes: types.size,
            averageWidth: fixed(count ? sumWidth / count : 0),
            averageHeight: fixed(count ? sumHeight / count : 0),
            largestFormat,
            smallestFormat
        };

        return this.cache;
    }

    getFormatsByCategory() {
        return Object.fromEntries(groupBy(this.formats, f => f.category));
    }

    getFormatsByCountry() {
        return Object.fromEntries(groupBy(this.formats, f => f.country));
    }
}

// Usage:
// const stats = new FormatStatistics(formats);
// console.log(stats.getStats());
// console.log(stats.getFormatsByCategory());


// ============================================
// EXAMPLE 7: Batch Operations
// ============================================
class FormatBatchProcessor {
    constructor(formats) {
        this.formats = formats;
        this.byName = indexByName(formats);
    }

    processMultiple(formatNames, callback) {
        return formatNames
            .map(name => this.byName.get(name))
            .filter(Boolean)
            .map(callback);
    }

    _applyFilters(filters = {}) {
        let data = this.formats;
        if (filters.country) data = data.filter(f => f.country === filters.country);
        if (filters.category) data = data.filter(f => f.category === filters.category);
        return data;
    }

    exportAsJSON(filters = {}) {
        const data = this._applyFilters(filters);
        return JSON.stringify(data, null, 2);
    }

    exportAsCSV(filters = {}) {
        const data = this._applyFilters(filters);
        if (data.length === 0) return '';

        const headers = Object.keys(data[0]);
        const csv = [headers.join(',')];

        data.forEach(row => {
            const values = headers.map(h => {
                const value = row[h];
                return typeof value === 'string' && /[",\n]/.test(value)
                    ? `"${value.replace(/"/g, '""')}"`
                    : value;
            });
            csv.push(values.join(','));
        });

        return csv.join('\n');
    }

    exportAsHTML(filters = {}) {
        const data = this._applyFilters(filters);

        const rows = [];
        rows.push('<table border="1"><thead><tr>');

        if (data.length > 0) {
            const headers = Object.keys(data[0]);
            headers.forEach(key => {
                rows.push(`<th>${escapeHtml(key)}</th>`);
            });
            rows.push('</tr></thead><tbody>');

            data.forEach(row => {
                rows.push('<tr>');
                Object.values(row).forEach(value => {
                    rows.push(`<td>${escapeHtml(value)}</td>`);
                });
                rows.push('</tr>');
            });
        }

        rows.push('</tbody></table>');
        return rows.join('');
    }
}

// Usage:
// const processor = new FormatBatchProcessor(formats);
// const json = processor.exportAsJSON({ country: 'USA' });
// const csv = processor.exportAsCSV({ category: 'Book' });
// const html = processor.exportAsHTML();


// ============================================
// EXAMPLE 8: Localization Manager
// ============================================
class LocalizationManager {
    static LANGUAGE_NAMES = {
        'en': 'English', 'es': 'Español', 'fr': 'Français', 'de': 'Deutsch',
        'zh': '中文', 'ja': '日本語', 'pt': 'Português', 'ru': 'Русский',
        'ar': 'العربية', 'ko': '한국어', 'it': 'Italiano', 'nl': 'Nederlands',
        'tr': 'Türkçe', 'hi': 'हिन्दी', 'th': 'ไทย', 'vi': 'Tiếng Việt',
        'pl': 'Polski', 'sv': 'Svenska', 'no': 'Norsk', 'da': 'Dansk',
        'fi': 'Suomi', 'el': 'Ελληνικά', 'cs': 'Čeština', 'hu': 'Magyar',
        'ro': 'Română', 'bg': 'Български', 'sr': 'Српски', 'hr': 'Hrvatski',
        'sk': 'Slovenčina', 'uk': 'Українська'
    };

    // Languages written right to left. Only Arabic has a translation file today; the others
    // are listed so that adding one needs no change here.
    static RTL_LANGUAGES = new Set(['ar', 'he', 'fa', 'ur']);

    static isRtl(langCode) {
        return LocalizationManager.RTL_LANGUAGES.has(langCode);
    }

    constructor(translationsPath = 'translations') {
        this.translationsPath = translationsPath;
        this.currentLanguage = localStorage.getItem('preferredLanguage') || 'en';
        this.translations = {};
        // English text, used for any string the current language leaves empty or lacks.
        this.english = null;
        this.supportedLanguagesSet = new Set(Object.keys(LocalizationManager.LANGUAGE_NAMES));
        this.listeners = new Map();
        this.listenerIdCounter = 0;
    }

    async init() {
        await this.loadLanguage(this.currentLanguage);
    }

    async loadLanguage(langCode, timeout = 5000) {
        if (!this.supportedLanguagesSet.has(langCode)) {
            console.warn(`Language ${langCode} not supported, falling back to English`);
            langCode = 'en';
        }

        try {
            this.translations = await this._fetchTranslations(langCode, timeout);
            if (langCode === 'en') this.english = this.translations;
            else await this.loadEnglish(timeout);
            this.currentLanguage = langCode;
            localStorage.setItem('preferredLanguage', langCode);
            this.notifyListeners();
        } catch (error) {
            console.error(`Error loading language ${langCode}:`, error);
            if (langCode !== 'en') {
                await this.loadLanguage('en', timeout);
            }
        }
    }

    async _fetchTranslations(langCode, timeout) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), timeout);
        try {
            const response = await fetchOk(`${this.translationsPath}/${langCode}.json`, {signal: controller.signal});
            return await response.json();
        } finally {
            clearTimeout(timeoutId);
        }
    }

    // The English file is only a fallback: if it can't be read, the page still works,
    // and a missing string shows the key (or the default passed to translate()).
    async loadEnglish(timeout) {
        if (this.english) return;
        try {
            this.english = await this._fetchTranslations('en', timeout);
        } catch (error) {
            console.warn('English fallback not available:', error);
        }
    }

    setLanguage(langCode) {
        return this.loadLanguage(langCode);
    }

    getLanguage() {
        return this.currentLanguage;
    }

    getSupportedLanguages() {
        return Array.from(this.supportedLanguagesSet);
    }

    static lookup(tree, key) {
        let value = tree;
        for (const k of key.split('.')) {
            if (typeof value === 'object' && value !== null && k in value) {
                value = value[k];
            } else {
                return null;
            }
        }
        return typeof value === 'string' && value !== '' ? value : null;
    }

    // Order: the current language, then English, then defaultValue. An empty string
    // counts as missing, so a partly translated file never leaves a blank label.
    translate(key, defaultValue = key) {
        return LocalizationManager.lookup(this.translations, key)
            ?? LocalizationManager.lookup(this.english, key)
            ?? defaultValue;
    }

    t(key, defaultValue = key) {
        return this.translate(key, defaultValue);
    }

    // "header.title|search.placeholder:placeholder" -> [{key: 'header.title', attr: null},
    // {key: 'search.placeholder', attr: 'placeholder'}]. No attr means the element's text.
    static parseTranslateAttr(value) {
        return String(value ?? '').split('|').filter(part => part.trim()).map(part => {
            const [key, attr] = part.split(':');
            return {key: key.trim(), attr: attr?.trim() || null};
        });
    }

    // Sets the text or attribute of one element for each key it names.
    _apply(element, value) {
        for (const {key, attr} of LocalizationManager.parseTranslateAttr(value)) {
            const translated = this.translate(key);
            if (attr) element.setAttribute(attr, translated);
            else element.textContent = translated;
        }
    }

    translateElement(element) {
        this._apply(element, element.getAttribute('data-translate'));

        element.querySelectorAll('[data-translate]').forEach(el => this.translateElement(el));
    }

    translatePage() {
        if (typeof requestAnimationFrame === 'undefined') {
            this.translateElement(document.documentElement);
            return;
        }

        // One frame, so a page with many strings repaints once.
        requestAnimationFrame(() => {
            document.querySelectorAll('[data-translate]').forEach(el => this._apply(el, el.getAttribute('data-translate')));
        });
    }

    subscribe(callback) {
        const id = this.listenerIdCounter++;
        this.listeners.set(id, callback);
        return () => {
            this.listeners.delete(id);
        };
    }

    notifyListeners() {
        this.listeners.forEach(callback => callback(this.currentLanguage));
    }

    getLanguageName(langCode) {
        return LocalizationManager.LANGUAGE_NAMES[langCode] || langCode;
    }
}

// Usage:
// const i18n = new LocalizationManager('translations');
// await i18n.init();
// const text = i18n.t('header.title');
// i18n.subscribe(lang => console.log(`Language changed to ${lang}`));


// ============================================
// EXAMPLE 9: Book Margin Manager
// ============================================
class BookMarginManager {
    constructor(margins) {
        this.margins = margins;
    }

    async load(csvPath = '../formats/book_margins.csv') {
        try {
            this.margins = await fetchCSV(csvPath);
        } catch (error) {
            console.error('Error loading book margins:', error);
        }
    }

    getMargins(bookFormat) {
        return this.margins.find(m => m.book_format === bookFormat) ?? null;
    }

    getAllMargins() {
        return this.margins;
    }

    calculatePrintableArea(bookFormat, width_mm, height_mm) {
        const margins = this.getMargins(bookFormat);
        if (!margins) return null;
        const inches = (mm) => fixed(mmToInches(mm), 2);

        return {
            format: bookFormat,
            totalSize: { mm: `${width_mm}×${height_mm}` },
            margins: {
                top: margins.top_mm,
                bottom: margins.bottom_mm,
                inner: margins.inner_mm,
                outer: margins.outer_mm
            },
            printableArea: {
                mm: `${width_mm - margins.inner_mm - margins.outer_mm}×${height_mm - margins.top_mm - margins.bottom_mm}`,
                inches: `${inches(width_mm - margins.inner_mm - margins.outer_mm)}"×${inches(height_mm - margins.top_mm - margins.bottom_mm)}"`
            }
        };
    }
}

// Usage:
// const bookMargins = new BookMarginManager([]);
// await bookMargins.load('../formats/book_margins.csv');
// const tradeMargins = bookMargins.getMargins('Trade Paperback');
// const printable = bookMargins.calculatePrintableArea('Trade Paperback', 152, 228);


// ============================================
// EXAMPLE 10: Screen Device Manager
// ============================================
class ScreenDeviceManager {
    static TYPE_CONFIG = [
        {list: 'monitors', idField: 'monitor_type', type: 'monitor'},
        {list: 'smartphones', idField: 'device_name', type: 'phone'},
        {list: 'tablets', idField: 'device_name', type: 'tablet'},
        {list: 'ereaders', idField: 'device_name', type: 'ereader'}
    ];

    constructor(devices = []) {
        this.monitors = [];
        this.smartphones = [];
        this.tablets = [];
        this.ereaders = [];
        this.deviceIndex = new Map();
    }

    async loadAllDevices(monitorPath, phonePath, tabletPath, ereaderPath) {
        await Promise.all([
            this.loadMonitors(monitorPath),
            this.loadSmartphones(phonePath),
            this.loadTablets(tabletPath),
            this.loadEReaders(ereaderPath)
        ]);
        this._buildDeviceIndex();
    }

    _buildDeviceIndex() {
        this.deviceIndex.clear();
        ScreenDeviceManager.TYPE_CONFIG.forEach(({list, idField, type}) => {
            this[list].forEach(d => {
                this.deviceIndex.set(d[idField], {device: d, type});
            });
        });
    }

    async loadMonitors(csvPath = '../devices/monitors.csv') {
        await this.loadDeviceType(csvPath, 'monitors');
    }

    async loadSmartphones(csvPath = '../devices/smartphones.csv') {
        await this.loadDeviceType(csvPath, 'smartphones');
    }

    async loadTablets(csvPath = '../devices/tablets.csv') {
        await this.loadDeviceType(csvPath, 'tablets');
    }

    async loadEReaders(csvPath = '../devices/ereaders.csv') {
        await this.loadDeviceType(csvPath, 'ereaders');
    }

    async loadDeviceType(csvPath, type) {
        try {
            this[type] = await fetchCSV(csvPath);
        } catch (error) {
            console.error(`Error loading ${type}:`, error);
        }
    }

    getDevice(deviceName, type = 'all') {
        const entry = this.deviceIndex.get(deviceName);
        if (!entry) return null;
        return type === 'all' || entry.type === type ? entry.device : null;
    }

    getDevicesBySize(minInches, maxInches, type = 'all') {
        const results = [];

        ScreenDeviceManager.TYPE_CONFIG.forEach(({list, type: t}) => {
            if (type !== 'all' && type !== t) return;
            this[list].forEach(d => {
                const size = d.screen_size_inches;
                if (size >= minInches && size <= maxInches) results.push(d);
            });
        });

        return results;
    }

    getPixelDensity(deviceName) {
        const device = this.getDevice(deviceName);
        return device ? device.ppi : null;
    }

    calculateDPI(screenSizeInches, resolutionWidth, resolutionHeight) {
        const diagonal = Math.sqrt(resolutionWidth ** 2 + resolutionHeight ** 2);
        return Math.round(diagonal / screenSizeInches);
    }
}

// Usage:
// const devices = new ScreenDeviceManager();
// await devices.loadAllDevices('../devices/monitors.csv', '../devices/smartphones.csv', '../devices/tablets.csv', '../devices/ereaders.csv');
// const iphone = devices.getDevice('iPhone 15 Pro Max');
// const tablets7to10 = devices.getDevicesBySize(7, 10, 'tablet');


// ============================================
// EXAMPLE 11: Specification Loaders (Refactored)
// ============================================
class SpecificationManager {
    static SPECS = {
        bindingStyles: { file: '../specs/binding_styles.csv', key: 'binding_style' },
        foldCompatibility: { file: '../specs/fold_compatibility.csv', key: 'source_format' },
        pixelDensity: { file: '../devices/pixel_density_guide.csv', key: 'device_type' },
        videoResolutions: { file: '../devices/video_resolutions.csv', key: 'resolution_name' },
        colorSpaces: { file: '../specs/color_spaces.csv', key: 'color_space' },
        fontSizes: { file: '../specs/minimum_font_sizes.csv', key: 'format' },
        wcagContrast: { file: '../specs/wcag_contrast.csv', key: 'element_type' },
        formatEquivalence: { file: '../specs/format_equivalence_matrix.csv', key: 'source_format' },
        regionalCompatibility: { file: '../specs/regional_compatibility.csv', key: 'region' },
        standardsReference: { file: '../specs/standards_reference.csv', key: 'format_name' },
        technicalSpecifications: { file: '../specs/technical_specifications.csv', key: 'format_name' },
        paperTypes: { file: '../specs/paper_types.csv', key: 'type_name' },
        paperTypeTranslations: { file: '../specs/paper_type_translations.csv', key: 'type_name' }
    };

    constructor() {
        this.specifications = new Map();
        this.indexes = new Map();
        this.cache = new Map();
    }

    async loadAllSpecifications(basePath = './') {
        const specs = Object.entries(SpecificationManager.SPECS);
        await Promise.all(specs.map(([name, {file}]) =>
            this._loadAndIndex(name, `${basePath}${file}`)
        ));
    }

    async _loadAndIndex(name, csvPath) {
        try {
            // Frozen, because get() hands the same rows to every caller.
            const data = Object.freeze((await fetchCSV(csvPath)).map(Object.freeze));

            this.specifications.set(name, data);
            this._buildIndex(name, data);
        } catch (error) {
            console.error(`Error loading ${name}:`, error);
            this.specifications.set(name, []);
        }
    }

    _buildIndex(name, data) {
        const {key} = SpecificationManager.SPECS[name];
        const index = new Map();
        data.forEach(item => index.set(item[key], item));
        this.indexes.set(name, index);
    }

    get(specType, key, query) {
        const cacheKey = query
            ? `${specType}:${Object.keys(query).sort().map(k => `${k}=${query[k]}`).join('&')}`
            : `${specType}:${key}`;
        if (this.cache.has(cacheKey)) return this.cache.get(cacheKey);

        let result;
        const data = this.specifications.get(specType) || [];

        if (query) {
            result = Object.freeze(data.filter(item =>
                Object.entries(query).every(([k, v]) => item[k] === v)
            ));
        } else {
            const index = this.indexes.get(specType);
            result = (index ? index.get(key) : data.find(item =>
                item[SpecificationManager.SPECS[specType].key] === key
            )) ?? null;
        }

        this.cache.set(cacheKey, result);
        return result;
    }

    getBindingStyle(styleName) { return this.get('bindingStyles', styleName); }
    getFoldCompatibility(format) { return this.get('foldCompatibility', null, {source_format: format}); }
    getPixelDensityGuide(type, useCase) { return this.get('pixelDensity', null, {device_type: type, use_case: useCase}); }
    getVideoResolution(name) { return this.get('videoResolutions', name); }
    getColorSpace(name) { return this.get('colorSpaces', name); }
    getMinimumFontSize(format, type, content) { return this.get('fontSizes', null, {format, device_type: type, content_type: content}); }
    getWCAGContrast(elementType, level = 'AA') {
        const element = this.get('wcagContrast', elementType);
        return element ? {element: elementType, level, ratio: element[`wcag_level_${level.toLowerCase()}`], fontSize: element.font_size_pt} : null;
    }
    getFormatEquivalent(source, target) { return this.get('formatEquivalence', null, {source_format: source, target_format: target}); }
    getRegionalFormats(region) { return this.get('regionalCompatibility', region); }
    getStandardReference(format) { return this.get('standardsReference', format); }
    getVideoResolutionsByCategory(category) { return this.get('videoResolutions', null, {category}); }
    getColorSpacesByUse(useCase) { return this.get('colorSpaces', null, {best_for: useCase}); }
    getPaperType(typeName) { return this.get('paperTypes', typeName); }
    getPaperTypesByCategory(category) { return this.get('paperTypes', null, {category}); }
    getAllPaperTypes() { return this.specifications.get('paperTypes') || []; }
    getPaperTypeTranslations(typeName) { return this.get('paperTypeTranslations', typeName); }
}

// Usage:
// const specs = new SpecificationManager();
// await specs.loadAllSpecifications('./');
// const binding = specs.getBindingStyle('Perfect Binding');
// const folds = specs.getFoldCompatibility('A4');
// const font = specs.getMinimumFontSize('A4', 'Print', 'Body Text');
// const wcag = specs.getWCAGContrast('Normal Text', 'AAA');


// ============================================
// One entry point: loads the catalogs and wires every manager
// ============================================
// const docs = await DocumentFormats.load();
// docs.converter.convert('Letter', 'A4'); docs.specs.getPaperType('Papel bond');
// Paths are the ones the page in app/ uses; pass `only` to skip what you don't need.
class DocumentFormats {
    static async load({only = ['specs', 'margins', 'devices']} = {}) {
        const {formats, lastModified} = await loadFormatCatalogs();
        const docs = new DocumentFormats(formats, lastModified);
        const loads = [];
        if (only.includes('specs')) loads.push(docs.specs.loadAllSpecifications());
        if (only.includes('margins')) loads.push(docs.margins.load());
        if (only.includes('devices')) loads.push(docs.devices.loadAllDevices());
        await Promise.all(loads);
        if (only.includes('margins')) docs.generator.setBookMargins(docs.margins.getAllMargins());
        if (only.includes('specs')) docs.validator.setPrintSpecs(docs.specs.specifications.get('technicalSpecifications') ?? []);
        return docs;
    }

    constructor(formats, lastModified) {
        this.formats = formats;
        this.lastModified = lastModified;
        this.searcher = new FormatSearcher(formats);
        this.converter = new FormatConverter(formats);
        this.generator = new DocumentGenerator(formats);
        this.validator = new FormatValidator(formats);
        this.statistics = new FormatStatistics(formats);
        this.batch = new FormatBatchProcessor(formats);
        this.specs = new SpecificationManager();
        this.margins = new BookMarginManager([]);
        this.devices = new ScreenDeviceManager();
    }
}

// Name of a paper type in a UI language. paper_types.csv carries Spanish
// (type_name) and English (type_name_en); the other 28 languages come from the
// row of paper_type_translations.csv for the same type_name. Anything missing
// or empty falls back to English, so a name is never blank.
const paperTypeName = (paperType, translations, lang) => {
    if (lang === 'es') return paperType.type_name;
    const translated = lang === 'en' ? null : translations?.[lang];
    return translated || paperType.type_name_en;
};

// Fills {name} placeholders: formatMessage('{count} found', {count: 3}) -> '3 found'.
// A placeholder with no value is left as it is, so a typo shows up on screen.
const formatMessage = (template, values = {}) =>
    String(template).replace(/\{(\w+)\}/g, (match, key) => (key in values ? values[key] : match));


// Export all classes for use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        escapeHtml,
        normalizeText,
        sizeSimilarity,
        MM_PER_INCH,
        mmToInches,
        inchesToMm,
        fixed,
        fetchCSV,
        indexByName,
        closestNames,
        UnknownFormatError,
        DocumentFormats,
        paperTypeName,
        formatMessage,
        FORMAT_CATALOG_FILES,
        COUNTRIES_FILE,
        loadFormatCatalogs,
        splitCSVLine,
        parseCSV,
        FormatLoader,
        FormatSearcher,
        FormatConverter,
        DocumentGenerator,
        FormatValidator,
        FormatStatistics,
        FormatBatchProcessor,
        LocalizationManager,
        BookMarginManager,
        ScreenDeviceManager,
        SpecificationManager
    };
}

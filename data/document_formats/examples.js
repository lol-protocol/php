/**
 * Document Formats Database - JavaScript Examples
 * Complete examples of how to use the format data programmatically
 */

// ============================================
// UTILITIES: Optimization Helpers
// ============================================

// Memoization decorator for expensive calculations
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

// CSV Parser - shared across all managers
const parseCSV = (text) => {
    const lines = text.trim().split('\n');
    const headers = lines[0].split(',').map(h => h.trim());
    return lines.slice(1).map(line => {
        const values = line.split(',');
        const obj = {};
        headers.forEach((header, i) => {
            const value = values[i] ? values[i].trim() : '';
            obj[header] = isNaN(value) || value === '' ? value : parseFloat(value);
        });
        return obj;
    });
};

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
            const response = await fetch(this.csvUrl);
            const text = await response.text();
            this.formats = parseCSV(text);
            console.log(`Loaded ${this.formats.length} formats`);
            return this.formats;
        } catch (error) {
            console.error('Error loading formats:', error);
        }
    }

}

// Usage:
// const loader = new FormatLoader('all_formats_master.csv');
// const formats = await loader.load();


// ============================================
// EXAMPLE 2: Format Searcher
// ============================================
class FormatSearcher {
    constructor(formats) {
        this.formats = formats;
        this.formatMap = new Map(formats.map((f, i) => [f.format_name, i]));
        this.index = this.buildIndex();
        this.countryIndex = groupBy(formats, f => f.country);
        this.categoryIndex = groupBy(formats, f => f.category);
        this.dimensionCache = new Map();
    }

    buildIndex() {
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
            .toLowerCase()
            .split(/\s+/);

            words.forEach(word => {
                if (!index[word]) index[word] = [];
                index[word].push(idx);
            });
        });
        return index;
    }

    search(query) {
        const terms = query.toLowerCase().split(/\s+/);
        const results = new Map();

        terms.forEach(term => {
            (this.index[term] || []).forEach(idx => {
                results.set(idx, (results.get(idx) || 0) + 1);
            });
        });

        return Array.from(results.entries())
            .sort((a, b) => b[1] - a[1])
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
        this.formatMap = new Map(formats.map((f, i) => [f.format_name, i]));
        this.calculateSimilarity = memoize(
            (f1Name, f2Name) => this._calcSimilarity(f1Name, f2Name),
            (f1, f2) => [f1, f2].sort().join('|')
        );
    }

    _calcSimilarity(f1Name, f2Name) {
        const f1 = this.formats[this.formatMap.get(f1Name)];
        const f2 = this.formats[this.formatMap.get(f2Name)];
        if (!f1 || !f2) return 0;

        const w1 = f1.width_mm || 0;
        const h1 = f1.height_mm || 0;
        const w2 = f2.width_mm || 0;
        const h2 = f2.height_mm || 0;

        if (w1 === 0 || h1 === 0 || w2 === 0 || h2 === 0) return 0;

        const widthDiff = Math.abs(w1 - w2) / Math.max(w1, w2) * 100;
        const heightDiff = Math.abs(h1 - h2) / Math.max(h1, h2) * 100;
        return Math.round(100 - ((widthDiff + heightDiff) / 2));
    }

    findEquivalents(formatName, threshold = 90) {
        const idx = this.formatMap.get(formatName);
        if (idx === undefined) return [];

        const candidates = [];
        for (let i = 0; i < this.formats.length; i++) {
            if (i !== idx && this.formats[i].width_mm && this.formats[i].height_mm) {
                const sim = this.calculateSimilarity(formatName, this.formats[i].format_name);
                if (sim >= threshold) candidates.push({format: this.formats[i], similarity: sim});
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
        const fromIdx = this.formatMap.get(fromName);
        const toIdx = this.formatMap.get(toName);
        if (fromIdx === undefined || toIdx === undefined) return null;

        const from = this.formats[fromIdx];
        const to = this.formats[toIdx];

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
                width: widthDiff.toFixed(1),
                height: heightDiff.toFixed(1)
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
        this.formatMap = new Map(formats.map((f, i) => [f.format_name, i]));
    }

    createTemplate(formatName, content = '') {
        const idx = this.formatMap.get(formatName);
        if (idx === undefined) throw new Error(`Format ${formatName} not found`);
        const format = this.formats[idx];

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

    getRecommendedMargins(format) {
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
        const idx = this.formatMap.get(formatName);
        if (idx === undefined) throw new Error(`Format ${formatName} not found`);
        const format = this.formats[idx];

        const widthCm = (parseFloat(format.width_mm) / 10).toFixed(1);
        const heightCm = (parseFloat(format.height_mm) / 10).toFixed(1);

        return `
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>${title || formatName}</title>
    <style>
        @page {
            size: ${widthCm}cm ${heightCm}cm;
            margin: 2cm;
        }
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <h1>${title}</h1>
    ${body}
</body>
</html>
        `;
    }

    generatePrintCSS(formatName) {
        const idx = this.formatMap.get(formatName);
        if (idx === undefined) throw new Error(`Format ${formatName} not found`);
        const format = this.formats[idx];

        const widthCm = (parseFloat(format.width_mm) / 10).toFixed(1);
        const heightCm = (parseFloat(format.height_mm) / 10).toFixed(1);

        return `
@media print {
    @page {
        size: ${widthCm}cm ${heightCm}cm;
        margin: 2cm;
    }

    body {
        margin: 0;
        padding: 2cm;
        width: ${widthCm - 4}cm;
        height: ${heightCm - 4}cm;
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
        this.formatMap = new Map(formats.map((f, i) => [f.format_name, i]));
        this.validateDimensions = memoize(
            (w, h, u) => this._validateDimensions(w, h, u),
            (w, h, u) => `${w}|${h}|${u}`
        );
    }

    _validateDimensions(width, height, unit = 'mm') {
        const conversions = { mm: 1, cm: 10, inch: 25.4 };
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

    validateForPrinting(formatName, dpi = 300) {
        const idx = this.formatMap.get(formatName);
        if (idx === undefined) return { valid: false, message: 'Format not found' };
        const format = this.formats[idx];

        const minDpi = 150;
        const recommendedDpi = 300;

        return {
            valid: dpi >= minDpi,
            recommended: dpi >= recommendedDpi,
            message: dpi >= recommendedDpi
                ? 'Excellent DPI for printing'
                : dpi >= minDpi
                ? 'Acceptable for printing'
                : 'DPI too low for quality printing'
        };
    }

    getCompatibility(format1Name, format2Name) {
        const idx1 = this.formatMap.get(format1Name);
        const idx2 = this.formatMap.get(format2Name);
        if (idx1 === undefined || idx2 === undefined) return { compatible: false };

        const f1 = this.formats[idx1];
        const f2 = this.formats[idx2];

        const w1 = f1.width_mm || 0;
        const h1 = f1.height_mm || 0;
        const w2 = f2.width_mm || 0;
        const h2 = f2.height_mm || 0;

        const widthDiff = Math.abs(w1 - w2) / Math.max(w1, w2) * 100;
        const heightDiff = Math.abs(h1 - h2) / Math.max(h1, h2) * 100;
        const compatibility = 100 - ((widthDiff + heightDiff) / 2);

        return {
            compatible: compatibility > 90,
            compatibility: compatibility.toFixed(1),
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
            averageWidth: (sumWidth / this.formats.length).toFixed(1),
            averageHeight: (sumHeight / this.formats.length).toFixed(1),
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
        this.formatMap = new Map(formats.map((f, i) => [f.format_name, i]));
    }

    processMultiple(formatNames, callback) {
        return formatNames
            .map(name => {
                const idx = this.formatMap.get(name);
                return idx !== undefined ? this.formats[idx] : null;
            })
            .filter(Boolean)
            .map(callback);
    }

    exportAsJSON(filters = {}) {
        let data = this.formats;

        if (filters.country) {
            data = data.filter(f => f.country === filters.country);
        }
        if (filters.category) {
            data = data.filter(f => f.category === filters.category);
        }

        return JSON.stringify(data, null, 2);
    }

    exportAsCSV(filters = {}) {
        let data = this.formats;

        if (filters.country) {
            data = data.filter(f => f.country === filters.country);
        }
        if (filters.category) {
            data = data.filter(f => f.category === filters.category);
        }

        if (data.length === 0) return '';

        const headers = Object.keys(data[0]);
        const csv = [headers.join(',')];

        data.forEach(row => {
            const values = headers.map(h => {
                const value = row[h];
                return typeof value === 'string' && value.includes(',')
                    ? `"${value}"`
                    : value;
            });
            csv.push(values.join(','));
        });

        return csv.join('\n');
    }

    exportAsHTML(filters = {}) {
        let data = this.formats;

        if (filters.country) {
            data = data.filter(f => f.country === filters.country);
        }
        if (filters.category) {
            data = data.filter(f => f.category === filters.category);
        }

        let html = '<table border="1"><thead><tr>';

        if (data.length > 0) {
            Object.keys(data[0]).forEach(key => {
                html += `<th>${key}</th>`;
            });
            html += '</tr></thead><tbody>';

            data.forEach(row => {
                html += '<tr>';
                Object.values(row).forEach(value => {
                    html += `<td>${value}</td>`;
                });
                html += '</tr>';
            });
        }

        html += '</tbody></table>';
        return html;
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

    constructor(translationsPath = 'translations') {
        this.translationsPath = translationsPath;
        this.currentLanguage = localStorage.getItem('preferredLanguage') || 'en';
        this.translations = {};
        this.supportedLanguagesSet = new Set(Object.keys(LocalizationManager.LANGUAGE_NAMES));
        this.listeners = [];
    }

    async init() {
        await this.loadLanguage(this.currentLanguage);
    }

    async loadLanguage(langCode) {
        if (!this.supportedLanguagesSet.has(langCode)) {
            console.warn(`Language ${langCode} not supported, falling back to English`);
            langCode = 'en';
        }

        try {
            const response = await fetch(`${this.translationsPath}/${langCode}.json`);
            if (!response.ok) throw new Error(`Failed to load ${langCode}.json`);
            this.translations = await response.json();
            this.currentLanguage = langCode;
            localStorage.setItem('preferredLanguage', langCode);
            this.notifyListeners();
        } catch (error) {
            console.error(`Error loading language ${langCode}:`, error);
            if (langCode !== 'en') {
                await this.loadLanguage('en');
            }
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

    translate(key, defaultValue = key) {
        const keys = key.split('.');
        let value = this.translations;

        for (const k of keys) {
            if (typeof value === 'object' && value !== null && k in value) {
                value = value[k];
            } else {
                return defaultValue;
            }
        }

        return typeof value === 'string' ? value : defaultValue;
    }

    t(key, defaultValue = key) {
        return this.translate(key, defaultValue);
    }

    translateElement(element) {
        const translateAttr = element.getAttribute('data-translate');
        if (translateAttr) {
            const parts = translateAttr.split('|');
            parts.forEach(part => {
                const [key, attr] = part.split(':');
                const translated = this.translate(key.trim());
                if (attr) {
                    element.setAttribute(attr.trim(), translated);
                } else {
                    element.textContent = translated;
                }
            });
        }

        element.querySelectorAll('[data-translate]').forEach(el => this.translateElement(el));
    }

    translatePage() {
        if (typeof requestAnimationFrame === 'undefined') {
            this.translateElement(document.documentElement);
            return;
        }

        requestAnimationFrame(() => {
            const elements = document.querySelectorAll('[data-translate]');
            const updates = [];

            elements.forEach(el => {
                const attr = el.getAttribute('data-translate');
                if (!attr) return;
                const translations = {};
                attr.split('|').forEach(part => {
                    const [key, targetAttr] = part.split(':');
                    translations[targetAttr || 'text'] = this.translate(key.trim());
                });
                updates.push({element: el, translations});
            });

            updates.forEach(({element, translations}) => {
                Object.entries(translations).forEach(([attr, value]) => {
                    if (attr === 'text') {
                        element.textContent = value;
                    } else {
                        element.setAttribute(attr, value);
                    }
                });
            });
        });
    }

    subscribe(callback) {
        this.listeners.push(callback);
        return () => {
            this.listeners = this.listeners.filter(l => l !== callback);
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

    async load(csvPath = 'book_margins.csv') {
        try {
            const response = await fetch(csvPath);
            const text = await response.text();
            this.margins = parseCSV(text);
        } catch (error) {
            console.error('Error loading book margins:', error);
        }
    }

    getMargins(bookFormat) {
        return this.margins.find(m => m.book_format === bookFormat);
    }

    getAllMargins() {
        return this.margins;
    }

    calculatePrintableArea(bookFormat, width_mm, height_mm) {
        const margins = this.getMargins(bookFormat);
        if (!margins) return null;

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
                inches: `${((width_mm - margins.inner_mm - margins.outer_mm) / 25.4).toFixed(2)}"×${((height_mm - margins.top_mm - margins.bottom_mm) / 25.4).toFixed(2)}"`
            }
        };
    }
}

// Usage:
// const bookMargins = new BookMarginManager([]);
// await bookMargins.load('book_margins.csv');
// const tradeMargins = bookMargins.getMargins('Trade Paperback');
// const printable = bookMargins.calculatePrintableArea('Trade Paperback', 152, 228);


// ============================================
// EXAMPLE 10: Screen Device Manager
// ============================================
class ScreenDeviceManager {
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
        this.monitors.forEach(d => this.deviceIndex.set(d.monitor_type, d));
        this.smartphones.forEach(d => this.deviceIndex.set(d.device_name, d));
        this.tablets.forEach(d => this.deviceIndex.set(d.device_name, d));
        this.ereaders.forEach(d => this.deviceIndex.set(d.device_name, d));
    }

    async loadMonitors(csvPath = 'monitors.csv') {
        await this.loadDeviceType(csvPath, 'monitors');
    }

    async loadSmartphones(csvPath = 'smartphones.csv') {
        await this.loadDeviceType(csvPath, 'smartphones');
    }

    async loadTablets(csvPath = 'tablets.csv') {
        await this.loadDeviceType(csvPath, 'tablets');
    }

    async loadEReaders(csvPath = 'ereaders.csv') {
        await this.loadDeviceType(csvPath, 'ereaders');
    }

    async loadDeviceType(csvPath, type) {
        try {
            const response = await fetch(csvPath);
            const text = await response.text();
            const lines = text.trim().split('\n');
            const headers = lines[0].split(',').map(h => h.trim());

            const devices = lines.slice(1).map(line => {
                const values = line.split(',');
                const obj = {};
                headers.forEach((header, i) => {
                    const value = values[i] ? values[i].trim() : '';
                    obj[header] = isNaN(value) || value === '' ? value : parseFloat(value);
                });
                return obj;
            });

            this[type] = devices;
        } catch (error) {
            console.error(`Error loading ${type}:`, error);
        }
    }

    getDevice(deviceName, type = 'all') {
        if (type === 'all') {
            return this.deviceIndex.get(deviceName);
        } else if (type === 'monitor') {
            return this.monitors.find(d => d.monitor_type === deviceName);
        } else if (type === 'phone') {
            return this.smartphones.find(d => d.device_name === deviceName);
        } else if (type === 'tablet') {
            return this.tablets.find(d => d.device_name === deviceName);
        } else if (type === 'ereader') {
            return this.ereaders.find(d => d.device_name === deviceName);
        }
    }

    getDevicesBySize(minInches, maxInches, type = 'all') {
        const types = type === 'all' ? ['monitors', 'smartphones', 'tablets', 'ereaders'] : [type];
        const results = [];

        types.forEach(t => {
            const devices = t === 'monitors' ? this.monitors :
                           t === 'smartphones' ? this.smartphones :
                           t === 'tablets' ? this.tablets :
                           this.ereaders;

            results.push(...devices.filter(d => {
                const size = parseFloat(d.screen_size_inches || d.size_inches);
                return size >= minInches && size <= maxInches;
            }));
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
// await devices.loadAllDevices('monitors.csv', 'smartphones.csv', 'tablets.csv', 'ereaders.csv');
// const iphone = devices.getDevice('iPhone 15 Pro Max');
// const tablets7to10 = devices.getDevicesBySize(7, 10, 'tablet');


// ============================================
// EXAMPLE 11: Specification Loaders (Refactored)
// ============================================
class SpecificationManager {
    static SPECS = {
        bindingStyles: { file: 'binding_styles.csv', key: 'binding_style' },
        foldCompatibility: { file: 'fold_compatibility.csv', key: 'source_format' },
        pixelDensity: { file: 'pixel_density_guide.csv', key: 'device_type' },
        videoResolutions: { file: 'video_resolutions.csv', key: 'resolution_name' },
        colorSpaces: { file: 'color_spaces.csv', key: 'color_space' },
        fontSizes: { file: 'minimum_font_sizes.csv', key: 'format' },
        wcagContrast: { file: 'wcag_contrast.csv', key: 'element_type' },
        formatEquivalence: { file: 'format_equivalence_matrix.csv', key: 'source_format' },
        regionalCompatibility: { file: 'regional_compatibility.csv', key: 'region' },
        standardsReference: { file: 'standards_reference.csv', key: 'format_name' }
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
            const response = await fetch(csvPath);
            const text = await response.text();
            const data = parseCSV(text);

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
            result = data.filter(item =>
                Object.entries(query).every(([k, v]) => item[k] === v)
            );
        } else {
            const index = this.indexes.get(specType);
            result = index ? index.get(key) : data.find(item =>
                item[SpecificationManager.SPECS[specType].key] === key
            );
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
}

// Usage:
// const specs = new SpecificationManager();
// await specs.loadAllSpecifications('./');
// const binding = specs.getBindingStyle('Perfect Binding');
// const folds = specs.getFoldCompatibility('A4');
// const font = specs.getMinimumFontSize('A4', 'Print', 'Body Text');
// const wcag = specs.getWCAGContrast('Normal Text', 'AAA');


// Export all classes for use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
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

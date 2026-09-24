/**
 * Document Formats Database - JavaScript Examples
 * Complete examples of how to use the format data programmatically
 */

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
            this.formats = this.parseCSV(text);
            console.log(`Loaded ${this.formats.length} formats`);
            return this.formats;
        } catch (error) {
            console.error('Error loading formats:', error);
        }
    }

    parseCSV(text) {
        const lines = text.trim().split('\n');
        const headers = lines[0].split(',').map(h => h.trim());

        return lines.slice(1).map(line => {
            const values = line.split(',').map(v => v.trim());
            const obj = {};
            headers.forEach((header, i) => {
                obj[header] = values[i] === 'N/A' ? null : values[i];
            });
            return obj;
        });
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
        this.index = this.buildIndex();
    }

    buildIndex() {
        const index = {};
        this.formats.forEach(format => {
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
                if (!index[word]) {
                    index[word] = [];
                }
                index[word].push(format);
            });
        });
        return index;
    }

    search(query) {
        const terms = query.toLowerCase().split(/\s+/);
        const results = new Map();

        terms.forEach(term => {
            (this.index[term] || []).forEach(format => {
                const key = format.format_name;
                results.set(key, (results.get(key) || 0) + 1);
            });
        });

        return Array.from(results.entries())
            .sort((a, b) => b[1] - a[1])
            .map(([name]) => this.formats.find(f => f.format_name === name))
            .filter(Boolean);
    }

    byCountry(country) {
        return this.formats.filter(f => f.country === country);
    }

    byCategory(category) {
        return this.formats.filter(f => f.category === category);
    }

    byDimension(minWidth, maxWidth, minHeight, maxHeight) {
        return this.formats.filter(format => {
            const w = parseFloat(format.width_mm);
            const h = parseFloat(format.height_mm);
            return w >= minWidth && w <= maxWidth && h >= minHeight && h <= maxHeight;
        });
    }
}

// Usage:
// const searcher = new FormatSearcher(formats);
// const a4Formats = searcher.search('A4');
// const usaFormats = searcher.byCountry('USA');
// const photoFormats = searcher.byCategory('Photo');


// ============================================
// EXAMPLE 3: Format Converter
// ============================================
class FormatConverter {
    constructor(formats, conversionsUrl) {
        this.formats = formats;
        this.conversions = new Map();
        this.loadConversions(conversionsUrl);
    }

    async loadConversions(conversionsUrl) {
        try {
            const response = await fetch(conversionsUrl);
            const text = await response.text();
            const lines = text.trim().split('\n').slice(1);

            lines.forEach(line => {
                const [from, toCountry, to, , equivalence] = line.split(',').map(v => v.trim());
                const key = `${from}→${to}`;
                this.conversions.set(key, {
                    from,
                    to,
                    equivalence: parseFloat(equivalence),
                    toCountry
                });
            });
        } catch (error) {
            console.error('Error loading conversions:', error);
        }
    }

    findEquivalents(formatName, threshold = 95) {
        return this.formats.filter(format => {
            const from = this.formats.find(f => f.format_name === formatName);
            if (!from) return false;

            const w1 = parseFloat(from.width_mm);
            const h1 = parseFloat(from.height_mm);
            const w2 = parseFloat(format.width_mm);
            const h2 = parseFloat(format.height_mm);

            const widthSim = Math.abs(w1 - w2) / w1 * 100;
            const heightSim = Math.abs(h1 - h2) / h1 * 100;
            const similarity = 100 - ((widthSim + heightSim) / 2);

            return similarity >= threshold && format.format_name !== formatName;
        });
    }

    convert(fromName, toName) {
        const from = this.formats.find(f => f.format_name === fromName);
        const to = this.formats.find(f => f.format_name === toName);

        if (!from || !to) return null;

        const widthDiff = Math.abs(from.width_mm - to.width_mm);
        const heightDiff = Math.abs(from.height_mm - to.height_mm);

        return {
            from: fromName,
            to: toName,
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
            },
            similarity: this.calculateSimilarity(from, to)
        };
    }

    calculateSimilarity(format1, format2) {
        const w1 = parseFloat(format1.width_mm);
        const h1 = parseFloat(format1.height_mm);
        const w2 = parseFloat(format2.width_mm);
        const h2 = parseFloat(format2.height_mm);

        const widthDiff = Math.abs(w1 - w2) / Math.max(w1, w2) * 100;
        const heightDiff = Math.abs(h1 - h2) / Math.max(h1, h2) * 100;

        return Math.round(100 - ((widthDiff + heightDiff) / 2));
    }
}

// Usage:
// const converter = new FormatConverter(formats, 'format_conversions.csv');
// const conversion = converter.convert('Letter', 'A4');
// const equivalents = converter.findEquivalents('A4');


// ============================================
// EXAMPLE 4: Document Generator
// ============================================
class DocumentGenerator {
    constructor(formats) {
        this.formats = formats;
    }

    createTemplate(formatName, content = '') {
        const format = this.formats.find(f => f.format_name === formatName);
        if (!format) throw new Error(`Format ${formatName} not found`);

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
        const format = this.formats.find(f => f.format_name === formatName);
        if (!format) throw new Error(`Format ${formatName} not found`);

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
        const format = this.formats.find(f => f.format_name === formatName);
        if (!format) throw new Error(`Format ${formatName} not found`);

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
    }

    validateDimensions(width, height, unit = 'mm') {
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
        const format = this.formats.find(f => f.format_name === formatName);
        if (!format) return { valid: false, message: 'Format not found' };

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
        const f1 = this.formats.find(f => f.format_name === format1Name);
        const f2 = this.formats.find(f => f.format_name === format2Name);

        if (!f1 || !f2) return { compatible: false };

        const w1 = parseFloat(f1.width_mm);
        const h1 = parseFloat(f1.height_mm);
        const w2 = parseFloat(f2.width_mm);
        const h2 = parseFloat(f2.height_mm);

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
    }

    getStats() {
        const countries = new Set();
        const categories = new Set();
        const types = new Set();

        this.formats.forEach(f => {
            countries.add(f.country);
            categories.add(f.category);
            types.add(f.type || 'Unknown');
        });

        return {
            totalFormats: this.formats.length,
            totalCountries: countries.size,
            totalCategories: categories.size,
            totalTypes: types.size,
            averageWidth: this.getAverageWidth(),
            averageHeight: this.getAverageHeight(),
            largestFormat: this.getLargestFormat(),
            smallestFormat: this.getSmallestFormat()
        };
    }

    getAverageWidth() {
        const sum = this.formats.reduce((acc, f) => acc + parseFloat(f.width_mm), 0);
        return (sum / this.formats.length).toFixed(1);
    }

    getAverageHeight() {
        const sum = this.formats.reduce((acc, f) => acc + parseFloat(f.height_mm), 0);
        return (sum / this.formats.length).toFixed(1);
    }

    getLargestFormat() {
        return this.formats.reduce((max, f) => {
            const area1 = parseFloat(max.width_mm) * parseFloat(max.height_mm);
            const area2 = parseFloat(f.width_mm) * parseFloat(f.height_mm);
            return area2 > area1 ? f : max;
        });
    }

    getSmallestFormat() {
        return this.formats.reduce((min, f) => {
            const area1 = parseFloat(min.width_mm) * parseFloat(min.height_mm);
            const area2 = parseFloat(f.width_mm) * parseFloat(f.height_mm);
            return area2 < area1 ? f : min;
        });
    }

    getFormatsByCategory() {
        const byCategory = {};
        this.formats.forEach(f => {
            if (!byCategory[f.category]) {
                byCategory[f.category] = [];
            }
            byCategory[f.category].push(f);
        });
        return byCategory;
    }

    getFormatsByCountry() {
        const byCountry = {};
        this.formats.forEach(f => {
            if (!byCountry[f.country]) {
                byCountry[f.country] = [];
            }
            byCountry[f.country].push(f);
        });
        return byCountry;
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
    }

    processMultiple(formatNames, callback) {
        return formatNames
            .map(name => this.formats.find(f => f.format_name === name))
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


// Export all classes for use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        FormatLoader,
        FormatSearcher,
        FormatConverter,
        DocumentGenerator,
        FormatValidator,
        FormatStatistics,
        FormatBatchProcessor
    };
}

# Frontend Implementation Guide

Complete guide for integrating document formats into frontend applications with HTML, CSS, and JavaScript.

## Table of Contents

1. [Quick Start](#quick-start)
2. [HTML Components](#html-components)
3. [JavaScript Integration](#javascript-integration)
4. [CSS Styling](#css-styling)
5. [Interactive Examples](#interactive-examples)
6. [API Integration](#api-integration)

## Quick Start

### Basic Setup

```html
<!DOCTYPE html>
<html>
<head>
    <title>Document Format Selector</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div id="format-selector"></div>
    <script src="formats-data.js"></script>
    <script src="format-selector.js"></script>
</body>
</html>
```

## HTML Components

### 1. Format Selector Component

```html
<div class="format-selector">
    <div class="selector-controls">
        <input type="text" id="search" placeholder="Search formats...">
        <select id="country-filter">
            <option value="">All Countries</option>
            <option value="US">United States</option>
            <option value="MX">Mexico</option>
            <!-- More countries -->
        </select>
        <select id="category-filter">
            <option value="">All Categories</option>
            <option value="Standard">Standard</option>
            <option value="Book">Book</option>
            <!-- More categories -->
        </select>
    </div>
    
    <div id="results" class="format-results"></div>
</div>
```

### 2. Format Card Component

```html
<div class="format-card">
    <div class="format-header">
        <h3 class="format-name">A4</h3>
        <span class="format-badge">Standard</span>
    </div>
    
    <div class="format-details">
        <div class="detail-row">
            <span class="label">Dimensions (mm):</span>
            <span class="value">210 × 297</span>
        </div>
        <div class="detail-row">
            <span class="label">Dimensions (in):</span>
            <span class="value">8.3 × 11.7</span>
        </div>
        <div class="detail-row">
            <span class="label">Aspect Ratio:</span>
            <span class="value">1:1.414</span>
        </div>
        <div class="detail-row">
            <span class="label">Country:</span>
            <span class="value">International</span>
        </div>
        <div class="detail-row">
            <span class="label">Common Use:</span>
            <span class="value">Office documents, letters, reports</span>
        </div>
    </div>
    
    <div class="format-preview">
        <svg viewBox="0 0 210 297" class="format-visual">
            <rect x="10" y="10" width="190" height="277" 
                  fill="white" stroke="#333" stroke-width="2"/>
            <text x="105" y="160" text-anchor="middle" font-size="14">
                210 × 297 mm
            </text>
        </svg>
    </div>
    
    <div class="format-actions">
        <button class="btn-select">Select Format</button>
        <button class="btn-info">More Info</button>
    </div>
</div>
```

### 3. Format Comparison Component

```html
<div class="format-comparison">
    <div class="comparison-header">
        <h2>Format Comparison</h2>
    </div>
    
    <table class="comparison-table">
        <thead>
            <tr>
                <th>Format</th>
                <th>Width (mm)</th>
                <th>Height (mm)</th>
                <th>Aspect Ratio</th>
                <th>Country</th>
            </tr>
        </thead>
        <tbody id="comparison-body">
            <!-- Filled by JavaScript -->
        </tbody>
    </table>
</div>
```

### 4. Converter Component

```html
<div class="format-converter">
    <div class="converter-section">
        <h3>Format Converter</h3>
        
        <div class="converter-group">
            <label>From Format:</label>
            <select id="from-format">
                <option value="">Select format...</option>
            </select>
        </div>
        
        <div class="converter-group">
            <label>To Format:</label>
            <select id="to-format">
                <option value="">Select format...</option>
            </select>
        </div>
        
        <div class="conversion-result">
            <div id="conversion-info"></div>
            <div class="equivalence-bar">
                <div class="equivalence-fill" id="equivalence-fill"></div>
            </div>
        </div>
    </div>
</div>
```

## JavaScript Integration

### 1. Format Data Manager

```javascript
class FormatDataManager {
    constructor() {
        this.formats = [];
        this.conversions = {};
        this.countries = new Map();
        this.categories = new Map();
    }
    
    async loadFormats(csvUrl) {
        const response = await fetch(csvUrl);
        const text = await response.text();
        this.formats = this.parseCSV(text);
        this.indexFormats();
    }
    
    parseCSV(text) {
        const lines = text.trim().split('\n');
        const headers = lines[0].split(',');
        return lines.slice(1).map(line => {
            const values = line.split(',');
            const obj = {};
            headers.forEach((header, i) => {
                obj[header.trim()] = values[i].trim();
            });
            return obj;
        });
    }
    
    indexFormats() {
        this.formats.forEach(format => {
            const country = format.country;
            const category = format.category;
            
            if (!this.countries.has(country)) {
                this.countries.set(country, []);
            }
            this.countries.get(country).push(format);
            
            if (!this.categories.has(category)) {
                this.categories.set(category, []);
            }
            this.categories.get(category).push(format);
        });
    }
    
    searchFormats(query) {
        return this.formats.filter(format => 
            format.format_name.toLowerCase().includes(query.toLowerCase()) ||
            format.country.toLowerCase().includes(query.toLowerCase()) ||
            format.description.toLowerCase().includes(query.toLowerCase())
        );
    }
    
    getFormatsByCountry(country) {
        return this.countries.get(country) || [];
    }
    
    getFormatsByCategory(category) {
        return this.categories.get(category) || [];
    }
    
    getConversions(fromFormatId) {
        return this.conversions[fromFormatId] || [];
    }
}
```

### 2. Format Selector Component

```javascript
class FormatSelector {
    constructor(containerId, dataManager) {
        this.container = document.getElementById(containerId);
        this.dataManager = dataManager;
        this.selectedFormats = [];
        this.init();
    }
    
    init() {
        this.createControls();
        this.attachEventListeners();
        this.renderFormats(this.dataManager.formats);
    }
    
    createControls() {
        const controls = document.createElement('div');
        controls.className = 'format-controls';
        
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.id = 'format-search';
        searchInput.placeholder = 'Search formats...';
        
        const countrySelect = document.createElement('select');
        countrySelect.id = 'country-select';
        countrySelect.innerHTML = '<option value="">All Countries</option>';
        
        this.dataManager.countries.forEach((_, country) => {
            const option = document.createElement('option');
            option.value = country;
            option.textContent = country;
            countrySelect.appendChild(option);
        });
        
        const categorySelect = document.createElement('select');
        categorySelect.id = 'category-select';
        categorySelect.innerHTML = '<option value="">All Categories</option>';
        
        this.dataManager.categories.forEach((_, category) => {
            const option = document.createElement('option');
            option.value = category;
            option.textContent = category;
            categorySelect.appendChild(option);
        });
        
        controls.appendChild(searchInput);
        controls.appendChild(countrySelect);
        controls.appendChild(categorySelect);
        
        this.container.appendChild(controls);
    }
    
    attachEventListeners() {
        document.getElementById('format-search').addEventListener('input', 
            (e) => this.handleSearch(e.target.value));
        
        document.getElementById('country-select').addEventListener('change',
            (e) => this.handleCountryFilter(e.target.value));
        
        document.getElementById('category-select').addEventListener('change',
            (e) => this.handleCategoryFilter(e.target.value));
    }
    
    handleSearch(query) {
        if (query.length === 0) {
            this.renderFormats(this.dataManager.formats);
        } else {
            const results = this.dataManager.searchFormats(query);
            this.renderFormats(results);
        }
    }
    
    handleCountryFilter(country) {
        if (country === '') {
            this.renderFormats(this.dataManager.formats);
        } else {
            const formats = this.dataManager.getFormatsByCountry(country);
            this.renderFormats(formats);
        }
    }
    
    handleCategoryFilter(category) {
        if (category === '') {
            this.renderFormats(this.dataManager.formats);
        } else {
            const formats = this.dataManager.getFormatsByCategory(category);
            this.renderFormats(formats);
        }
    }
    
    renderFormats(formats) {
        let resultsContainer = this.container.querySelector('.format-results');
        if (!resultsContainer) {
            resultsContainer = document.createElement('div');
            resultsContainer.className = 'format-results';
            this.container.appendChild(resultsContainer);
        }
        
        resultsContainer.innerHTML = '';
        
        formats.forEach(format => {
            const card = this.createFormatCard(format);
            resultsContainer.appendChild(card);
        });
    }
    
    createFormatCard(format) {
        const card = document.createElement('div');
        card.className = 'format-card';
        
        card.innerHTML = `
            <div class="format-header">
                <h3>${format.format_name}</h3>
                <span class="badge">${format.category}</span>
            </div>
            <div class="format-body">
                <div class="detail"><strong>Country:</strong> ${format.country}</div>
                <div class="detail"><strong>Size (mm):</strong> ${format.width_mm} × ${format.height_mm}</div>
                <div class="detail"><strong>Size (in):</strong> ${format.width_inches}" × ${format.height_inches}"</div>
                <div class="detail"><strong>Aspect Ratio:</strong> ${format.aspect_ratio}</div>
                <div class="detail"><strong>Use:</strong> ${format.common_use || 'N/A'}</div>
            </div>
            <div class="format-preview">
                ${this.generatePreviewSVG(format)}
            </div>
            <button class="btn-select" data-format="${format.format_name}">Select</button>
        `;
        
        card.querySelector('.btn-select').addEventListener('click', () => {
            this.selectFormat(format);
        });
        
        return card;
    }
    
    generatePreviewSVG(format) {
        const scale = 0.5;
        const width = parseFloat(format.width_mm) * scale;
        const height = parseFloat(format.height_mm) * scale;
        
        return `
            <svg viewBox="0 0 ${width} ${height}" class="format-svg">
                <rect x="1" y="1" width="${width-2}" height="${height-2}" 
                      fill="white" stroke="#999" stroke-width="1"/>
                <text x="${width/2}" y="${height/2}" text-anchor="middle" 
                      font-size="8" fill="#666">
                    ${format.format_name}
                </text>
            </svg>
        `;
    }
    
    selectFormat(format) {
        this.selectedFormats.push(format);
        alert(`Selected: ${format.format_name}`);
        console.log('Selected formats:', this.selectedFormats);
    }
}
```

### 3. Format Converter

```javascript
class FormatConverter {
    constructor(dataManager) {
        this.dataManager = dataManager;
        this.conversions = new Map();
        this.loadConversions();
    }
    
    loadConversions() {
        // This would load from format_conversions.csv
        this.conversions.set('Letter-A4', {
            equivalence: 95,
            level: 'Very Similar',
            notes: 'Nearly equivalent size'
        });
    }
    
    convert(fromFormat, toFormat) {
        const key = `${fromFormat}-${toFormat}`;
        if (this.conversions.has(key)) {
            return this.conversions.get(key);
        }
        return null;
    }
    
    findEquivalents(formatName) {
        const format = this.dataManager.formats.find(f => f.format_name === formatName);
        if (!format) return [];
        
        const width = parseFloat(format.width_mm);
        const height = parseFloat(format.height_mm);
        
        return this.dataManager.formats.filter(f => {
            const fWidth = parseFloat(f.width_mm);
            const fHeight = parseFloat(f.height_mm);
            
            const widthDiff = Math.abs(fWidth - width) / width;
            const heightDiff = Math.abs(fHeight - height) / height;
            
            return (widthDiff < 0.1 && heightDiff < 0.1) && f.format_name !== formatName;
        });
    }
}
```

## CSS Styling

### Base Styles

```css
:root {
    --primary-color: #2563eb;
    --secondary-color: #1e40af;
    --text-color: #1f2937;
    --border-color: #d1d5db;
    --background-color: #f9fafb;
    --card-bg: #ffffff;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto;
    color: var(--text-color);
    background-color: var(--background-color);
    margin: 0;
    padding: 20px;
}

.format-selector {
    max-width: 1200px;
    margin: 0 auto;
}

.format-controls {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.format-controls input,
.format-controls select {
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
    flex: 1;
    min-width: 150px;
}

.format-controls input:focus,
.format-controls select:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.format-results {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.format-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.format-card:hover {
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.format-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    border-bottom: 2px solid var(--border-color);
    padding-bottom: 10px;
}

.format-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
}

.badge {
    background: var(--primary-color);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.format-body {
    margin-bottom: 15px;
}

.detail {
    padding: 8px 0;
    font-size: 14px;
    border-bottom: 1px solid var(--border-color);
}

.detail:last-child {
    border-bottom: none;
}

.detail strong {
    color: var(--secondary-color);
}

.format-preview {
    margin: 15px 0;
    padding: 15px;
    background: var(--background-color);
    border-radius: 4px;
    min-height: 150px;
}

.format-svg {
    width: 100%;
    height: 100%;
}

.btn-select {
    width: 100%;
    padding: 10px;
    background: var(--primary-color);
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s ease;
}

.btn-select:hover {
    background: var(--secondary-color);
}

/* Dark mode */
@media (prefers-color-scheme: dark) {
    :root {
        --text-color: #f3f4f6;
        --border-color: #374151;
        --background-color: #111827;
        --card-bg: #1f2937;
    }
}
```

## Interactive Examples

See `index.html` for complete interactive examples including:
- Format search and filtering
- Format preview visualization
- Format comparison table
- Format converter with equivalence detection
- Country-specific format browser

## API Integration

### Fetch from Remote API

```javascript
class FormatAPI {
    constructor(baseUrl) {
        this.baseUrl = baseUrl;
    }
    
    async getFormats(page = 1, limit = 20) {
        const response = await fetch(
            `${this.baseUrl}/formats?page=${page}&limit=${limit}`
        );
        return response.json();
    }
    
    async searchFormats(query) {
        const response = await fetch(
            `${this.baseUrl}/formats/search?q=${encodeURIComponent(query)}`
        );
        return response.json();
    }
    
    async getFormatsByCountry(countryCode) {
        const response = await fetch(
            `${this.baseUrl}/countries/${countryCode}/formats`
        );
        return response.json();
    }
    
    async getConversions(formatId) {
        const response = await fetch(
            `${this.baseUrl}/formats/${formatId}/conversions`
        );
        return response.json();
    }
}

// Usage
const api = new FormatAPI('https://api.example.com/api/v1');
api.searchFormats('A4').then(results => {
    console.log(results);
});
```

## Performance Tips

1. **Lazy Load Data** - Load CSV data only when needed
2. **Cache Results** - Cache search and filter results
3. **Debounce Search** - Add debouncing to search input
4. **Virtualization** - Use virtual scrolling for large lists
5. **Service Worker** - Cache format data for offline access

## Accessibility

- Use semantic HTML
- Add ARIA labels to interactive elements
- Ensure keyboard navigation works
- Use sufficient color contrast
- Test with screen readers

## Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

---

For more examples, see `index.html` and `examples.js` in this directory.

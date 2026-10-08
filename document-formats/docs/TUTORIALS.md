# Document Formats Database - Complete Tutorials

Step-by-step guides for using the document formats database in different scenarios.

## Table of Contents

1. [Quick Start](#quick-start)
2. [Web Application Tutorial](#web-application-tutorial)
3. [Backend Integration](#backend-integration)
4. [Data Analysis](#data-analysis)
5. [Print System Integration](#print-system-integration)
6. [Advanced Topics](#advanced-topics)

## Quick Start

### Load and Display Formats

**HTML Setup:**
```html
<!DOCTYPE html>
<html>
<head>
    <title>My Format Browser</title>
    <style>
        .format { padding: 10px; border: 1px solid #ccc; margin: 5px; }
    </style>
</head>
<body>
    <div id="formats"></div>
    <script src="../app/document-formats.js"></script>
    <script>
        // Load formats
        const loader = new FormatLoader('../formats/all_formats_master.csv');
        loader.load().then(formats => {
            const html = formats.map(f => `
                <div class="format">
                    <strong>${f.format_name}</strong> (${f.country})
                    <br>${f.width_mm}×${f.height_mm}mm
                </div>
            `).join('');
            document.getElementById('formats').innerHTML = html;
        });
    </script>
</body>
</html>
```

## Web Application Tutorial

### 1. Create a Format Selector

**Step 1: HTML Structure**
```html
<div class="app">
    <h1>Select Document Format</h1>
    
    <div class="controls">
        <label>Search:</label>
        <input type="text" id="search" placeholder="Type format name...">
        
        <label>Country:</label>
        <select id="country">
            <option value="">All</option>
        </select>
    </div>
    
    <div id="results" class="results-grid"></div>
</div>
```

**Step 2: JavaScript Implementation**
```javascript
class FormatSelector {
    constructor(loader) {
        this.loader = loader;
        this.formats = [];
        this.init();
    }

    async init() {
        this.formats = await this.loader.load();
        this.populateCountries();
        this.attachListeners();
    }

    populateCountries() {
        const countries = [...new Set(this.formats.map(f => f.country))];
        const select = document.getElementById('country');
        
        countries.forEach(country => {
            const option = document.createElement('option');
            option.value = country;
            option.textContent = country;
            select.appendChild(option);
        });
    }

    attachListeners() {
        document.getElementById('search').addEventListener('input', 
            e => this.handleSearch(e.target.value));
        document.getElementById('country').addEventListener('change',
            e => this.handleCountryFilter(e.target.value));
    }

    handleSearch(query) {
        const filtered = this.formats.filter(f =>
            f.format_name.toLowerCase().includes(query.toLowerCase()) ||
            f.common_use.toLowerCase().includes(query.toLowerCase())
        );
        this.displayFormats(filtered);
    }

    handleCountryFilter(country) {
        const filtered = country 
            ? this.formats.filter(f => f.country === country)
            : this.formats;
        this.displayFormats(filtered);
    }

    displayFormats(formats) {
        const container = document.getElementById('results');
        container.innerHTML = formats.map(f => `
            <div class="format-card">
                <h3>${f.format_name}</h3>
                <p><strong>Country:</strong> ${f.country}</p>
                <p><strong>Size:</strong> ${f.width_mm}×${f.height_mm}mm</p>
                <p><strong>Use:</strong> ${f.common_use}</p>
                <button onclick="selectFormat('${f.format_name}')">Select</button>
            </div>
        `).join('');
    }
}

// Usage
const loader = new FormatLoader('../formats/all_formats_master.csv');
new FormatSelector(loader);
```

**Step 3: CSS Styling**
```css
.format-card {
    border: 1px solid #ddd;
    padding: 15px;
    border-radius: 8px;
    background: #f9f9f9;
    margin-bottom: 10px;
    transition: transform 0.2s;
}

.format-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.format-card button {
    background: #007bff;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
}

.format-card button:hover {
    background: #0056b3;
}
```

### 2. Create a Format Comparison Tool

**HTML:**
```html
<div class="comparator">
    <select id="format1">
        <option value="">Select first format...</option>
    </select>
    
    <select id="format2">
        <option value="">Select second format...</option>
    </select>
    
    <table id="comparison">
        <thead>
            <tr>
                <th>Property</th>
                <th id="header1"></th>
                <th id="header2"></th>
            </tr>
        </thead>
        <tbody id="comparison-body">
        </tbody>
    </table>
</div>
```

**JavaScript:**
```javascript
class FormatComparator {
    constructor(formats) {
        this.formats = formats;
        this.init();
    }

    init() {
        this.populateSelects();
        this.attachListeners();
    }

    populateSelects() {
        const options = this.formats
            .map(f => f.format_name)
            .sort()
            .map(name => `<option value="${name}">${name}</option>`)
            .join('');

        document.getElementById('format1').innerHTML = '<option value="">Select...</option>' + options;
        document.getElementById('format2').innerHTML = '<option value="">Select...</option>' + options;
    }

    attachListeners() {
        document.getElementById('format1').addEventListener('change', () => this.compare());
        document.getElementById('format2').addEventListener('change', () => this.compare());
    }

    compare() {
        const name1 = document.getElementById('format1').value;
        const name2 = document.getElementById('format2').value;

        if (!name1 || !name2) return;

        const f1 = this.formats.find(f => f.format_name === name1);
        const f2 = this.formats.find(f => f.format_name === name2);

        document.getElementById('header1').textContent = name1;
        document.getElementById('header2').textContent = name2;

        const properties = [
            ['Country', 'country'],
            ['Width (mm)', 'width_mm'],
            ['Height (mm)', 'height_mm'],
            ['Width (in)', 'width_inches'],
            ['Height (in)', 'height_inches'],
            ['Aspect Ratio', 'aspect_ratio'],
            ['Category', 'category']
        ];

        const tbody = document.getElementById('comparison-body');
        tbody.innerHTML = properties.map(([label, key]) => `
            <tr>
                <td><strong>${label}</strong></td>
                <td>${f1[key]}</td>
                <td>${f2[key]}</td>
            </tr>
        `).join('');
    }
}

// Usage
const comparator = new FormatComparator(formats);
```

## Backend Integration

### PHP Example

```php
<?php
// Format class
class DocumentFormat {
    private $data = [];

    public function __construct($csvFile) {
        $this->loadFormats($csvFile);
    }

    private function loadFormats($file) {
        $file = fopen($file, 'r');
        $header = fgetcsv($file);
        
        while (($row = fgetcsv($file)) !== false) {
            $format = array_combine($header, $row);
            $this->data[$format['format_name']] = $format;
        }
        fclose($file);
    }

    public function getFormat($name) {
        return $this->data[$name] ?? null;
    }

    public function getByCountry($country) {
        return array_filter($this->data, function($f) use ($country) {
            return $f['country'] === $country;
        });
    }

    public function search($query) {
        return array_filter($this->data, function($f) use ($query) {
            return stripos($f['format_name'], $query) !== false ||
                   stripos($f['common_use'], $query) !== false;
        });
    }

    public function all() {
        return $this->data;
    }
}

// API Endpoint
header('Content-Type: application/json');

$formats = new DocumentFormat('../formats/all_formats_master.csv');

if ($_GET['action'] === 'search') {
    $results = $formats->search($_GET['q']);
    echo json_encode($results);
} else if ($_GET['action'] === 'country') {
    $results = $formats->getByCountry($_GET['code']);
    echo json_encode($results);
} else if ($_GET['action'] === 'format') {
    $format = $formats->getFormat($_GET['name']);
    echo json_encode($format);
} else {
    echo json_encode($formats->all());
}
?>
```

## Data Analysis

### Python Analysis

```python
import pandas as pd
import matplotlib.pyplot as plt

# Load data
formats = pd.read_csv('../formats/all_formats_master.csv')

# Basic statistics
print(f"Total formats: {len(formats)}")
print(f"Countries: {formats['country'].nunique()}")
print(f"Categories: {formats['category'].nunique()}")

# Group by country
by_country = formats.groupby('country').size()
print("\nFormats by country:")
print(by_country.sort_values(ascending=False).head(10))

# Group by category
by_category = formats.groupby('category').size()
print("\nFormats by category:")
print(by_category.sort_values(ascending=False))

# Find largest and smallest
largest = formats.loc[(formats['width_mm'].astype(float) * 
                       formats['height_mm'].astype(float)).idxmax()]
smallest = formats.loc[(formats['width_mm'].astype(float) * 
                        formats['height_mm'].astype(float)).idxmin()]

print(f"\nLargest: {largest['format_name']} ({largest['width_mm']}×{largest['height_mm']}mm)")
print(f"Smallest: {smallest['format_name']} ({smallest['width_mm']}×{smallest['height_mm']}mm)")

# Visualization
plt.figure(figsize=(12, 6))
by_country.sort_values(ascending=False).head(15).plot(kind='bar')
plt.title('Document Formats by Country')
plt.xlabel('Country')
plt.ylabel('Number of Formats')
plt.tight_layout()
plt.show()
```

## Print System Integration

### Document to Print

```javascript
class PrintDocument {
    constructor(formatName, content) {
        this.format = formatName;
        this.content = content;
    }

    async generatePDF() {
        // Using a library like jsPDF
        const pdf = new jsPDF({
            orientation: this.getOrientation(),
            unit: 'mm',
            format: this.getPageFormat()
        });

        pdf.text(this.content, 10, 10);
        return pdf.output();
    }

    getPageFormat() {
        // Convert to jsPDF format: 'a4', 'letter', etc.
        const mapping = {
            'A4': 'a4',
            'Letter': 'letter',
            'A3': 'a3'
        };
        return mapping[this.format] || 'a4';
    }

    getOrientation() {
        // Determine portrait or landscape
        return 'portrait';
    }

    print() {
        const printWindow = window.open('', '', 'height=400,width=800');
        printWindow.document.write('<pre>' + this.content + '</pre>');
        printWindow.document.close();
        printWindow.print();
    }
}

// Usage
const doc = new PrintDocument('A4', 'My document content');
doc.print();
```

## Advanced Topics

### 1. Format Auto-Detection

Detect which format a document uses based on dimensions:

```javascript
class FormatDetector {
    constructor(formats) {
        this.formats = formats;
    }

    detect(widthMm, heightMm, tolerance = 2) {
        return this.formats.find(f => {
            const fWidth = parseFloat(f.width_mm);
            const fHeight = parseFloat(f.height_mm);
            
            return Math.abs(fWidth - widthMm) <= tolerance &&
                   Math.abs(fHeight - heightMm) <= tolerance;
        });
    }
}
```

### 2. Format Recommendation Engine

Recommend formats based on content type:

```javascript
class FormatRecommender {
    constructor(formats) {
        this.formats = formats;
    }

    recommendForContent(contentType) {
        const recommendations = {
            'letter': ['Letter', 'A4'],
            'business-card': ['Business Card'],
            'poster': ['A2', 'A1', 'A0'],
            'brochure': ['A4', 'A3'],
            'photo': ['4x6', '5x7', '8x10'],
            'book': ['Trade Paperback', 'Hardcover']
        };

        return (recommendations[contentType] || [])
            .map(name => this.formats.find(f => f.format_name === name))
            .filter(Boolean);
    }
}
```

### 3. Format Caching

Cache format data for performance:

```javascript
class FormatCache {
    constructor() {
        this.cache = new Map();
    }

    set(key, value, ttl = 3600) {
        this.cache.set(key, {
            value,
            expires: Date.now() + (ttl * 1000)
        });
    }

    get(key) {
        const item = this.cache.get(key);
        if (!item) return null;

        if (Date.now() > item.expires) {
            this.cache.delete(key);
            return null;
        }

        return item.value;
    }

    clear() {
        this.cache.clear();
    }
}
```

---

For more examples, see `document-formats.js` and the interactive `index.html` in `../app/`.

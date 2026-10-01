# Localization System (i18n) - Complete Guide

The Document Formats Database includes a comprehensive multi-language localization system supporting **30 languages** across 200 countries.

## Supported Languages

The system supports the following 30 languages:

- **English** (en) - English
- **Spanish** (es) - Español
- **French** (fr) - Français
- **German** (de) - Deutsch
- **Chinese** (zh) - 中文
- **Japanese** (ja) - 日本語
- **Portuguese** (pt) - Português
- **Russian** (ru) - Русский
- **Arabic** (ar) - العربية
- **Korean** (ko) - 한국어
- **Italian** (it) - Italiano
- **Dutch** (nl) - Nederlands
- **Turkish** (tr) - Türkçe
- **Hindi** (hi) - हिन्दी
- **Thai** (th) - ไทย
- **Vietnamese** (vi) - Tiếng Việt
- **Polish** (pl) - Polski
- **Swedish** (sv) - Svenska
- **Norwegian** (no) - Norsk
- **Danish** (da) - Dansk
- **Finnish** (fi) - Suomi
- **Greek** (el) - Ελληνικά
- **Czech** (cs) - Čeština
- **Hungarian** (hu) - Magyar
- **Romanian** (ro) - Română
- **Bulgarian** (bg) - Български
- **Serbian** (sr) - Српски
- **Croatian** (hr) - Hrvatski
- **Slovak** (sk) - Slovenčina
- **Ukrainian** (uk) - Українська

## Quick Start

### Basic Usage

```javascript
// Initialize the localization manager
const i18n = new LocalizationManager('translations');
await i18n.init();

// Get a translated string
const title = i18n.t('header.title');
console.log(title); // "Document Format Browser" (or translated version)

// Change language
await i18n.setLanguage('es');
const titleSpanish = i18n.t('header.title');
console.log(titleSpanish); // "Navegador de Formatos de Documentos"
```

### HTML Integration

Use `data-translate` attributes to mark elements for translation:

```html
<h1 data-translate="header.title"></h1>
<p data-translate="header.subtitle"></p>

<input type="text" data-translate="search.placeholder:placeholder">
```

Then call `translatePage()` after language change:

```javascript
await i18n.setLanguage('fr');
i18n.translatePage();
```

## LocalizationManager API

### Constructor

```javascript
const i18n = new LocalizationManager(translationsPath = 'translations');
```

- `translationsPath`: Path to the translations directory (default: 'translations')

### Methods

#### `init()`

Initialize the localization manager and load the preferred language (or English as fallback).

```javascript
await i18n.init();
```

#### `loadLanguage(langCode)`

Load a specific language.

```javascript
await i18n.loadLanguage('de');
```

#### `setLanguage(langCode)`

Change the current language and save preference to localStorage.

```javascript
await i18n.setLanguage('ja');
```

#### `getLanguage()`

Get the currently active language code.

```javascript
const current = i18n.getLanguage();
console.log(current); // 'en', 'es', 'fr', etc.
```

#### `getSupportedLanguages()`

Get list of all supported language codes.

```javascript
const langs = i18n.getSupportedLanguages();
// ['en', 'es', 'fr', 'de', 'zh', 'ja', ...]
```

#### `translate(key, defaultValue = key)`

Translate a key using dot notation. Returns default value if key not found.

```javascript
const text = i18n.translate('header.title');
const fallback = i18n.translate('unknown.key', 'Default Text');
```

#### `t(key, defaultValue = key)`

Shorthand for `translate()`.

```javascript
const text = i18n.t('format.name');
```

#### `translateElement(element)`

Translate all `data-translate` attributes in an element and its children.

```javascript
i18n.translateElement(document.getElementById('app'));
```

#### `translatePage()`

Translate all `data-translate` attributes on the entire page.

```javascript
i18n.translatePage();
```

#### `subscribe(callback)`

Subscribe to language change events. Returns unsubscribe function.

```javascript
const unsubscribe = i18n.subscribe(lang => {
    console.log(`Language changed to ${lang}`);
});

// Unsubscribe later
unsubscribe();
```

#### `getLanguageName(langCode)`

Get the display name for a language code in that language.

```javascript
console.log(i18n.getLanguageName('es')); // 'Español'
console.log(i18n.getLanguageName('ja')); // '日本語'
console.log(i18n.getLanguageName('ar')); // 'العربية'
```

## Translation File Structure

Translation files are JSON files located in the `translations/` directory. Each file follows this structure:

```json
{
  "header": {
    "title": "Document Format Browser",
    "subtitle": "Global Database...",
    "tagline": "..."
  },
  "tabs": {
    "browser": "Browser",
    "comparison": "Comparison",
    "converter": "Converter",
    "statistics": "Statistics",
    "about": "About"
  },
  "search": {
    "placeholder": "Search...",
    "label": "Search:"
  },
  "filters": {
    "country": "Country:",
    "category": "Category:",
    "region": "Region:",
    "allCountries": "All Countries",
    "allCategories": "All Categories",
    "allRegions": "All Regions"
  },
  "format": {
    "name": "Format Name",
    "country": "Country",
    "size": "Size",
    "use": "Common Use",
    "category": "Category",
    "width": "Width",
    "height": "Height",
    "millimeters": "mm",
    "inches": "in",
    "aspectRatio": "Aspect Ratio",
    "measurementSystem": "Measurement System",
    "standard": "Standard",
    "notes": "Notes"
  },
  "regions": {
    "americas": "Americas",
    "europe": "Europe",
    "asiaPacific": "Asia-Pacific",
    "middleEast": "Middle East",
    "africa": "Africa",
    "oceania": "Oceania"
  }
}
```

## Updating Translations

To update or add translations:

1. Edit the corresponding `translations/[langCode].json` file
2. Use dot notation to reference keys: `translate('header.title')`
3. Changes take effect immediately when the page is reloaded or language is switched

### Adding a New Language

1. Create a new JSON file: `translations/[langCode].json`
2. Copy the structure from `en.json`
3. Add the language code to `supportedLanguages` array in `LocalizationManager`
4. Translate all strings to the new language

Example:

```bash
cp translations/en.json translations/pt.json
# Edit pt.json with Portuguese translations
```

## Web Interface Integration

### Language Selector Dropdown

```html
<select id="languageSelect" onchange="changeLanguage(this.value)">
  <option value="en">English</option>
  <option value="es">Español</option>
  <option value="fr">Français</option>
  <option value="de">Deutsch</option>
  <option value="zh">中文</option>
  <option value="ja">日本語</option>
  <!-- ... more languages ... -->
</select>

<script>
async function changeLanguage(langCode) {
    await i18n.setLanguage(langCode);
    i18n.translatePage();
}
</script>
```

### Real-time Language Switching

```javascript
i18n.subscribe(lang => {
    // Update UI when language changes
    document.getElementById('languageSelect').value = lang;
    
    // Update country list
    updateCountryList(lang);
    
    // Update search placeholder
    document.getElementById('search').placeholder = 
        i18n.t('search.placeholder');
});
```

## Advanced Examples

### Dynamic Content Translation

```javascript
// Translate data returned from API
function translateFormat(format) {
    return {
        ...format,
        category: i18n.t(`categories.${format.category.toLowerCase()}`),
        region: i18n.t(`regions.${format.region.toLowerCase()}`)
    };
}
```

### Conditional Translations

```javascript
// Get different strings based on language
const isRTL = ['ar', 'he', 'fa'].includes(i18n.getLanguage());
document.documentElement.dir = isRTL ? 'rtl' : 'ltr';
```

### Pluralization

```javascript
// Simple pluralization example
function formatCount(count, key) {
    return i18n.t(key).replace('{count}', count);
}
```

## Performance Optimization

### Caching

Translations are cached in memory after loading:

```javascript
// First call - loads from file
const text = i18n.t('header.title'); // ~async

// Subsequent calls are instant
const text2 = i18n.t('header.subtitle'); // instant
```

### Lazy Loading

```javascript
// Only load when needed
if (userSelectedLanguage === 'zh') {
    await i18n.loadLanguage('zh');
}
```

### Persistent Language Preference

The user's language preference is automatically saved to `localStorage` and restored on next visit:

```javascript
// First visit: English (default)
// User changes to Spanish: localStorage.setItem('preferredLanguage', 'es')
// Next visit: Spanish is automatically loaded
```

## Accessibility

The system includes accessibility features:

- Right-to-left (RTL) language support (Arabic, Hebrew, Farsi, Urdu)
- Language attribute on HTML element: `<html lang="en">`
- Proper text direction for RTL languages

Example:

```javascript
const isRTL = ['ar', 'he', 'fa', 'ur'].includes(i18n.getLanguage());
document.documentElement.lang = i18n.getLanguage();
document.documentElement.dir = isRTL ? 'rtl' : 'ltr';
```

## Troubleshooting

### Language Not Loading

```javascript
// Check if language file exists
const isSupported = i18n.getSupportedLanguages().includes('xx');
console.log(isSupported); // false

// Falls back to English automatically
await i18n.setLanguage('xx');
// Still in English
```

### Missing Translations

```javascript
// Provide a default value
const text = i18n.t('missing.key', 'Fallback Text');
```

### Language Not Persisting

The system automatically saves to localStorage:

```javascript
// Check stored preference
console.log(localStorage.getItem('preferredLanguage'));

// Clear preference to reset
localStorage.removeItem('preferredLanguage');
```

## Examples

### Complete Application

```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Document Formats Database</title>
</head>
<body>
    <select id="languageSelect" onchange="changeLanguage(this.value)"></select>
    <h1 id="title" data-translate="header.title"></h1>
    <div id="app"></div>

    <script src="examples.js"></script>
    <script>
        const i18n = new LocalizationManager('translations');

        i18n.subscribe(lang => {
            // Update select dropdown
            const select = document.getElementById('languageSelect');
            select.innerHTML = i18n.getSupportedLanguages().map(code =>
                `<option value="${code}" ${code === lang ? 'selected' : ''}>${i18n.getLanguageName(code)}</option>`
            ).join('');
        });

        async function changeLanguage(langCode) {
            await i18n.setLanguage(langCode);
            i18n.translatePage();
        }

        // Initialize
        (async () => {
            await i18n.init();
            i18n.translatePage();
            
            // Populate language selector
            const select = document.getElementById('languageSelect');
            select.innerHTML = i18n.getSupportedLanguages().map(code =>
                `<option value="${code}" ${code === i18n.getLanguage() ? 'selected' : ''}>${i18n.getLanguageName(code)}</option>`
            ).join('');
        })();
    </script>
</body>
</html>
```

## Statistics

- **Total Languages**: 30
- **Translation Keys**: 100+
- **File Size**: ~20KB per language
- **Load Time**: <100ms per language
- **Coverage**: All UI strings translated

## Support

For additional help with the localization system:

1. Check the translation files in `translations/` directory
2. Review the `LocalizationManager` class in `examples.js`
3. See the `TUTORIALS.md` for practical examples
4. Refer to the `frontend_implementation_guide.md` for integration patterns


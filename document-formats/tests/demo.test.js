const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const { parseCSV, paperTypeName, formatMessage, LocalizationManager, FORMAT_CATALOG_FILES } = require('../app/document-formats.js');

const APP = path.join(__dirname, '..', 'app');
const page = fs.readFileSync(path.join(APP, 'index.html'), 'utf8');
const script = fs.readFileSync(path.join(APP, 'demo.js'), 'utf8');
const html = `${page}\n${script}`;   // the markup and the script that fills it, read together
const english = JSON.parse(fs.readFileSync(path.join(APP, 'translations', 'en.json'), 'utf8'));
const csv = (file) => parseCSV(fs.readFileSync(path.join(__dirname, '..', 'specs', file), 'utf8'));

const lookup = (tree, key) => key.split('.').reduce((node, part) => node?.[part], tree);
const LANGUAGES = Object.keys(LocalizationManager.LANGUAGE_NAMES);

// These tests read index.html and demo.js as text. They don't replace opening the demo in a browser,
// but they stop the easy regressions: a label unplugged from its field, a tab without its
// panel, a translation key that doesn't exist.

test('the page has its landmarks and a language', () => {
    assert.match(html, /<html lang="en">/);
    for (const tag of ['header', 'main', 'footer']) {
        assert.equal((html.match(new RegExp(`<${tag}[\\s>]`, 'g')) ?? []).length, 1, `exactly one <${tag}>`);
    }
});

test('every field has a label that points at it', () => {
    const ids = [...html.matchAll(/<(?:input|select|textarea)\b[^>]*\bid="([^"]+)"/g)].map(m => m[1]);
    assert.ok(ids.length >= 10, `only ${ids.length} fields found`);
    for (const id of ids) {
        assert.match(html, new RegExp(`<label[^>]*\\bfor="${id}"`), `<label for="${id}"> is missing`);
    }
});

test('every tab is a tab of a panel, and the panel names its tab back', () => {
    const tabs = [...html.matchAll(/<button\b[^>]*\brole="tab"[^>]*>/g)].map(m => m[0]);
    assert.equal(tabs.length, 5);
    assert.equal(tabs.filter(tag => /aria-selected="true"/.test(tag)).length, 1, 'one tab starts selected');
    for (const tag of tabs) {
        const id = tag.match(/\bid="([^"]+)"/)[1];
        const panel = tag.match(/aria-controls="([^"]+)"/)[1];
        assert.match(html, new RegExp(`id="${panel}"[^>]*role="tabpanel"[^>]*aria-labelledby="${id}"`), `${panel} should be a tabpanel labelled by ${id}`);
    }
});

test('every translation key the page uses exists in en.json', () => {
    const fromMarkup = [...html.matchAll(/data-translate="([^"]+)"/g)].flatMap(m => m[1].split('|').map(part => part.split(':')[0]));
    const fromScript = [...html.matchAll(/\b(?:tr|fallbackLang)\('([\w.]+)'/g)].map(m => m[1]);
    const keys = new Set([...fromMarkup, ...fromScript]);
    assert.ok(keys.size > 40, `only ${keys.size} keys found`);
    const missing = [...keys].filter(key => typeof lookup(english, key) !== 'string' || lookup(english, key) === '');
    assert.deepEqual(missing, [], 'these keys have no English text');
});

test('every language has every string, and does not just copy the English ones', () => {
    const flat = (node, prefix = '') => Object.entries(node).flatMap(([k, v]) =>
        typeof v === 'object' ? flat(v, `${prefix}${k}.`) : [[`${prefix}${k}`, v]]);
    const englishStrings = new Map(flat(english));
    for (const lang of LANGUAGES) {
        const tree = JSON.parse(fs.readFileSync(path.join(APP, 'translations', `${lang}.json`), 'utf8'));
        const blanks = [];
        const walk = (node, prefix) => Object.entries(node).forEach(([k, v]) =>
            typeof v === 'object' ? walk(v, `${prefix}${k}.`) : (v === '' && blanks.push(`${prefix}${k}`)));
        walk(tree, '');
        assert.deepEqual(blanks, [], `${lang} is meant to be complete`);
        if (lang === 'en') continue;
        // Some words are the same in many languages (Region, Standard, Format), but not a quarter of the file.
        const same = flat(tree).filter(([key, value]) => value === englishStrings.get(key));
        assert.ok(same.length < englishStrings.size / 4, `${lang} repeats ${same.length} English strings: ${same.slice(0, 5).map(([k]) => k)}`);
    }
});

test('placeholders such as {count} survive translation', () => {
    const placeholders = (text) => [...String(text).matchAll(/\{(\w+)\}/g)].map(m => m[1]).sort();
    const keys = ['demo.proportions', 'demo.similarity', 'demo.resultsFormats', 'demo.resultsPaperTypes', 'paperTypes.combinedTitle',
        'demo.loadError', 'demo.loadErrorHelp', 'demo.aboutFormats', 'demo.aboutCountries'];
    for (const lang of ['de', 'es', 'fr', 'ja', 'zh']) {
        const tree = JSON.parse(fs.readFileSync(path.join(APP, 'translations', `${lang}.json`), 'utf8'));
        for (const key of keys) {
            assert.deepEqual(placeholders(lookup(tree, key)), placeholders(lookup(english, key)), `${lang} ${key}`);
        }
    }
});

test('formatMessage fills placeholders and leaves unknown ones visible', () => {
    assert.equal(formatMessage('{count} formats found', { count: 3 }), '3 formats found');
    assert.equal(formatMessage('{a} and {a}', { a: 'x' }), 'x and x');
    assert.equal(formatMessage('{count} found, {missing}', { count: 0 }), '0 found, {missing}');
    assert.equal(formatMessage('no placeholders'), 'no placeholders');
});

test('paperTypeName: Spanish and English come from paper_types.csv, the rest from the translations', () => {
    const type = { type_name: 'Papel bond', type_name_en: 'Bond' };
    assert.equal(paperTypeName(type, { fr: 'Papier bond' }, 'es'), 'Papel bond');
    assert.equal(paperTypeName(type, { fr: 'Papier bond' }, 'en'), 'Bond');
    assert.equal(paperTypeName(type, { fr: 'Papier bond' }, 'fr'), 'Papier bond');
    assert.equal(paperTypeName(type, { fr: '' }, 'fr'), 'Bond', 'an empty translation falls back to English');
    assert.equal(paperTypeName(type, undefined, 'de'), 'Bond', 'no translation row falls back to English');
});

test('every language of the demo has a name for every paper type', () => {
    const types = csv('paper_types.csv');
    const translations = new Map(csv('paper_type_translations.csv').map(row => [row.type_name, row]));
    const covered = new Set(['es', 'en', ...Object.keys([...translations.values()][0]).filter(key => key !== 'type_name')]);
    assert.deepEqual([...covered].sort(), [...LANGUAGES].sort(), 'paper_type_translations.csv must cover exactly the languages of LocalizationManager');
    for (const lang of LANGUAGES) {
        for (const type of types) {
            assert.ok(paperTypeName(type, translations.get(type.type_name), lang), `${lang}: no name for ${type.type_name}`);
        }
    }
});

test('Localization.md states the real translation coverage', () => {
    const count = (tree) => Object.values(tree).reduce((sum, v) => sum + (typeof v === 'object' ? count(v) : 1), 0);
    const filled = (tree) => Object.values(tree).reduce((sum, v) => sum + (typeof v === 'object' ? filled(v) : v === '' ? 0 : 1), 0);
    const total = count(english);
    const perLanguage = LANGUAGES.map(lang => filled(JSON.parse(fs.readFileSync(path.join(APP, 'translations', `${lang}.json`), 'utf8'))));
    const complete = perLanguage.filter(n => n === total).length;
    const partial = [...new Set(perLanguage.filter(n => n !== total))];
    assert.ok(partial.length <= 1, 'the partial languages all translate the same strings');

    const doc = fs.readFileSync(path.join(__dirname, '..', 'docs', 'Localization.md'), 'utf8');
    const stated = (label) => doc.match(new RegExp(`\\*\\*${label}\\*\\*: (\\d+)`))?.[1];
    assert.equal(Number(stated('Languages')), LANGUAGES.length);
    assert.equal(Number(stated('Strings per language')), total);
    assert.equal(Number(stated('Fully translated')), complete);
    if (partial.length === 0) {
        assert.equal(complete, LANGUAGES.length);
        assert.equal(/Partly translated/.test(doc), false, 'no language is partial any more');
        assert.ok(doc.includes(`all ${total} strings`), 'the intro of the languages list');
    } else {
        assert.equal(Number(stated('Partly translated')), LANGUAGES.length - complete);
        assert.ok(doc.includes(`with ${partial[0]} of the ${total} strings`), 'the partial coverage line');
    }
});

test('the page loads the tabs and URL-state scripts it uses, after the library', () => {
    const scripts = [...html.matchAll(/<script src="([^"]+)"/g)].map(m => m[1]);
    assert.deepEqual(scripts, ['document-formats.js', 'tabs.js', 'url-state.js', 'demo.js']);
    for (const file of scripts) assert.ok(fs.existsSync(path.join(APP, file)), `${file} is missing`);
});

test('every field kept in the URL is a field of the page', () => {
    const { URL_STATE_KEYS } = require('../app/url-state.js');
    const fields = Object.fromEntries([...html.matchAll(/\b(\w+): '([\w-]+-(?:input|filter|search|format|select))'/g)].map(m => [m[1], m[2]]));
    const fromPage = Object.keys(fields);
    const kept = URL_STATE_KEYS.filter(key => !['lang', 'tab', 'desc'].includes(key));   // desc is the reverse-order button
    assert.deepEqual(fromPage.sort(), [...kept].sort(), 'STATE_FIELDS in demo.js and URL_STATE_KEYS must list the same keys');
    for (const id of Object.values(fields)) assert.match(html, new RegExp(`id="${id}"`), `#${id} is not in the page`);
});

test('the name of the downloaded CSV is a safe file name', () => {
    for (const lang of LANGUAGES) {
        const tree = JSON.parse(fs.readFileSync(path.join(APP, 'translations', `${lang}.json`), 'utf8'));
        const name = lookup(tree, 'demo.csvFilename');
        // Letters of any script are fine; what a file system or a download would refuse or mangle is not.
        assert.match(name, /^[^\\/:*?"<>|\x00-\x1f]+$/, `${lang}: "${name}"`);
        assert.equal(name, name.trim(), `${lang}: spaces at the ends`);
        assert.ok(!name.startsWith('.') && !name.endsWith('.') && name.length <= 60, `${lang}: "${name}"`);
        assert.ok(name !== '', `${lang}: empty`);
    }
});

test('the About lines that carry a number have the {count} placeholder in every language that translates them', () => {
    for (const lang of LANGUAGES) {
        const tree = JSON.parse(fs.readFileSync(path.join(APP, 'translations', `${lang}.json`), 'utf8'));
        for (const key of ['demo.aboutFormats', 'demo.aboutCountries']) {
            const text = lookup(tree, key);
            if (text !== '') assert.ok(text.includes('{count}'), `${lang} ${key}`);
        }
    }
});

test('the page keeps no style attributes, so a strict content security policy could forbid inline styles', () => {
    assert.deepEqual(html.match(/\sstyle="/g) ?? [], []);
});

// ---- regions and categories in the language of the page ----------------------------------

const vocabularyKey = (value) => String(value).split(/[\s/-]+/).filter(Boolean)
    .map((word, i) => (i ? word[0].toUpperCase() + word.slice(1).toLowerCase() : word.toLowerCase())).join('');

test('every region and category of the data has a translation key, so the filters never mix languages', () => {
    const catalog = [...FORMAT_CATALOG_FILES].flatMap(file => parseCSV(fs.readFileSync(path.join(APP, file), 'utf8')));
    const categories = [...new Set(catalog.map(f => f.category))];
    const regions = [...new Set(parseCSV(fs.readFileSync(path.join(__dirname, '..', 'countries', 'countries_200_complete.csv'), 'utf8')).map(c => c.region))];
    regions.push('International', 'Europe');   // the two the catalog names that are not in the countries list
    const missing = [
        ...categories.filter(v => typeof lookup(english, `categories.${vocabularyKey(v)}`) !== 'string').map(v => `categories.${vocabularyKey(v)}`),
        ...regions.filter(v => typeof lookup(english, `regions.${vocabularyKey(v)}`) !== 'string').map(v => `regions.${vocabularyKey(v)}`),
    ];
    assert.deepEqual(missing, []);
    // And the English text is the CSV's own word, so English users see exactly what the data says.
    for (const value of categories) assert.equal(lookup(english, `categories.${vocabularyKey(value)}`), value);
    for (const value of regions) assert.equal(lookup(english, `regions.${vocabularyKey(value)}`), value);
});

test('the translation keys the page does not use are listed here, so a new one cannot go unnoticed', () => {
    const flat = (node, prefix = '') => Object.entries(node).flatMap(([k, v]) =>
        typeof v === 'object' ? flat(v, `${prefix}${k}.`) : [`${prefix}${k}`]);
    const used = new Set();
    for (const m of html.matchAll(/data-translate="([^"]+)"/g)) m[1].split('|').forEach(part => used.add(part.split(':')[0]));
    for (const m of html.matchAll(/\b(?:tr|fallbackLang|i18n\.t)\(\s*'([\w.]+)'/g)) used.add(m[1]);
    for (const key of ['demo.aboutFormats', 'demo.aboutCountries']) used.add(key);   // filled in by a loop
    for (const key of flat(english)) if (html.includes(`'${key}'`)) used.add(key);   // passed around as options
    const dynamic = /^(regions|categories|paperCategories)\./;   // vocabularyLabel() looks these up by the value of the data
    // Vocabulary for people who use the library (docs/Localization.md) rather than this page.
    const reserved = /^(header|search|filters|format|comparison|converter|statistics|actions|measurementSystems|messages)\.|^(language|theme|light|dark|auto)$|^tabs\.statistics$|^categories\.(specialty|business|packaging)$/;
    const unused = flat(english).filter(key => !used.has(key) && !dynamic.test(key));
    const unexpected = unused.filter(key => !reserved.test(key));
    assert.deepEqual(unexpected, [], 'these keys are in en.json but nothing uses them: use them, or list them as reserved');
});

test('the Browser tab can sort, and the choice is part of the address', () => {
    assert.match(html, /<select id="sort-select">/);
    for (const value of ['', 'name', 'width', 'height', 'area']) assert.match(html, new RegExp(`<option value="${value}"[^>]*data-translate="demo\\.sort`));
    assert.ok(require('../app/url-state.js').URL_STATE_KEYS.includes('sort'));
    for (const lang of LANGUAGES) {
        const tree = JSON.parse(fs.readFileSync(path.join(APP, 'translations', `${lang}.json`), 'utf8'));
        for (const key of ['sortBy', 'sortDefault', 'sortName', 'sortWidth', 'sortHeight', 'sortArea']) {
            assert.ok(lookup(tree, `demo.${key}`), `${lang} demo.${key}`);
        }
    }
});

test('index.html has no inline script or style, so a content security policy can forbid them', () => {
    assert.deepEqual(page.match(/<script(?![^>]*\bsrc=)[^>]*>/g) ?? [], [], 'inline <script>');
    assert.deepEqual(page.match(/<style[\s>]/g) ?? [], [], 'inline <style>');
    assert.deepEqual(page.match(/\son[a-z]+="/g) ?? [], [], 'inline event handlers');
    assert.match(page, /<link rel="stylesheet" href="demo\.css">/);
    assert.ok(fs.existsSync(path.join(APP, 'demo.css')));
});

test('every category of the paper types has a translation key, in English the CSV\'s own word', () => {
    const categories = [...new Set(csv('paper_types.csv').map(p => p.category))];
    for (const value of categories) {
        assert.equal(lookup(english, `paperCategories.${vocabularyKey(value)}`), value, value);
    }
    assert.equal(Object.keys(english.paperCategories).length, categories.length, 'no key for a category the data does not have');
});

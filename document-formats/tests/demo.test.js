const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const { parseCSV, paperTypeName, formatMessage, LocalizationManager } = require('../app/document-formats.js');

const APP = path.join(__dirname, '..', 'app');
const html = fs.readFileSync(path.join(APP, 'index.html'), 'utf8');
const english = JSON.parse(fs.readFileSync(path.join(APP, 'translations', 'en.json'), 'utf8'));
const csv = (file) => parseCSV(fs.readFileSync(path.join(__dirname, '..', 'specs', file), 'utf8'));

const lookup = (tree, key) => key.split('.').reduce((node, part) => node?.[part], tree);
const LANGUAGES = Object.keys(LocalizationManager.LANGUAGE_NAMES);

// These tests read index.html as text. They don't replace opening the demo in a browser,
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
    const fromScript = [...html.matchAll(/\btr\('([\w.]+)'/g)].map(m => m[1]);
    const keys = new Set([...fromMarkup, ...fromScript]);
    assert.ok(keys.size > 40, `only ${keys.size} keys found`);
    const missing = [...keys].filter(key => typeof lookup(english, key) !== 'string' || lookup(english, key) === '');
    assert.deepEqual(missing, [], 'these keys have no English text');
});

test('the languages with every string translated also have the new demo strings', () => {
    for (const lang of ['de', 'en', 'es', 'fr', 'ja', 'zh']) {
        const tree = JSON.parse(fs.readFileSync(path.join(APP, 'translations', `${lang}.json`), 'utf8'));
        const blanks = [];
        const walk = (node, prefix) => Object.entries(node).forEach(([k, v]) =>
            typeof v === 'object' ? walk(v, `${prefix}${k}.`) : (v === '' && blanks.push(`${prefix}${k}`)));
        walk(tree, '');
        assert.deepEqual(blanks, [], `${lang} is meant to be complete`);
    }
});

test('placeholders such as {count} survive translation', () => {
    const placeholders = (text) => [...String(text).matchAll(/\{(\w+)\}/g)].map(m => m[1]).sort();
    const keys = ['demo.proportions', 'demo.similarity', 'demo.resultsFormats', 'demo.resultsPaperTypes', 'paperTypes.combinedTitle'];
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

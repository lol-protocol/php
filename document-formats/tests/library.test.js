const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const APP_DIR = path.join(__dirname, '..', 'app');

// The library fetches paths relative to the page in app/; serve them from
// disk, answering 404 for a missing file the way a web server would.
globalThis.fetch = async (url) => {
    const file = path.resolve(APP_DIR, url);
    if (!fs.existsSync(file)) return { ok: false, status: 404, text: async () => 'Not found' };
    const text = fs.readFileSync(file, 'utf8');
    const mtime = fs.statSync(file).mtime;
    return {
        ok: true, status: 200, text: async () => text, json: async () => JSON.parse(text),
        headers: { get: (name) => name.toLowerCase() === 'last-modified' ? mtime.toUTCString() : null }
    };
};
globalThis.localStorage = { getItem: () => null, setItem: () => {} };

const {
    escapeHtml, splitCSVLine, parseCSV, sizeSimilarity, FORMAT_CATALOG_FILES, loadFormatCatalogs,
    FormatConverter, FormatValidator, FormatBatchProcessor, DocumentGenerator,
    BookMarginManager, ScreenDeviceManager, SpecificationManager, LocalizationManager,
    MM_PER_INCH, mmToInches, inchesToMm, fixed, fetchCSV, indexByName, closestNames, UnknownFormatError,
    DocumentFormats, FormatLoader, FormatSearcher, FormatStatistics, normalizeText,
} = require('../app/document-formats.js');

test('escapeHtml neutralises every HTML-significant character', () => {
    assert.equal(escapeHtml(`<a href="x" title='y'>&</a>`),
        '&lt;a href=&quot;x&quot; title=&#39;y&#39;&gt;&amp;&lt;/a&gt;');
    assert.equal(escapeHtml(210), '210');
    assert.equal(escapeHtml(undefined), '');
    assert.equal(escapeHtml(null), '');
});

test('splitCSVLine keeps a quoted comma inside its field', () => {
    assert.deepEqual(splitCSVLine('A4,"Cyprus, Northern",210'), ['A4', 'Cyprus, Northern', '210']);
});

test('splitCSVLine unescapes doubled quotes', () => {
    assert.deepEqual(splitCSVLine('"say ""hi""",x'), ['say "hi"', 'x']);
});

test('splitCSVLine treats a quote in the middle of a field as a literal inch mark', () => {
    assert.deepEqual(splitCSVLine('Tablet 10",Digital,13'), ['Tablet 10"', 'Digital', '13']);
});

test('splitCSVLine keeps empty fields', () => {
    assert.deepEqual(splitCSVLine('a,,c,'), ['a', '', 'c', '']);
});

test('parseCSV converts known numeric columns and leaves placeholders as text', () => {
    const rows = parseCSV('format_name,width_mm,screen_size_inches\nA4,210,N/A\nTV,1,100+');
    assert.equal(rows[0].width_mm, 210);
    assert.equal(rows[0].screen_size_inches, 'N/A');
    assert.equal(rows[1].screen_size_inches, '100+');
    assert.equal(rows[0].format_name, 'A4');
});

test('findEquivalents finds a same-height match whose width is far away', () => {
    // Regression: a width-only pre-filter once hid this 93% match.
    const converter = new FormatConverter([
        { format_name: 'A', width_mm: 200, height_mm: 1000 },
        { format_name: 'B', width_mm: 230, height_mm: 1000 },
        { format_name: 'C', width_mm: 500, height_mm: 50 },
    ]);
    const found = converter.findEquivalents('A', 90);
    assert.deepEqual(found.map(f => [f.name, f.similarity]), [['B', 93]]);
});

test('validateDimensions accepts mm, cm and inches', () => {
    const validator = new FormatValidator([{ format_name: 'A4', width_mm: 210, height_mm: 297 }]);
    assert.ok(validator.validateDimensions(210, 297, 'mm').isValid);
    assert.ok(validator.validateDimensions(21, 29.7, 'cm').isValid);
    assert.ok(validator.validateDimensions(8.27, 11.69, 'inch').isValid);
    assert.ok(!validator.validateDimensions(216, 279, 'mm').isValid);
});

test('exportAsHTML escapes cell content', () => {
    const html = new FormatBatchProcessor([{ format_name: '<script>x</script>' }]).exportAsHTML();
    assert.ok(!html.includes('<script>'));
    assert.ok(html.includes('&lt;script&gt;x&lt;/script&gt;'));
});

test('exportAsCSV output parses back to the same rows', () => {
    const rows = [
        { format_name: 'Tablet 10"', common_use: 'Reading, video' },
        { format_name: 'A4', common_use: 'Office' },
    ];
    const csv = new FormatBatchProcessor(rows).exportAsCSV();
    assert.deepEqual(parseCSV(csv, new Set()), rows);
});

test('generateHTML escapes the title but inserts the body as markup', () => {
    const html = new DocumentGenerator([{ format_name: 'A4', width_mm: 210, height_mm: 297 }])
        .generateHTML('A4', '<b>Title</b>', '<p>Body</p>');
    assert.ok(html.includes('<h1>&lt;b&gt;Title&lt;/b&gt;</h1>'));
    assert.ok(html.includes('<p>Body</p>'));
    assert.ok(html.includes('size: 21.0cm 29.7cm'));
});

test('book margins load as numbers and give the printable area', async () => {
    const margins = new BookMarginManager([]);
    await margins.load();
    assert.equal(typeof margins.getMargins('Trade Paperback').inner_mm, 'number');
    // 152×228 book, margins 16/13 inside/outside and 19/19 top/bottom.
    assert.equal(margins.calculatePrintableArea('Trade Paperback', 152, 228).printableArea.mm, '123×190');
});

test('device loading reads every device file and filters by size and type', async () => {
    const devices = new ScreenDeviceManager();
    await devices.loadAllDevices();
    for (const list of ['monitors', 'smartphones', 'tablets', 'ereaders']) {
        assert.ok(devices[list].length > 0, `${list} is empty`);
    }

    // Regression: mismatched size buckets once hid every phone between 6 and 7".
    const phones = devices.getDevicesBySize(6, 7, 'phone');
    assert.ok(phones.length > 0);
    assert.ok(phones.every(p => p.screen_size_inches >= 6 && p.screen_size_inches <= 7));

    const phone = phones[0];
    assert.equal(devices.getDevice(phone.device_name, 'phone'), phone);
    assert.equal(devices.getDevice(phone.device_name, 'tablet'), null);
});

test('every specification file loads through its configured path', async () => {
    const specs = new SpecificationManager();
    await specs.loadAllSpecifications();
    for (const name of Object.keys(SpecificationManager.SPECS)) {
        assert.ok(specs.specifications.get(name).length > 0, `${name} loaded no rows`);
    }
});

test('paper types can be looked up by name and filtered by category', async () => {
    const specs = new SpecificationManager();
    await specs.loadAllSpecifications();

    const roneo = specs.getPaperType('Papel roneo');
    assert.ok(roneo, 'Papel roneo should be in the catalog');
    assert.equal(roneo.category, 'Writing/Printing');

    const all = specs.getAllPaperTypes();
    assert.ok(all.length >= 50, `only ${all.length} paper types`);

    const decorative = specs.getPaperTypesByCategory('Decorative');
    assert.ok(decorative.length > 0, 'no Decorative paper types found');
    assert.ok(decorative.every(p => p.category === 'Decorative'));
});

test('the default language loads from app/translations', async () => {
    const i18n = new LocalizationManager();
    await i18n.init();
    assert.equal(i18n.getLanguage(), 'en');
    assert.notEqual(i18n.t('header.title'), 'header.title');
});

test('no language ever shows a blank label: empty or missing strings fall back to English', async () => {
    const english = JSON.parse(fs.readFileSync(path.join(APP_DIR, 'translations', 'en.json'), 'utf8'));
    const keys = [];
    const walk = (tree, prefix) => Object.entries(tree).forEach(([k, v]) =>
        typeof v === 'object' ? walk(v, `${prefix}${k}.`) : keys.push(`${prefix}${k}`));
    walk(english, '');
    assert.ok(keys.length > 50, `only ${keys.length} English strings`);

    for (const file of fs.readdirSync(path.join(APP_DIR, 'translations'))) {
        const code = file.replace(/\.json$/, '');
        const i18n = new LocalizationManager();
        await i18n.loadLanguage(code);
        assert.equal(i18n.getLanguage(), code);
        const blank = keys.filter(key => i18n.t(key) === '' || i18n.t(key) === key);
        assert.deepEqual(blank, [], `${code}: these keys show nothing or the raw key`);
    }
});

test('a translated string wins over English, and a missing key still returns the default', async () => {
    const english = new LocalizationManager();
    await english.loadLanguage('en');
    const spanish = new LocalizationManager();
    await spanish.loadLanguage('es');
    assert.notEqual(spanish.t('header.title'), english.t('header.title'));
    assert.equal(spanish.t('no.such.key', 'Default'), 'Default');
    assert.equal(spanish.t('no.such.key'), 'no.such.key');
});

test('every translation file has exactly the keys of en.json', () => {
    const flat = (tree, prefix = '') => Object.entries(tree).flatMap(([k, v]) =>
        typeof v === 'object' ? flat(v, `${prefix}${k}.`) : [`${prefix}${k}`]);
    const dir = path.join(APP_DIR, 'translations');
    const expected = flat(JSON.parse(fs.readFileSync(path.join(dir, 'en.json'), 'utf8'))).sort();
    for (const file of fs.readdirSync(dir)) {
        const keys = flat(JSON.parse(fs.readFileSync(path.join(dir, file), 'utf8'))).sort();
        assert.deepEqual(keys, expected, `${file} does not have the same keys as en.json`);
    }
});

test('sizeSimilarity ignores orientation', () => {
    const ledger = { width_mm: 431.8, height_mm: 279.4 };
    const tabloid = { width_mm: 279.4, height_mm: 431.8 };
    assert.equal(sizeSimilarity(ledger, tabloid), 100);
    assert.equal(Math.round(sizeSimilarity({ width_mm: 210, height_mm: 297 }, { width_mm: 148, height_mm: 210 })), 71);
});

test('the catalog list names every file of sheet sizes', () => {
    const listed = new Set(FORMAT_CATALOG_FILES.map(file => path.resolve(APP_DIR, file)));
    for (const dir of ['countries', 'formats', 'specs']) {
        for (const name of fs.readdirSync(path.join(APP_DIR, '..', dir))) {
            const file = path.join(APP_DIR, '..', dir, name);
            const header = fs.readFileSync(file, 'utf8').split('\n', 1)[0];
            const isCatalog = header.startsWith('format_name,width_mm,') && name !== 'all_formats_master.csv';
            assert.equal(listed.has(file), isCatalog, `${dir}/${name} ${isCatalog ? 'is missing from' : 'should not be in'} FORMAT_CATALOG_FILES`);
        }
    }
});

test('loadFormatCatalogs returns every format once, with a category and a region', async () => {
    const {formats} = await loadFormatCatalogs();
    assert.ok(formats.length > 300, `only ${formats.length} formats`);
    assert.ok(formats.every(f => f.category), 'a format has no category');
    const keys = formats.map(f => [f.format_name, f.country, f.width_mm, f.height_mm].join('|'));
    assert.equal(new Set(keys).size, keys.length);

    const regionless = formats.filter(f => !f.region || f.region === 'Other');
    assert.deepEqual(regionless, [], 'every format should resolve to a real region');
    const a4 = formats.find(f => f.format_name === 'A4' && f.country === 'International');
    assert.equal(a4.region, 'International');
    const letter = formats.find(f => f.format_name === 'Letter' && f.country === 'USA');
    assert.equal(letter.region, 'Americas');
});

test('loadFormatCatalogs reports the newest Last-Modified header', async () => {
    const {lastModified} = await loadFormatCatalogs();
    assert.ok(lastModified instanceof Date && !Number.isNaN(lastModified.getTime()));
});

test('loadFormatCatalogs fails loudly when a file is missing', async () => {
    await assert.rejects(loadFormatCatalogs(['../formats/does_not_exist.csv']));
});

test('every catalog has the same columns in the same order', () => {
    const headerOf = (file) => fs.readFileSync(path.resolve(APP_DIR, file), 'utf8').split('\n', 1)[0];
    const expected = headerOf(FORMAT_CATALOG_FILES[0]);
    for (const file of FORMAT_CATALOG_FILES) {
        assert.equal(headerOf(file), expected, `${file} has different columns`);
    }
});

test('inches and millimetres convert with one factor', () => {
    assert.equal(MM_PER_INCH, 25.4);
    assert.equal(mmToInches(25.4), 1);
    assert.equal(fixed(inchesToMm(8.5)), '215.9');
    assert.equal(fixed(21), '21.0');
    assert.equal(fixed(1 / 3, 2), '0.33');
});

test('fetchCSV stops at a missing file instead of reading the error page as rows', async () => {
    await assert.rejects(fetchCSV('../specs/does_not_exist.csv'), /HTTP 404 for \.\.\/specs\/does_not_exist\.csv/);
    assert.ok((await fetchCSV('../specs/paper_types.csv')).length > 0);
});

test('a loader that cannot read its file logs it and keeps an empty list', async () => {
    const error = console.error;
    console.error = () => {};
    try {
        const loader = new FormatLoader('../formats/nope.csv');
        assert.deepEqual(await loader.load(), []);
    } finally { console.error = error; }
});

test('indexByName keeps the last row of a repeated name', () => {
    const index = indexByName([{ format_name: 'A4', width_mm: 1 }, { format_name: 'A4', width_mm: 2 }]);
    assert.equal(index.size, 1);
    assert.equal(index.get('A4').width_mm, 2);
});

test('an unknown format name suggests the closest ones', () => {
    const formats = ['A3', 'A4', 'A5', 'Letter', 'Legal'].map(format_name => ({ format_name }));
    assert.deepEqual(closestNames('A44', formats.map(f => f.format_name)), ['A4', 'A3', 'A5']);

    const error = new UnknownFormatError('A44', formats);
    assert.equal(error.name, 'UnknownFormatError');
    assert.equal(error.formatName, 'A44');
    assert.deepEqual(error.suggestions, ['A4', 'A3', 'A5']);
    assert.match(error.message, /^Format A44 not found \(did you mean A4, A3, A5\?\)$/);
    assert.ok(error instanceof Error);

    const generator = new DocumentGenerator(formats.map(f => ({ ...f, width_mm: 1, height_mm: 1 })));
    for (const call of [() => generator.createTemplate('A44'), () => generator.generateHTML('A44'), () => generator.generatePrintCSS('A44')]) {
        assert.throws(call, UnknownFormatError);
    }
    const check = new FormatValidator(formats).validateForPrinting('A44');
    assert.equal(check.valid, false);
    assert.deepEqual(check.suggestions, ['A4', 'A3', 'A5']);
});

test('a single thing that is not there is null, a list that is empty is []', async () => {
    const { formats } = await loadFormatCatalogs();
    assert.equal(new FormatConverter(formats).convert('A4', 'Nope'), null);
    assert.deepEqual(new FormatConverter(formats).findEquivalents('Nope'), []);
    assert.equal(new BookMarginManager([]).getMargins('Nope'), null);
    assert.equal(new BookMarginManager([]).calculatePrintableArea('Nope', 1, 1), null);

    const devices = new ScreenDeviceManager();
    assert.equal(devices.getDevice('Nope'), null);
    assert.equal(devices.getPixelDensity('Nope'), null);

    const specs = new SpecificationManager();
    await specs.loadAllSpecifications();
    assert.equal(specs.getPaperType('Nope'), null);
    assert.deepEqual(specs.getPaperTypesByCategory('Nope'), []);
});

test('rows handed out by SpecificationManager cannot be changed for the next caller', async () => {
    const specs = new SpecificationManager();
    await specs.loadAllSpecifications();
    const row = specs.getAllPaperTypes()[0];
    assert.throws(() => { 'use strict'; row.type_name = 'changed'; }, TypeError);
    assert.throws(() => { 'use strict'; specs.getAllPaperTypes().push({}); }, TypeError);
    const byCategory = specs.getPaperTypesByCategory(row.category);
    assert.throws(() => { 'use strict'; byCategory.pop(); }, TypeError);
    assert.equal(specs.getPaperTypesByCategory(row.category), byCategory, 'the second call is the cached one');
});

test('DocumentFormats.load wires every manager from one call', async () => {
    const docs = await DocumentFormats.load();
    assert.ok(docs.formats.length > 250);
    assert.ok(docs.lastModified instanceof Date);
    assert.ok(docs.converter.convert('Letter', 'A4'));
    assert.ok(docs.specs.getAllPaperTypes().length > 0);
    assert.ok(docs.margins.getMargins('Trade Paperback'));
    assert.ok(docs.devices.monitors.length > 0);
    assert.ok(docs.statistics.getStats().totalCountries > 20);
    assert.ok(docs.searcher.search('A4').length > 0);

    const light = await DocumentFormats.load({ only: [] });
    assert.equal(light.specs.getAllPaperTypes().length, 0, 'what is left out is not loaded');
});

test('data-translate values are read the same way by translateElement and translatePage', async () => {
    assert.deepEqual(LocalizationManager.parseTranslateAttr('header.title'), [{ key: 'header.title', attr: null }]);
    assert.deepEqual(LocalizationManager.parseTranslateAttr(' a.b | search.placeholder : placeholder '),
        [{ key: 'a.b', attr: null }, { key: 'search.placeholder', attr: 'placeholder' }]);
    assert.deepEqual(LocalizationManager.parseTranslateAttr(null), []);
    assert.deepEqual(LocalizationManager.parseTranslateAttr('a||b'), [{ key: 'a', attr: null }, { key: 'b', attr: null }]);

    const i18n = new LocalizationManager('../app/translations');
    await i18n.init();
    const fake = (value) => ({
        value, text: '', attrs: {},
        getAttribute() { return this.value; },
        setAttribute(name, v) { this.attrs[name] = v; },
        set textContent(v) { this.text = v; },
        querySelectorAll: () => [],
    });
    const element = fake('header.title|search.placeholder:placeholder');
    i18n.translateElement(element);
    assert.equal(element.text, i18n.t('header.title'));
    assert.equal(element.attrs.placeholder, i18n.t('search.placeholder'));

    // translatePage goes through the same code, one frame later.
    const page = fake('header.title|search.placeholder:placeholder');
    globalThis.requestAnimationFrame = (callback) => callback();
    globalThis.document = { querySelectorAll: () => [page] };
    try {
        i18n.translatePage();
    } finally {
        delete globalThis.requestAnimationFrame;
        delete globalThis.document;
    }
    assert.equal(page.text, element.text);
    assert.deepEqual(page.attrs, element.attrs);
});

test('recommended margins come from book_margins.csv for a book format, and are general otherwise', async () => {
    const bookMargins = new BookMarginManager([]);
    await bookMargins.load();
    const generator = new DocumentGenerator([{ format_name: 'Trade Paperback', category: 'Book' }, { format_name: 'A4', category: 'Standard' }]);
    assert.deepEqual(generator.getRecommendedMargins({ format_name: 'Trade Paperback' }), { top: 20, bottom: 20, left: 20, right: 20 },
        'before the margins are set, the general ones');

    generator.setBookMargins(bookMargins.getAllMargins());
    // Trade Paperback in book_margins.csv: top 19, bottom 19, inner 16, outer 13.
    assert.deepEqual(generator.getRecommendedMargins({ format_name: 'Trade Paperback' }), { top: 19, bottom: 19, left: 16, right: 13 });
    assert.deepEqual(generator.getRecommendedMargins({ format_name: 'A4', category: 'Standard' }), { top: 20, bottom: 20, left: 20, right: 20 });
    assert.deepEqual(generator.getRecommendedMargins({ format_name: 'X', category: 'Business' }), { top: 25, bottom: 25, left: 20, right: 20 });
});

test('validateForPrinting judges a format by its own recommended_dpi', async () => {
    const specs = new SpecificationManager();
    await specs.loadAllSpecifications();
    const formats = ['A4', 'Business Card Standard', 'Nothing in the CSV'].map(format_name => ({ format_name }));
    const validator = new FormatValidator(formats);

    // Without the specs, everything is judged by 300.
    assert.equal(validator.validateForPrinting('Business Card Standard', 300).recommended, true);

    validator.setPrintSpecs(specs.specifications.get('technicalSpecifications'));
    // A4 says 300-600; the business card says 600-1200.
    const a4 = validator.validateForPrinting('A4', 300);
    assert.deepEqual([a4.valid, a4.recommended, a4.recommendedDpi, a4.minimumDpi], [true, true, 300, 150]);
    const card = validator.validateForPrinting('Business Card Standard', 300);
    assert.deepEqual([card.valid, card.recommended, card.recommendedDpi, card.minimumDpi], [true, false, 600, 300]);
    assert.equal(validator.validateForPrinting('Business Card Standard', 200).valid, false);
    assert.equal(validator.validateForPrinting('Nothing in the CSV', 150).valid, true, 'a format with no row keeps the 300/150 limits');
});

test('search puts an exact name first and breaks ties alphabetically, whatever the file order', () => {
    const row = (format_name, country = 'X') => ({ format_name, country, category: 'Paper', width_mm: 1, height_mm: 1 });
    const names = (formats, query) => new FormatSearcher(formats).search(query).map(f => f.format_name);
    const forward = [row('A4 Plus'), row('A4'), row('A4 Extra')];
    assert.deepEqual(names(forward, 'a4'), ['A4', 'A4 Extra', 'A4 Plus']);
    assert.deepEqual(names([...forward].reverse(), 'a4'), ['A4', 'A4 Extra', 'A4 Plus']);
    // More matched terms still beat an exact name.
    assert.deepEqual(names([row('A4'), row('A4 Extra', 'Extra')], 'a4 extra').slice(0, 1), ['A4 Extra']);
});

test('DocumentFormats.load sets the book margins and the print specs', async () => {
    const docs = await DocumentFormats.load();
    assert.equal(docs.generator.getRecommendedMargins({ format_name: 'Trade Paperback' }).left, 16);
    assert.equal(docs.validator.validateForPrinting('Business Card Standard', 300).recommendedDpi, 600);
});

test('statistics of an empty catalog are zeros, not NaN', () => {
    const stats = new FormatStatistics([]).getStats();
    assert.equal(stats.totalFormats, 0);
    assert.equal(stats.averageWidth, '0.0');
    assert.equal(stats.averageHeight, '0.0');
    assert.equal(stats.largestFormat, null);
});

test('loading formats does not write to the console', async () => {
    const log = console.log;
    const lines = [];
    console.log = (...args) => lines.push(args);
    try {
        await new FormatLoader('../specs/iso_216_series.csv').load();
    } finally { console.log = log; }
    assert.deepEqual(lines, []);
});

test('LocalizationManager.isRtl knows the right-to-left languages', () => {
    for (const code of ['ar', 'he', 'fa', 'ur']) assert.equal(LocalizationManager.isRtl(code), true, code);
    for (const code of ['en', 'es', 'ja', 'zh']) assert.equal(LocalizationManager.isRtl(code), false, code);
    const files = fs.readdirSync(path.join(APP_DIR, 'translations')).map(f => f.replace('.json', ''));
    assert.deepEqual(files.filter(code => LocalizationManager.isRtl(code)), ['ar'], 'the only RTL translation file');
});

test('normalizeText ignores case and Latin accents, and leaves other scripts alone', () => {
    assert.equal(normalizeText('Papel TÉRMICO'), 'papel termico');
    assert.equal(normalizeText('México'), 'mexico');
    assert.equal(normalizeText('Zürich Škoda'), 'zurich skoda');
    assert.equal(normalizeText(null), '');
    assert.equal(normalizeText(210), '210');
    // The marks of Devanagari and the syllables of Korean are not accents: they stay as they are.
    assert.equal(normalizeText('सफ़ेद').length, normalizeText('सफ़ेद').normalize('NFC').length);
    assert.equal(normalizeText('한국어'), '한국어');
    assert.equal(normalizeText('ドキュメント'), 'ドキュメント');
});

test('searching finds a name whatever its accents and case', () => {
    const rows = [
        { format_name: 'Papel térmico', country: 'México', category: 'Roll', width_mm: 1, height_mm: 1 },
        { format_name: 'Carta', country: 'Mexico', category: 'Standard', width_mm: 1, height_mm: 1 },
    ];
    const search = new FormatSearcher(rows);
    assert.deepEqual(search.search('termico').map(f => f.format_name), ['Papel térmico']);
    assert.deepEqual(search.search('TÉRMICO').map(f => f.format_name), ['Papel térmico']);
    assert.deepEqual(search.search('méxico').map(f => f.format_name).sort(), ['Carta', 'Papel térmico']);
});

test('the language manager works, and switches language, when the browser blocks storage', async () => {
    const original = globalThis.localStorage;
    const blocked = () => { throw new Error('SecurityError: The operation is insecure.'); };
    globalThis.localStorage = { getItem: blocked, setItem: blocked };
    try {
        assert.equal(LocalizationManager.savedLanguage(), null);
        assert.doesNotThrow(() => LocalizationManager.saveLanguage('fr'));
        const i18n = new LocalizationManager('../app/translations');
        assert.equal(i18n.getLanguage(), 'en');
        await i18n.setLanguage('fr');
        assert.equal(i18n.getLanguage(), 'fr', 'the switch is not undone because the choice cannot be saved');
        assert.notEqual(i18n.t('demo.download'), 'Download CSV');
    } finally {
        globalThis.localStorage = original;
    }
});

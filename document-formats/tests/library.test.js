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
} = require('../app/document-formats.js');

const quiet = async (fn) => {
    const log = console.log;
    console.log = () => {};
    try { return await fn(); } finally { console.log = log; }
};

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
    assert.equal(devices.getDevice(phone.device_name, 'tablet'), undefined);
});

test('every specification file loads through its configured path', async () => {
    const specs = new SpecificationManager();
    await specs.loadAllSpecifications();
    for (const name of Object.keys(SpecificationManager.SPECS)) {
        assert.ok(specs.specifications.get(name).length > 0, `${name} loaded no rows`);
    }
});

test('the default language loads from app/translations', async () => {
    const i18n = new LocalizationManager();
    await quiet(() => i18n.init());
    assert.equal(i18n.getLanguage(), 'en');
    assert.notEqual(i18n.t('header.title'), 'header.title');
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

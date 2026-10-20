const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const { parseCSV, FORMAT_CATALOG_FILES } = require('../app/document-formats.js');

const ROOT = path.join(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(ROOT, file), 'utf8');
const rows = (file) => parseCSV(read(file));

const paperTypes = () => rows('specs/paper_types.csv');
const finishNames = () => new Set(rows('specs/finish_types.csv').map(row => row.finish_name));
const catalogNames = () => new Set(FORMAT_CATALOG_FILES.flatMap(file =>
    parseCSV(fs.readFileSync(path.resolve(ROOT, 'app', file), 'utf8')).map(row => row.format_name)));

const gsmRange = (range) => { const [min, max] = String(range).split('-').map(Number); return { min, max }; };
const overlap = (a, b) => a.min <= b.max && b.min <= a.max;

test('every finish is a term of the finish_types vocabulary', () => {
    const vocabulary = finishNames();
    paperTypes().forEach((row, i) => {
        assert.ok(vocabulary.has(row.finish), `paper_types.csv line ${i + 2} (${row.type_name}): "${row.finish}" is not in finish_types.csv`);
    });
    for (const file of ['specs/technical_specifications.csv', 'specs/product_specifications.csv']) {
        rows(file).forEach((row, i) => {
            if (row.finish_type === 'N/A') return;
            // "Matte/Glossy" means either of the two.
            for (const finish of row.finish_type.split('/')) {
                assert.ok(vocabulary.has(finish), `${file} line ${i + 2}: "${finish}" is not in finish_types.csv`);
            }
        });
    }
});

test('technical_specifications.csv only describes sheet sizes of the catalogs', () => {
    const names = catalogNames();
    rows('specs/technical_specifications.csv').forEach((row, i) => {
        assert.ok(names.has(row.format_name),
            `line ${i + 2}: "${row.format_name}" is not a catalog format; products go in product_specifications.csv`);
    });
});

test('product_specifications.csv: products are not sheet sizes, and a named paper type fits their weight', () => {
    const sizes = catalogNames();
    const types = new Map(paperTypes().map(row => [row.type_name, row]));
    rows('specs/product_specifications.csv').forEach((row, i) => {
        const where = `product_specifications.csv line ${i + 2} (${row.product_name})`;
        assert.ok(!sizes.has(row.product_name), `${where}: it is a catalog format, so it belongs in technical_specifications.csv`);
        const weight = gsmRange(row.weight_gsm_range);
        assert.ok(weight.min <= weight.max, `${where}: weight_gsm_range ${row.weight_gsm_range} runs backwards`);
        if (!row.paper_type) return;
        const type = types.get(row.paper_type);
        assert.ok(type, `${where}: no paper type called "${row.paper_type}"`);
        assert.ok(overlap(weight, gsmRange(type.weight_gsm_range)),
            `${where}: ${row.weight_gsm_range} gsm never meets the ${type.weight_gsm_range} gsm of ${type.type_name}`);
    });
});

test('paper_types.csv weight ranges run from lighter to heavier', () => {
    paperTypes().forEach((row, i) => {
        const { min, max } = gsmRange(row.weight_gsm_range);
        assert.ok(min < max, `line ${i + 2} (${row.type_name}): ${row.weight_gsm_range} is not an increasing range`);
    });
});

test('paper_weight_standards.csv: lb and gsm agree with the standard basis weights', () => {
    // g/m2 per pound of basis weight: bond 17x22", text 25x38", cover 20x26".
    const FACTOR = { Bond: 3.7597, Text: 1.4801, Cover: 2.7048 };
    const COLUMN = { Bond: 'lb_bond', Text: 'lb_text', Cover: 'lb_cover' };
    const CLASS_TYPES = { Bond: ['Bond'], Text: ['Text'], Cover: ['Cover', 'Cardstock'] };
    const weights = rows('specs/paper_weight_standards.csv');
    assert.equal(new Set(weights.map(row => row.gsm)).size, weights.length, 'a gsm value repeats');

    weights.forEach((row, i) => {
        const where = `line ${i + 2} (${row.weight_name})`;
        const [, nominal, weightClass] = row.weight_name.match(/^(\d+) lb (Bond|Text|Cover)$/) ?? [];
        assert.ok(weightClass, `${where}: the name should read "<N> lb <Bond|Text|Cover>"`);
        assert.ok(CLASS_TYPES[weightClass].includes(row.weight_type), `${where}: a ${weightClass} weight cannot be "${row.weight_type}"`);
        assert.equal(Number(row[COLUMN[weightClass]]), Number(nominal), `${where}: ${COLUMN[weightClass]} should be the nominal ${nominal}`);
        for (const [cls, factor] of Object.entries(FACTOR)) {
            const expected = Number(row.gsm) / factor;
            assert.ok(Math.abs(Number(row[COLUMN[cls]]) - expected) <= (cls === weightClass ? expected * 0.01 : 0.06),
                `${where}: ${COLUMN[cls]} is ${row[COLUMN[cls]]}, ${row.gsm} gsm gives ${expected.toFixed(1)}`);
        }
    });
});

test('api_reference.json: endpoints are well formed and its paper examples are real rows', () => {
    const api = JSON.parse(read('api_reference.json'));
    const keys = api.endpoints.map(endpoint => `${endpoint.method} ${endpoint.path}`);
    assert.equal(new Set(keys).size, keys.length, 'an endpoint is listed twice');
    for (const endpoint of api.endpoints) {
        for (const field of ['name', 'method', 'path', 'parameters', 'response']) {
            assert.ok(field in endpoint, `${endpoint.path}: missing "${field}"`);
        }
    }
});

test('api_reference.json: the examples taken from CSV rows are those rows (generate_api_examples.js)', () => {
    const { execFileSync } = require('node:child_process');
    // Exits 1 with a message if a CSV changed and the file was not regenerated.
    assert.doesNotThrow(() => execFileSync(process.execPath, ['generate_api_examples.js', '--check'], { cwd: ROOT, stdio: 'pipe' }),
        'api_reference.json is out of date: run `node generate_api_examples.js`');
});

test('no file of the project is named in capitals only, except the usual convention names', () => {
    // README.md and friends keep their standard name; every other file is mixed or lower case.
    const CONVENTION = new Set(['README', 'LICENSE', 'CHANGELOG', 'CONTRIBUTING']);
    const offenders = [];
    const walk = (dir) => {
        for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
            if (entry.name === 'node_modules' || entry.name.startsWith('.')) continue;
            const full = path.join(dir, entry.name);
            if (entry.isDirectory()) { walk(full); continue; }
            const stem = entry.name.replace(/\.[^.]*$/, '');
            if (/^[A-Z0-9_-]*[A-Z][A-Z0-9_-]*$/.test(stem) && !CONVENTION.has(stem)) offenders.push(path.relative(ROOT, full));
        }
    };
    walk(ROOT);
    assert.deepEqual(offenders, [], 'rename these (see the note on file names in README.md)');
});

test('the README table lists every CSV with its real number of rows', () => {
    const readme = read('README.md');
    const files = ['countries', 'formats', 'devices', 'specs'].flatMap(dir =>
        fs.readdirSync(path.join(ROOT, dir)).filter(name => name.endsWith('.csv')).map(name => ({ name, dir })));

    const listed = new Map([...readme.matchAll(/^\| `([\w.-]+\.csv)` \| (\d+) \|/gm)].map(m => [m[1], Number(m[2])]));
    for (const { name, dir } of files) {
        assert.ok(listed.has(name), `README.md has no row for ${dir}/${name}`);
        const count = rows(`${dir}/${name}`).length;
        assert.equal(listed.get(name), count, `README.md says ${name} has ${listed.get(name)} rows, it has ${count}`);
    }
});

// ---- CSV columns, importer and schema must name the same things --------------------------

// Columns of a CSV the importer deliberately does not read, with the reason.
const NOT_IMPORTED = {
    'countries/countries_200_complete.csv': ['region', 'primary_language', 'iso_3166_alpha3', 'currency'], // the schema keeps only name, code and measurement system
    'countries/country_compatibility_guide.csv': ['secondary_formats'], // only the first of the alternatives is the primary format
    'formats/book_margins.csv': ['top_inches', 'bottom_inches', 'inner_inches', 'outer_inches'], // the schema stores millimetres only
    'specs/format_equivalence_matrix.csv': ['can_rotate', 'can_fold', 'can_trim'], // no column for them; the level is computed from similarity_percent
    'specs/standards_reference.csv': ['iso_standard', 'iso_number', 'ansi_standard', 'ansi_number', 'jis_standard', 'jis_number',
        'gb_standard', 'gb_number', 'din_standard', 'din_number', 'countries_count'], // read through STANDARD_BODIES, by prefix
    'specs/technical_specifications.csv': ['format_size_mm'], // the size comes from the catalog row of the same format
};

test('import_database.php reads every column of every CSV it loads, except the ones listed here with a reason', () => {
    const php = read('import_database.php');
    const files = [...new Set([...php.matchAll(/'((?:specs|countries|formats)\/[a-z0-9_]+\.csv)'/g)].map(m => m[1]))];
    assert.ok(files.length >= 20, `only ${files.length} CSV files found in the importer`);

    for (const file of files) {
        const headers = read(file).split('\n')[0].split(',').map(h => h.trim());
        const unused = headers.filter(h => !php.includes(`['${h}']`));
        assert.deepEqual(unused.sort(), [...(NOT_IMPORTED[file] ?? [])].sort(),
            `${file}: columns the importer does not read (list them in NOT_IMPORTED with the reason, or read them)`);
    }
});

test('every INSERT of import_database.php names columns that exist in database_schema.sql', () => {
    const php = read('import_database.php');
    const schema = read('database_schema.sql');
    const tables = new Map([...schema.matchAll(/CREATE TABLE (?:IF NOT EXISTS )?(\w+)\s*\(([\s\S]*?)\n\)/g)]
        .map(m => [m[1], new Set(m[2].split('\n').map(line => line.trim().match(/^(\w+)\s+[A-Z]/)?.[1]).filter(Boolean))]));
    assert.ok(tables.size >= 15, `only ${tables.size} tables found in the schema`);

    const inserts = [...php.matchAll(/INSERT (?:IGNORE )?INTO (\w+)\s*\(([^)]*)\)/g)];
    assert.ok(inserts.length >= 12, `only ${inserts.length} INSERTs found in the importer`);
    for (const [, table, columns] of inserts) {
        assert.ok(tables.has(table), `${table} is not a table of database_schema.sql`);
        const missing = columns.split(',').map(c => c.trim()).filter(c => !tables.get(table).has(c));
        assert.deepEqual(missing, [], `${table}: columns the schema does not have`);
    }
});

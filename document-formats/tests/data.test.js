const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const { splitCSVLine, parseCSV, sizeSimilarity } = require('../app/document-formats.js');

const ROOT = path.join(__dirname, '..');
const DATA_DIRS = ['countries', 'formats', 'devices', 'specs'];

// Columns that identify a row, where the first column alone doesn't:
// "A4 ISO" repeats once per country, a fold repeats per source format, etc.
const NATURAL_KEYS = {
    'specs/fold_compatibility.csv': ['source_format', 'fold_type', 'result_format'],
    'specs/format_equivalence_matrix.csv': ['source_format', 'target_format'],
    'specs/minimum_font_sizes.csv': ['format', 'device_type', 'content_type'],
    'devices/pixel_density_guide.csv': ['device_type', 'use_case'],
    'devices/monitors.csv': ['monitor_type', 'screen_size_inches'],
};

const csvFiles = DATA_DIRS.flatMap(dir =>
    fs.readdirSync(path.join(ROOT, dir))
        .filter(name => name.endsWith('.csv'))
        .map(name => `${dir}/${name}`)
);

const read = (file) => fs.readFileSync(path.join(ROOT, file), 'utf8');

const naturalKey = (file, headers) =>
    NATURAL_KEYS[file]
    ?? (headers.includes('format_name') && headers.includes('country') ? ['format_name', 'country'] : [headers[0]]);

test('countries_200_complete.csv only uses the six regions the app knows about', () => {
    const REGIONS = new Set(['Africa', 'Americas', 'Asia-Pacific', 'Europe', 'Middle East', 'Oceania']);
    parseCSV(read('countries/countries_200_complete.csv')).forEach((row, i) => {
        assert.ok(REGIONS.has(row.region), `line ${i + 2}: unknown region "${row.region}" for ${row.country_name}`);
    });
});

test('there are data files to check', () => {
    assert.ok(csvFiles.length >= 30, `only found ${csvFiles.length} CSV files`);
});

for (const file of csvFiles) {
    test(`${file}: every row has as many fields as the header`, () => {
        const [header, ...lines] = read(file).trim().split('\n');
        const width = splitCSVLine(header).length;
        lines.forEach((line, i) => {
            assert.equal(splitCSVLine(line).length, width,
                `line ${i + 2} has ${splitCSVLine(line).length} fields, header has ${width}` +
                ' (a value with a comma must be wrapped in double quotes)');
        });
    });

    test(`${file}: no two rows describe the same thing`, () => {
        const rows = parseCSV(read(file));
        const key = naturalKey(file, Object.keys(rows[0]));
        const seen = new Map();
        rows.forEach((row, i) => {
            const id = key.map(column => row[column]).join(' | ');
            assert.ok(!seen.has(id), `${key.join('+')} "${id}" repeats on lines ${seen.get(id)} and ${i + 2}`);
            seen.set(id, i + 2);
        });
    });

    test(`${file}: millimetres and inches agree`, () => {
        const rows = parseCSV(read(file));
        const pairs = Object.keys(rows[0])
            .filter(column => column.endsWith('_mm'))
            .map(column => [column, column.replace(/_mm$/, '_inches')])
            .filter(([, inches]) => inches in rows[0]);

        rows.forEach((row, i) => {
            for (const [mmColumn, inchColumn] of pairs) {
                const mm = row[mmColumn];
                const inches = row[inchColumn];
                if (typeof mm !== 'number' || typeof inches !== 'number') continue;
                // Inches are often rounded to 0.1" (or to whole inches for roll lengths).
                const tolerance = Math.max(0.06, inches * 0.002);
                assert.ok(Math.abs(mm / 25.4 - inches) <= tolerance,
                    `line ${i + 2}: ${mm} mm is ${(mm / 25.4).toFixed(2)}", but ${inchColumn} says ${inches}"`);
            }
        });
    });
}

// Every sheet size in the catalogs, by name. The master file only repeats
// rows from the others, and book_margins.csv isn't a sheet size.
const catalog = () => {
    const sizes = new Map();
    for (const file of csvFiles) {
        if (/all_formats_master|book_margins/.test(file)) continue;
        const rows = parseCSV(read(file));
        if (!('width_mm' in rows[0])) continue;
        for (const row of rows) {
            if (!sizes.has(row.format_name)) sizes.set(row.format_name, []);
            sizes.get(row.format_name).push(row);
        }
    }
    return sizes;
};

test('the equivalence matrix only names catalog formats and its percentages match their sizes', () => {
    const sizes = catalog();
    parseCSV(read('specs/format_equivalence_matrix.csv')).forEach((row, i) => {
        const where = `line ${i + 2} (${row.source_format} → ${row.target_format})`;
        const source = sizes.get(row.source_format);
        const target = sizes.get(row.target_format);
        assert.ok(source, `${where}: no catalog format called "${row.source_format}"`);
        assert.ok(target, `${where}: no catalog format called "${row.target_format}"`);

        // The same name can appear with sizes a millimetre apart (139.7 vs 140).
        const possible = source.flatMap(s => target.map(t => Math.round(sizeSimilarity(s, t))));
        const percent = Number(row.similarity_percent);
        assert.ok(percent <= 100, `${where}: ${percent}% is over 100`);
        assert.ok(possible.some(p => Math.abs(p - percent) <= 1),
            `${where}: says ${percent}%, the sizes give ${[...new Set(possible)].join('/')}%`);

        if (row.compatibility_level === 'Folded') {
            assert.equal(row.can_fold, 'Yes', `${where}: a Folded pair must have can_fold=Yes`);
        }
    });
});

test('the standards reference only names catalog formats', () => {
    const sizes = catalog();
    parseCSV(read('specs/standards_reference.csv')).forEach((row, i) => {
        assert.ok(sizes.has(row.format_name), `line ${i + 2}: no catalog format called "${row.format_name}"`);
    });
});

test('every master-file row copies a row from another catalog', () => {
    const sizes = catalog();
    parseCSV(read('formats/all_formats_master.csv')).forEach((row, i) => {
        const matches = (sizes.get(row.format_name) ?? [])
            .filter(r => r.width_mm === row.width_mm && r.height_mm === row.height_mm);
        assert.ok(matches.length > 0,
            `line ${i + 2}: "${row.format_name}" ${row.width_mm}×${row.height_mm} mm is in no other file`);
    });
});

test('format catalogs have positive numeric dimensions', () => {
    for (const file of csvFiles) {
        const rows = parseCSV(read(file));
        if (!('width_mm' in rows[0])) continue;
        rows.forEach((row, i) => {
            for (const column of ['width_mm', 'height_mm']) {
                assert.ok(typeof row[column] === 'number' && row[column] > 0,
                    `${file} line ${i + 2}: ${column} is ${JSON.stringify(row[column])}`);
            }
        });
    }
});

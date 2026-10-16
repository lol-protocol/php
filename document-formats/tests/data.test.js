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

test('paper_types.csv has at least 50 types with a known category and a gsm range', () => {
    const CATEGORIES = new Set([
        'Writing/Printing', 'Coated', 'Cardstock', 'Decorative', 'Recycled',
        'Specialty', 'Technical', 'Photo', 'Packaging',
    ]);
    const rows = parseCSV(read('specs/paper_types.csv'));
    assert.ok(rows.length >= 50, `only ${rows.length} paper types`);
    rows.forEach((row, i) => {
        assert.ok(CATEGORIES.has(row.category), `line ${i + 2} (${row.type_name}): unknown category "${row.category}"`);
        assert.match(row.weight_gsm_range, /^\d+-\d+$/, `line ${i + 2} (${row.type_name}): weight_gsm_range "${row.weight_gsm_range}" isn't "NN-NN"`);
    });
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
        const copied = ['width_inches', 'height_inches', 'country', 'category', 'description'];
        assert.ok(matches.some(r => copied.every(column => r[column] === row[column])),
            `line ${i + 2}: "${row.format_name}" differs from its source in ` +
            copied.filter(column => matches.every(r => r[column] !== row[column])).join(', '));
    });
});

test('fold_compatibility.csv: the folded size is the source divided as the fold says', () => {
    // Number of parts each side is split into, by fold type. A half or a third folds one
    // side (either one: the file lists some results upright and some on their side).
    const parts = { 'Half Vertical': 2, 'Half Horizontal': 2, 'Half': 2, 'Thirds': 3 };
    const upright = ([a, b]) => [Math.min(a, b), Math.max(a, b)];
    parseCSV(read('specs/fold_compatibility.csv')).forEach((row, i) => {
        const w = row.source_mm_w, h = row.source_mm_h;
        const candidates = row.fold_type === 'Quarters' ? [[w / 2, h / 2]]
            : parts[row.fold_type] ? [[w / parts[row.fold_type], h], [w, h / parts[row.fold_type]]] : null;
        assert.ok(candidates, `line ${i + 2}: unknown fold type "${row.fold_type}"`);
        const got = upright([row.result_mm_w, row.result_mm_h]);
        // Sizes are rounded to the millimetre (A4 in half is 148.5, listed as 148 or 148.5).
        assert.ok(candidates.some(c => upright(c).every((v, k) => Math.abs(v - got[k]) <= 1)),
            `line ${i + 2}: ${row.source_format} "${row.fold_type}" should give ` +
            `${candidates.map(c => upright(c).map(v => +v.toFixed(2)).join('×')).join(' or ')} mm, the file says ${got.join('×')}`);
    });
});

test('video_resolutions.csv: the aspect ratio agrees with the pixel counts', () => {
    parseCSV(read('devices/video_resolutions.csv')).forEach((row, i) => {
        const [a, b] = String(row.aspect_ratio).split(':').map(Number);
        const label = a / b, pixels = row.width_pixels / row.height_pixels;
        assert.ok(Math.abs(label - pixels) / pixels < 0.04,
            `line ${i + 2}: ${row.resolution_name} says ${row.aspect_ratio} (${label.toFixed(2)}), ` +
            `${row.width_pixels}×${row.height_pixels} px is ${pixels.toFixed(2)}`);
    });
});

// Screens: the numbers of one row have to agree with each other. The size in inches is the
// physical size of the screen for monitors and of the whole device (its body) for phones,
// tablets and e-readers; resolutions follow the orientation each maker lists.
const deviceRows = (name) => parseCSV(read(`devices/${name}.csv`)).filter(row =>
    ['screen_size_inches', 'resolution_width', 'resolution_height', 'ppi'].every(column => typeof row[column] === 'number'));
const deviceName = (row) => row.device_name ?? row.monitor_type;

test('devices: ppi agrees with the resolution and the screen size', () => {
    for (const file of ['smartphones', 'tablets', 'ereaders', 'monitors']) {
        const rows = deviceRows(file);
        assert.ok(rows.length > 10, `${file}.csv has only ${rows.length} rows with numbers`);
        rows.forEach(row => {
            const computed = Math.hypot(row.resolution_width, row.resolution_height) / row.screen_size_inches;
            assert.ok(Math.abs(computed - row.ppi) / row.ppi <= 0.015,
                `${file}.csv "${deviceName(row)}": says ${row.ppi} ppi, ${row.resolution_width}×${row.resolution_height} px on ` +
                `${row.screen_size_inches}" is ${computed.toFixed(0)}`);
        });
    }
});

test('devices: the aspect ratio agrees with the resolution', () => {
    for (const file of ['smartphones', 'tablets', 'ereaders', 'monitors']) {
        deviceRows(file).forEach(row => {
            const [a, b] = String(row.aspect_ratio).split(':').map(Number);
            const label = Math.max(a / b, b / a);
            const pixels = Math.max(row.resolution_width, row.resolution_height) / Math.min(row.resolution_width, row.resolution_height);
            assert.ok(Math.abs(label - pixels) / pixels <= 0.04,
                `${file}.csv "${deviceName(row)}": says ${row.aspect_ratio} (${label.toFixed(2)}), ` +
                `${row.resolution_width}×${row.resolution_height} px is ${pixels.toFixed(2)}`);
        });
    }
});

test('devices: a phone, tablet or e-reader is bigger than its screen and lies like its resolution', () => {
    for (const file of ['smartphones', 'tablets', 'ereaders']) {
        deviceRows(file).forEach(row => {
            const diagonal = Math.hypot(row.width_inches, row.height_inches);
            assert.ok(diagonal >= row.screen_size_inches,
                `${file}.csv "${row.device_name}": ${row.width_inches}×${row.height_inches}" is ${diagonal.toFixed(1)}" corner to corner, ` +
                `smaller than its ${row.screen_size_inches}" screen`);
            assert.equal(row.width_inches < row.height_inches, row.resolution_width < row.resolution_height,
                `${file}.csv "${row.device_name}": the size and the resolution are listed in different orientations`);
        });
    }
});

test('monitors: width and height are those of the screen', () => {
    deviceRows('monitors').forEach(row => {
        const diagonal = Math.hypot(row.width_inches, row.height_inches);
        assert.ok(Math.abs(diagonal - row.screen_size_inches) / row.screen_size_inches <= 0.015,
            `monitors.csv "${row.monitor_type}": ${row.width_inches}×${row.height_inches}" is ${diagonal.toFixed(1)}", ` +
            `not ${row.screen_size_inches}"`);
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

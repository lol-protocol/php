#!/usr/bin/env node
/**
 * Keeps the examples of api_reference.json that come from CSV rows equal to those rows.
 *
 *   node generate_api_examples.js          rewrites api_reference.json
 *   node generate_api_examples.js --check  exits 1 if the file is out of date (the tests run this)
 *
 * Each example names its row (type_name, finish_name, weight_name); every field the example
 * has and the row also has is copied from the CSV. Ids, pagination and the rest are left as written.
 */
const fs = require('node:fs');
const path = require('node:path');
const { parseCSV } = require('./app/document-formats.js');

const API_FILE = path.join(__dirname, 'api_reference.json');
const rows = (file) => parseCSV(fs.readFileSync(path.join(__dirname, file), 'utf8'));

// "60-300" -> weight_gsm_min 60, weight_gsm_max 300 (a single number is both).
const withRange = (row) => {
    const [min, max = min] = String(row.weight_gsm_range ?? '').split('-').map(Number);
    return 'weight_gsm_range' in row ? { ...row, weight_gsm_min: min, weight_gsm_max: max } : row;
};

// Where each CSV-backed example lives: endpoint path, how to reach the example, the row's file and key.
const EXAMPLES = [
    { path: '/paper-types', pick: (body) => body.data[0], file: 'specs/paper_types.csv', key: 'type_name' },
    { path: '/paper-types/{paper_type_id}', pick: (body) => body.data, file: 'specs/paper_types.csv', key: 'type_name' },
    { path: '/finishes', pick: (body) => body.finishes[0], file: 'specs/finish_types.csv', key: 'finish_name' },
    { path: '/paper-weights', pick: (body) => body.weights[0], file: 'specs/paper_weight_standards.csv', key: 'weight_name' },
];

const refresh = (api) => {
    for (const { path: endpointPath, pick, file, key } of EXAMPLES) {
        const endpoint = api.endpoints.find(e => e.path === endpointPath);
        if (!endpoint) throw new Error(`api_reference.json has no endpoint ${endpointPath}`);
        const example = pick(endpoint.response.body);
        const row = rows(file).map(withRange).find(candidate => candidate[key] === example[key]);
        if (!row) throw new Error(`${endpointPath}: no row of ${file} has ${key} "${example[key]}"`);
        for (const field of Object.keys(example)) {
            if (field in row) example[field] = typeof example[field] === 'number' ? Number(row[field]) : String(row[field]);
        }
    }
    return api;
};

const render = (api) => JSON.stringify(api, null, 2) + '\n';

if (require.main === module) {
    const current = fs.readFileSync(API_FILE, 'utf8');
    const updated = render(refresh(JSON.parse(current)));
    if (process.argv.includes('--check')) {
        if (updated !== current) {
            console.error('api_reference.json is out of date: run `node generate_api_examples.js`');
            process.exit(1);
        }
        console.log('api_reference.json is up to date');
    } else {
        fs.writeFileSync(API_FILE, updated);
        console.log(updated === current ? 'api_reference.json already up to date' : 'api_reference.json updated');
    }
}

module.exports = { refresh, render, withRange };

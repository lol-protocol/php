const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

globalThis.localStorage = { getItem: () => null, setItem: () => {} };
const library = require('../app/document-formats.js');

const ROOT = path.join(__dirname, '..');
const DOCS = ['README.md', ...fs.readdirSync(path.join(ROOT, 'docs')).filter(f => f.endsWith('.md')).map(f => `docs/${f}`)];

const javascriptBlocks = (file) => {
    const blocks = [];
    const text = fs.readFileSync(path.join(ROOT, file), 'utf8');
    for (const match of text.matchAll(/^```(?:javascript|js)\n([\s\S]*?)^```/gm)) {
        blocks.push({ file, line: text.slice(0, match.index).split('\n').length + 1, code: match[1] });
    }
    return blocks;
};
const blocks = DOCS.flatMap(javascriptBlocks);

// Examples in the docs are not run (they fetch files and touch the page), but they
// must still be real JavaScript that names real methods of the library.

test('the docs have JavaScript examples to check', () => {
    assert.ok(blocks.length >= 30, `only ${blocks.length} JavaScript blocks found`);
});

test('every JavaScript example in README.md and docs/ is valid JavaScript', () => {
    const broken = [];
    for (const { file, line, code } of blocks) {
        try {
            new vm.Script(`(async () => {\n${code}\n})`);
        } catch (error) {
            broken.push(`${file}:${line}: ${error.message}`);
        }
    }
    assert.deepEqual(broken, []);
});

test('the methods the examples call on library classes exist', () => {
    const has = (Class, name) => name in Class || name in Class.prototype;
    const missing = [];
    for (const { file, line, code } of blocks) {
        // const loader = new FormatLoader(...)   ->   loader.load(...) must be a method of FormatLoader.
        const instances = new Map();
        for (const m of code.matchAll(/(?:const|let|var)\s+(\w+)\s*=\s*(?:await\s+)?new\s+(\w+)\(/g)) {
            if (typeof library[m[2]] === 'function') instances.set(m[1], m[2]);
        }
        for (const [name, className] of instances) {
            for (const call of code.matchAll(new RegExp(`\\b${name}\\.(\\w+)\\(`, 'g'))) {
                if (!has(library[className], call[1])) missing.push(`${file}:${line}: ${className} has no ${call[1]}()`);
            }
        }
        // LocalizationManager.lookup(...), ScreenDeviceManager.TYPE_CONFIG... called on the class itself.
        for (const m of code.matchAll(/\b([A-Z]\w+)\.(\w+)\(/g)) {
            if (typeof library[m[1]] === 'function' && !has(library[m[1]], m[2]) && !(m[2] in Function.prototype)) {
                missing.push(`${file}:${line}: ${m[1]} has no ${m[2]}()`);
            }
        }
    }
    assert.deepEqual([...new Set(missing)], []);
});

test('no example declares a class with the name of a library class', () => {
    // A guide that writes its own FormatConverter would teach an interface the library doesn't have.
    const clashes = blocks.flatMap(({ file, line, code }) => [...code.matchAll(/\bclass\s+([A-Z]\w*)/g)]
        .filter(m => typeof library[m[1]] === 'function')
        .map(m => `${file}:${line}: class ${m[1]}`));
    assert.deepEqual(clashes, []);
});

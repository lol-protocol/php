import { test } from "node:test";
import assert from "node:assert/strict";
import { formatDuration, formatFileSize, formatPct } from "../../interfaz/js/formato.js";

test("formatDuration: milisegundos por debajo de 1s", () => {
  assert.equal(formatDuration(850), "850 ms");
});

test("formatDuration: segundos con un decimal", () => {
  assert.equal(formatDuration(4200), "4.2 s");
});

test("formatDuration: minutos y segundos combinados", () => {
  assert.equal(formatDuration(125_000), "2 m 5 s");
});

test("formatFileSize: kilobytes con un decimal", () => {
  assert.equal(formatFileSize(512), "512.0 KB");
});

test("formatFileSize: pasa a megabytes a partir de 1024 KB", () => {
  assert.equal(formatFileSize(2048), "2.0 MB");
});

test("formatPct: agrega signo + en valores positivos", () => {
  assert.equal(formatPct(12.4), "+12%");
});

test("formatPct: no duplica el signo - en valores negativos", () => {
  assert.equal(formatPct(-8.6), "-9%");
});

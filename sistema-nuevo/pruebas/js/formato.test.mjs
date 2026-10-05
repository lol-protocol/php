import { test } from "node:test";
import assert from "node:assert/strict";

// formato.js importa nucleo.js, que lee window.location.hostname para armar
// API_BASE; Node no trae esa API del navegador, así que se simula antes del import.
globalThis.window = { location: { hostname: "localhost" } };

const { formatDuration, formatFileSize, formatPct, classifyDelta } = await import("../../interfaz/js/formato.js");

test("formatDuration: milisegundos por debajo de 1s", () => {
  assert.equal(formatDuration(850), "850 ms");
});

test("formatDuration: segundos con un decimal", () => {
  assert.equal(formatDuration(4200), "4.2 s");
});

test("formatDuration: minutos y segundos combinados", () => {
  assert.equal(formatDuration(125_000), "2 m 5 s");
});

test("formatDuration: el resto de segundos que redondea a 60 acarrea el minuto extra", () => {
  assert.equal(formatDuration(119_500), "2 m 0 s"); // antes: "1 m 60 s"
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

// classifyDelta decide el color de cada badge de comparación (verde / rojo / gris).
test("classifyDelta: sin delta (null) no hay comparación", () => {
  assert.equal(classifyDelta(null), "none");
});

test("classifyDelta: dentro de ±10% es 'en el promedio', con el borde incluido", () => {
  for (const pct of [0, 4.9, -4.9, 10, -10]) {
    assert.equal(classifyDelta(pct), "avg", `delta ${pct}`);
  }
});

test("classifyDelta: pasado el 10%, menos que el promedio es bueno y más es malo", () => {
  assert.equal(classifyDelta(10.01), "bad"); // más lento / más caro
  assert.equal(classifyDelta(250), "bad");
  assert.equal(classifyDelta(-10.01), "good"); // más rápido / más barato
  assert.equal(classifyDelta(-80), "good");
});

test("classifyDelta: con betterWhenLower=false el sentido se invierte, la franja del 10% no", () => {
  assert.equal(classifyDelta(25, false), "good");
  assert.equal(classifyDelta(-25, false), "bad");
  assert.equal(classifyDelta(10, false), "avg");
  assert.equal(classifyDelta(null, false), "none");
});

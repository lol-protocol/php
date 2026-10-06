import { test } from "node:test";
import assert from "node:assert/strict";
import "./navegador-falso.mjs"; // simula localStorage y window.location, que los módulos de interfaz/js/ leen al importarse

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

test("formatPct: un delta que redondea a cero se muestra 0%, sin signo (nada de -0% ni +0%)", () => {
  for (const pct of [0, 0.3, -0.3, 0.49, -0.49]) {
    assert.equal(formatPct(pct), "0%", `delta ${pct}`);
  }
});

test("formatPct: la mitad redondea hacia afuera, como siempre (2.5 -> +3%, -2.5 -> -3%)", () => {
  assert.equal(formatPct(2.5), "+3%");
  assert.equal(formatPct(-2.5), "-3%");
  assert.equal(formatPct(0.5), "+1%");
  assert.equal(formatPct(-0.5), "-1%");
});

// classifyDelta decide el color de cada badge de comparación (verde / rojo / gris).
test("classifyDelta: sin delta (null) no hay comparación", () => {
  assert.equal(classifyDelta(null), "none");
});

// La franja se mide sobre el porcentaje TAL COMO SE MUESTRA (entero, ver formatPct): un badge que dice
// "+10%" tiene que ser "en el promedio", no rojo, porque el manual dice que ±10% lo es.
test("classifyDelta: dentro de ±10% es 'en el promedio', con el borde incluido y lo que redondea a él", () => {
  for (const pct of [0, 4.9, -4.9, 10, -10, 10.4, -10.4]) {
    assert.equal(classifyDelta(pct), "avg", `delta ${pct}`);
  }
});

test("classifyDelta: pasado el 10%, menos que el promedio es bueno y más es malo", () => {
  assert.equal(classifyDelta(10.5), "bad"); // se muestra +11%: más lento / más caro
  assert.equal(classifyDelta(250), "bad");
  assert.equal(classifyDelta(-10.5), "good"); // se muestra -11%: más rápido / más barato
  assert.equal(classifyDelta(-80), "good");
});

test("classifyDelta: lo que se muestra y el color nunca se contradicen", () => {
  for (let delta = -30; delta <= 30; delta += 0.1) {
    const mostrado = parseInt(formatPct(delta), 10); // "+10%" -> 10, "0%" -> 0, "-11%" -> -11
    assert.equal(classifyDelta(delta) === "avg", Math.abs(mostrado) <= 10, `delta ${delta.toFixed(1)} se muestra ${formatPct(delta)}`);
  }
});

test("classifyDelta: con betterWhenLower=false el sentido se invierte, la franja del 10% no", () => {
  assert.equal(classifyDelta(25, false), "good");
  assert.equal(classifyDelta(-25, false), "bad");
  assert.equal(classifyDelta(10, false), "avg");
  assert.equal(classifyDelta(null, false), "none");
});

import { test } from "node:test";
import assert from "node:assert/strict";

// tarjeta-usuario.js importa idioma.js (lee localStorage al importarse) y nucleo.js (lee window.location).
globalThis.localStorage = { getItem: () => null, setItem: () => {} };
globalThis.window = { location: { hostname: "localhost" } };

const { mostrarAviso, renderStatusMessage } = await import("../../interfaz/js/tarjeta-usuario.js");
const { t } = await import("../../interfaz/js/idioma.js");

/** El aviso de arriba del timeline (#status-message): oculto y sin estilo de advertencia hasta que se lo muestra. */
const caja = { hidden: true, className: "status-message", textContent: "" };
globalThis.document = { getElementById: (id) => (id === "status-message" ? caja : null) };
const reiniciar = () => Object.assign(caja, { hidden: true, className: "status-message", textContent: "" });

test("mostrarAviso muestra el texto con el estilo de advertencia", () => {
  reiniciar();
  mostrarAviso("⚠ no se pudo cargar");
  assert.equal(caja.hidden, false);
  assert.equal(caja.className, "status-message status-message--warning");
  assert.equal(caja.textContent, "⚠ no se pudo cargar");
});

test("mostrarAviso reemplaza el aviso anterior en vez de sumarse a él", () => {
  reiniciar();
  mostrarAviso("primero");
  mostrarAviso("segundo");
  assert.equal(caja.textContent, "segundo");
});

test("renderStatusMessage: con el servicio de estadísticas disponible, el aviso se oculta", () => {
  reiniciar();
  mostrarAviso("quedó de antes");
  renderStatusMessage(true);
  assert.equal(caja.hidden, true);
});

test("renderStatusMessage: sin el servicio de estadísticas, muestra el aviso traducido con el mismo estilo", () => {
  reiniciar();
  renderStatusMessage(false);
  assert.equal(caja.hidden, false);
  assert.equal(caja.className, "status-message status-message--warning");
  assert.equal(caja.textContent, t("status_stats_unavailable"));
  assert.ok(caja.textContent.length > 10, "el texto no puede quedar vacío ni ser solo la clave");
});

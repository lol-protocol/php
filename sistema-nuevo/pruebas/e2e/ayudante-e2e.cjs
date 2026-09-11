// Ayudantes compartidos por las pruebas e2e. CommonJS a propósito: bajo ESM,
// Node no resuelve paquetes globales vía NODE_PATH (solo lo hace require()),
// y Playwright acá solo está instalado global (sin node_modules propio).
const assert = require("node:assert/strict");

const BASE_URL = "http://localhost:8082";

let totalPasos = 0;
let pasosFallidos = 0;

/** Corre un paso de la prueba; no corta la corrida si falla, pero lo cuenta. */
async function paso(nombre, fn) {
  totalPasos++;
  try {
    await fn();
    console.log(`✓ ${nombre}`);
  } catch (err) {
    pasosFallidos++;
    console.error(`✗ FALLÓ: ${nombre}\n    ${err.message}`);
  }
}

/** @return {number} código de salida: 0 si todo pasó, 1 si hubo fallas. */
function resumenPasos() {
  console.log(`\n${totalPasos - pasosFallidos}/${totalPasos} pasos OK`);
  return pasosFallidos === 0 ? 0 : 1;
}

async function iniciarSesion(page) {
  await page.goto(BASE_URL);
  await page.fill("#login-username", "admin");
  await page.fill("#login-password", "admin123");
  await page.click("#login-form button[type=submit]");
  await page.waitForSelector("#app:not([hidden])");
  await page.waitForTimeout(500);
}

module.exports = { assert, BASE_URL, paso, resumenPasos, iniciarSesion };

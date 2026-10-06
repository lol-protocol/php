// Ayudantes compartidos por las pruebas e2e. CommonJS a propósito: bajo ESM,
// Node no resuelve paquetes globales vía NODE_PATH (solo lo hace require()),
// y Playwright acá solo está instalado global (sin node_modules propio).
const assert = require("node:assert/strict");
const { execSync, execFileSync } = require("node:child_process");

const BASE_URL = "http://localhost:8082"; // el panel (interfaz/)
const API_URL = "http://localhost:8000"; // la API PHP
const JAVA_URL = "http://localhost:8081"; // el servicio de estadísticas

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

/** Llena el formulario de login y lo envía: la página ya tiene que estar en la pantalla de login. */
async function enviarLogin(page, clave = "admin123") {
  await page.fill("#login-username", "admin");
  await page.fill("#login-password", clave);
  await page.click("#login-form button[type=submit]");
}

async function iniciarSesion(page) {
  await page.goto(BASE_URL);
  await enviarLogin(page);
  await page.waitForSelector("#app:not([hidden])");
  await page.waitForTimeout(500);
}

/**
 * Abre Chromium, corre cuerpo({ browser, page }) y lo cierra siempre, también si el cuerpo falla. Por defecto la página ya
 * tiene la sesión iniciada en el panel; con { entrar: false } queda sin abrir, para probar el login mismo.
 */
async function conNavegador(cuerpo, { entrar = true } = {}) {
  const { chromium } = require("playwright"); // acá y no arriba: peticiones-api.e2e.cjs usa este archivo sin navegador
  const browser = await chromium.launch();
  try {
    const page = await browser.newPage();
    if (entrar) await iniciarSesion(page);
    return await cuerpo({ browser, page });
  } finally {
    await browser.close();
  }
}

/** Una prueba e2e entera: conNavegador(cuerpo) y sale con el código de los pasos (0 si todos pasaron). */
async function correrPrueba(cuerpo, opciones) {
  await conNavegador(cuerpo, opciones);
  process.exit(resumenPasos());
}

/** Una página en un contexto nuevo con la interfaz en inglés (el idioma elegido se guarda en localStorage). */
async function paginaEnIngles(browser) {
  const contexto = await browser.newContext();
  await contexto.addInitScript(() => localStorage.setItem("backoffice_idioma", "en"));
  return { page: await contexto.newPage(), cerrar: () => contexto.close() };
}

/** Conexión a PostgreSQL con las mismas variables BACKOFFICE_BD_* (y defaults) que ConexionBd.php. */
function conexionPsql() {
  return {
    env: { ...process.env, PGPASSWORD: process.env.BACKOFFICE_BD_CLAVE || "backoffice_dev_2026" },
    host: process.env.BACKOFFICE_BD_HOST || "localhost",
    puerto: process.env.BACKOFFICE_BD_PUERTO || "5432",
    usuario: process.env.BACKOFFICE_BD_USUARIO || "backoffice_app",
    nombre: process.env.BACKOFFICE_BD_NOMBRE || "backoffice",
  };
}

/** Corre una sentencia SQL con psql: para preparar o limpiar estado. */
function ejecutarSql(sql) {
  const { env, host, puerto, usuario, nombre } = conexionPsql();
  execSync(`psql -h ${host} -p ${puerto} -U ${usuario} -d ${nombre} -c "${sql}"`, { env, stdio: "pipe" });
}

/**
 * Corre una consulta con psql y devuelve las filas como arrays de strings (NULL -> null): para usar
 * la base como oráculo de lo que devuelven los servicios. execFileSync, sin shell de por medio, así
 * que el SQL puede llevar comillas y saltos de línea.
 */
function consultarSql(sql) {
  const { env, host, puerto, usuario, nombre } = conexionPsql();
  const salida = execFileSync(
    "psql",
    ["-h", host, "-p", puerto, "-U", usuario, "-d", nombre, "-tA", "-F", "|", "-P", "null=NULL", "-c", sql],
    { env, encoding: "utf8" }
  );
  return salida
    .split("\n")
    .filter((fila) => fila !== "")
    .map((fila) => fila.split("|").map((celda) => (celda === "NULL" ? null : celda)));
}

module.exports = {
  assert, BASE_URL, API_URL, JAVA_URL,
  paso, resumenPasos, enviarLogin, iniciarSesion, conNavegador, correrPrueba, paginaEnIngles,
  ejecutarSql, consultarSql,
};

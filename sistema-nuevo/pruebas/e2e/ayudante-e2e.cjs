// Ayudantes compartidos por las pruebas e2e. CommonJS a propósito: bajo ESM,
// Node no resuelve paquetes globales vía NODE_PATH (solo lo hace require()),
// y Playwright acá solo está instalado global (sin node_modules propio).
const assert = require("node:assert/strict");
const { execSync, execFileSync } = require("node:child_process");

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

module.exports = { assert, BASE_URL, paso, resumenPasos, iniciarSesion, ejecutarSql, consultarSql };

#!/usr/bin/env node
// Comprueba que las copias que privacy-laws/ y copyright-laws/ mantienen a propósito idénticas lo siguen
// siendo (ver «Lo que sigue duplicado» en leyes-comunes/README.md): la web se sirve como archivos
// estáticos, sin paso de build, así que no se pueden compartir, y ya se desincronizaron una vez (#140).
// Sin dependencias: solo Node. Uso, desde la raíz del repositorio:
//
//   node leyes-comunes/comprobar-copias.js
//
// Para añadir una copia nueva, ponla en COPIAS. Si una deja de ser igual a propósito, quítala de la lista
// y dilo en el README de esta carpeta.
import { existsSync, readFileSync } from "node:fs";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const RAIZ = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const MODULOS = ["privacy-laws", "copyright-laws"];
export const COPIAS = [
  "app/lib/chart.js",
  "app/lib/filters.js",
  "app/lib/util.js",
  "app/styles/base.css",
  "app/styles/comparison.css",
  "app/styles/search.css",
  "tests/helpers/php-server.js",
  "tests/fixtures/law-page.html",
];

export function comprobar(raiz = RAIZ, copias = COPIAS) {
  const problemas = [];
  for (const ruta of copias) {
    const [a, b] = MODULOS.map((m) => join(raiz, m, ruta));
    const faltan = MODULOS.filter((m) => !existsSync(join(raiz, m, ruta)));
    if (faltan.length) {
      problemas.push(`${ruta}: falta en ${faltan.join(" y ")}`);
    } else if (!readFileSync(a).equals(readFileSync(b))) {
      problemas.push(`${ruta}: ya no es igual en ${MODULOS.join(" y ")}`);
    }
  }
  return problemas;
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const problemas = comprobar();
  if (problemas.length) {
    console.error("Copias que se separaron:\n" + problemas.map((p) => `  - ${p}`).join("\n"));
    console.error("\nCambia las dos a la vez, o quita la copia de COPIAS en leyes-comunes/comprobar-copias.js y explícalo en el README de esa carpeta.");
    process.exit(1);
  }
  console.log(`${COPIAS.length} copias, todas iguales en ${MODULOS.join(" y ")}.`);
}

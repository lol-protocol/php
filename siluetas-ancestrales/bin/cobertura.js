#!/usr/bin/env node
// Informe de cobertura del catálogo por país, ordenado por la prioridad genealógica de Listado-paises/priorizacion.csv.
//   node bin/cobertura.js                      informe de los países no excluidos
//   node bin/cobertura.js --estado rojo        solo los países sin cobertura
//   node bin/cobertura.js --todos --desde 1500 --hasta 1900 -o cobertura.md
//   node bin/cobertura.js --csv ruta/priorizacion.csv

import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { leerPrioridades, informeDeCobertura, DESDE, HASTA } from '../src/cobertura.js';

const RAIZ = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const args = process.argv.slice(2);
const val = (n) => { const i = args.findIndex((a) => a === `--${n}` || (n === 'o' && a === '-o')); return i >= 0 ? args[i + 1] : undefined; };
const flag = (n) => args.includes(`--${n}`);

if (flag('ayuda') || flag('help')) {
  console.log(`Uso: node bin/cobertura.js [--csv RUTA] [--estado verde|amarillo|rojo] [--todos] [--desde ${DESDE}] [--hasta ${HASTA}] [-o archivo.md]
  --csv      priorizacion.csv de Listado-paises (por defecto ../Listado-paises/priorizacion.csv)
  --todos    incluye los países excluidos de la prioridad genealógica`);
  process.exit(0);
}

try {
  const csv = val('csv') ?? join(RAIZ, '..', 'Listado-paises', 'priorizacion.csv');
  let texto;
  try { texto = readFileSync(csv, 'utf8'); } catch { throw new Error(`No se pudo leer ${csv}. Pasa la ruta con --csv (el archivo está en Listado-paises/).`); }
  const mapa = JSON.parse(readFileSync(join(RAIZ, 'datos', 'paises-regiones.json'), 'utf8')).paises;
  const estado = val('estado');
  if (estado !== undefined && !['verde', 'amarillo', 'rojo'].includes(estado)) throw new Error(`Estado no válido: ${JSON.stringify(estado)}. Valores posibles: verde, amarillo, rojo.`);
  const num = (n, por) => { const v = val(n) === undefined ? por : Number(val(n)); if (!Number.isFinite(v)) throw new Error(`--${n} debe ser un número.`); return v; };
  const informe = informeDeCobertura(leerPrioridades(texto), mapa, { todos: flag('todos'), estado, desde: num('desde', DESDE), hasta: num('hasta', HASTA) });
  const salida = val('o');
  if (salida) { writeFileSync(salida, informe); console.error(`Escrito ${salida}`); } else process.stdout.write(informe);
} catch (e) { console.error(`Error: ${e.message}`); process.exit(2); }

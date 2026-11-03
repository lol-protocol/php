#!/usr/bin/env node
// CLI: genera una silueta y la escribe en un archivo SVG o la imprime.
//   node bin/generar.js --semilla 7 --atuendo huaso -o huaso.svg
//   node bin/generar.js --region andes --anio 1500 --sexo f --edad 30
//   node bin/generar.js --nueva --estilo festivo --semilla 12 > nueva.svg
//   node bin/generar.js --listar atuendos|regiones|prendas|tintes

import { writeFileSync } from 'node:fs';
import { generarSVG, generarPersona, generarPersonaConRopaNueva, ATUENDOS, PRENDAS, TINTES, REGIONES, ETNIAS, listarCatalogo } from '../src/index.js';

const args = process.argv.slice(2);
const val = (n) => { const i = args.findIndex((a) => a === `--${n}` || (n === 'o' && a === '-o')); return i >= 0 ? args[i + 1] : undefined; };
const flag = (n) => args.includes(`--${n}`);

if (flag('ayuda') || flag('help')) {
  console.log(`Uso: node bin/generar.js [opciones]
  --semilla N        misma semilla, misma persona (por defecto 1)
  --atuendo ID       atuendo del catálogo (--listar atuendos)
  --region ID --anio N --clase baja|media|alta
  --etnia ID[,ID2]   pueblo o cultura; con ella se estiman región, piel, cabello, peinado, barba y estatura (--listar etnias)
  --saludo derecha|izquierda|ninguno   mano en saludo (por defecto derecha: la de la persona, a la izquierda de quien mira)
  --avisos           imprime en la salida de error lo que se estimó y los filtros que hubo que aflojar
  --sexo f|m  --edad N  --corpulencia delgada|media|robusta|corpulenta  --altura CM
  --postura firme|relajada|abierta  --piel ID|#hex  --cabello ID|#hex
  --detalle minimo|medio|alto  --rasgos  --fondo '#efe9dd'
  --nueva [--estilo sobrio|trabajo|festivo|ceremonial] [--esquema analogo|complementario|triada|monocromo|tierra|festivo]
  --listar atuendos|regiones|etnias|prendas|tintes|resumen
  -o archivo.svg`);
  process.exit(0);
}
const listar = val('listar');
if (listar) {
  const f = { atuendos: () => ATUENDOS.map((a) => `${a.id}\t${a.sexo}\t${a.region}\t${a.epoca.join('..')}\t${a.nombre}`),
    etnias: () => ETNIAS.map((e) => `${e.id}\t${e.grupo}\t${e.nombre}`),
    regiones: () => Object.entries(REGIONES).map(([k, v]) => `${k}\t${v}`),
    prendas: () => PRENDAS.map((p) => `${p.id}\t${p.ranura}\t${p.nombre}`),
    tintes: () => TINTES.map((t) => `${t.id}\t${t.hex}\t${t.nombre}`),
    resumen: () => Object.entries(listarCatalogo()).map(([k, v]) => `${k}\t${v}`) }[listar];
  if (!f) { console.error('Nada que listar con ese nombre.'); process.exit(2); }
  console.log(f().join('\n'));
  process.exit(0);
}

const num = (n) => (val(n) === undefined ? undefined : Number(val(n)));
const opc = {
  semilla: val('semilla') === undefined ? 1 : (Number.isNaN(Number(val('semilla'))) ? val('semilla') : Number(val('semilla'))),
  atuendo: val('atuendo'), region: val('region'), anio: num('anio'), clase: val('clase'), sexo: val('sexo'), edad: num('edad'),
  corpulencia: val('corpulencia'), altura: num('altura'), postura: val('postura'), piel: val('piel'), cabello: val('cabello'),
  detalle: val('detalle'), rasgos: flag('rasgos'), fondo: val('fondo'),
  ropaNueva: flag('nueva'), estilo: val('estilo'), esquema: val('esquema'),
  etnia: val('etnia'), saludo: val('saludo'),
};
for (const k of Object.keys(opc)) if (opc[k] === undefined) delete opc[k];
const svg = generarSVG(opc);
if (flag('avisos')) {
  const p = opc.ropaNueva ? generarPersonaConRopaNueva(opc) : generarPersona(opc);
  for (const t of [...(p.estimaciones ?? []), ...(p.avisos ?? []).map((a) => `Aviso: ${a}`)]) console.error(t);
}
const salida = val('o');
if (salida) { writeFileSync(salida, svg + '\n'); console.error(`Escrito ${salida} (${svg.length} bytes)`); } else console.log(svg);

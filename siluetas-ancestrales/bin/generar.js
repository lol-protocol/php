#!/usr/bin/env node
// CLI: genera una silueta y la escribe en un archivo SVG o la imprime.
//   node bin/generar.js --semilla 7 --atuendo huaso -o huaso.svg
//   node bin/generar.js --region andes --anio 1500 --sexo f --edad 30
//   node bin/generar.js --nueva --estilo festivo --semilla 12 > nueva.svg
//   node bin/generar.js --listar atuendos|regiones|prendas|tintes

import { writeFileSync } from 'node:fs';
import { renderizar, generarPersona, generarPersonaConRopaNueva, PEINADOS, BARBAS, TOCADOS, ATUENDOS, PRENDAS, TINTES, REGIONES, ETNIAS, listarCatalogo, distribucionColor, catalogoColores } from '../src/index.js';

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
  --pose casual|firme   casual (por defecto): cabeza inclinada, peso en una pierna, brazo libre en la cintura o el bolsillo… distinto con cada semilla; firme: simétrica
  --contorno suave   por defecto no hay bordes; 'suave' añade un borde fino y tenue
  --postura firme|relajada|abierta  --piel ID|#hex  --cabello ID|#hex
  --compacto         SVG unos 7 % más liviano (coordenadas con 1 decimal)
  --detalle minimo|medio|alto  --rasgos  --fondo '#efe9dd'
  --vestir ID,ID,…   viste prenda por prenda (--listar prendas); los colores salen del catálogo de cada una
  --peinado ID --barba ID --tocado ID   cabeza a mano (--listar peinados|barbas|tocados)
  --nueva [--estilo sobrio|trabajo|festivo|ceremonial] [--esquema analogo|complementario|triada|monocromo|tierra|festivo]
  --colores ID       catálogo de colores de una prenda, con el % de cada color (usa --anio para quitar tintes que aún no existían)
  --colores-modo atuendo   los colores salen de la paleta del atuendo en vez de los perfiles de cada prenda
  --listar atuendos|regiones|etnias|prendas|tintes|colores|peinados|barbas|tocados|resumen|auditoria|fuentes   (auditoria: lo que conviene revisar con fuentes del propio pueblo; fuentes: la referencia de cada etnia y atuendo, o «(sin fuente)»)
  -o archivo.svg`);
  process.exit(0);
}
const prendaColores = val('colores');
if (prendaColores) {
  const anioC = val('anio') === undefined ? undefined : Number(val('anio'));
  const pr = PRENDAS.find((p) => p.id === prendaColores);
  if (!pr) { console.error(`Prenda desconocida: ${prendaColores}. Usa --listar prendas.`); process.exit(2); }
  console.log(`${pr.nombre} (${pr.id})${anioC === undefined ? '' : ` en el año ${anioC}`}`);
  for (const [rol, lista] of Object.entries(catalogoColores(prendaColores, { anio: anioC }))) {
    console.log(`\n  ${rol}:`);
    for (const x of lista) console.log(`    ${String(x.pct.toFixed(1)).padStart(5)} %  ${x.hex}  ${x.nombre}  [${x.origen}]`);
  }
  process.exit(0);
}
const listar = val('listar');
if (listar) {
  const f = { atuendos: () => ATUENDOS.map((a) => `${a.id}\t${a.sexo}\t${a.region}\t${a.epoca.join('..')}\t${a.confianza}\t${a.nombre}`),
    etnias: () => ETNIAS.map((e) => `${e.id}\t${e.confianza}\t${e.grupo}\t${e.nombre}`),
    auditoria: () => [...ETNIAS.filter((e) => e.confianza === 'baja').map((e) => `etnia\t${e.id}\t${e.nombre}\t${e.nota}`), ...ATUENDOS.filter((a) => a.confianza === 'baja').map((a) => `atuendo\t${a.id}\t${a.nombre}\t${a.nota}`)],
    fuentes: () => [...ETNIAS.map((e) => ['etnia', e]), ...ATUENDOS.map((a) => ['atuendo', a])].map(([t, x]) => `${t}\t${x.id}\t${x.confianza}\t${x.fuente || '(sin fuente)'}`),
    regiones: () => Object.entries(REGIONES).map(([k, v]) => `${k}\t${v}`),
    prendas: () => PRENDAS.map((p) => `${p.id}\t${p.ranura}\t${p.nombre}`),
    tintes: () => TINTES.map((t) => `${t.id}\t${t.hex}\t${t.nombre}`),
    peinados: () => Object.keys(PEINADOS),
    barbas: () => Object.keys(BARBAS),
    tocados: () => Object.keys(TOCADOS),
    colores: () => PRENDAS.map((p) => `${p.id}\t${distribucionColor(p.id, 'principal').slice(0, 3).map((x) => `${x.nombre} ${x.pct.toFixed(0)}%`).join(', ')}`),
    resumen: () => Object.entries(listarCatalogo()).map(([k, v]) => `${k}\t${v}`) }[listar];
  if (!f) { console.error('Nada que listar con ese nombre.'); process.exit(2); }
  console.log(f().join('\n'));
  process.exit(0);
}

const num = (n) => (val(n) === undefined ? undefined : Number(val(n)));
const opc = {
  semilla: val('semilla') === undefined ? 1 : (Number.isNaN(Number(val('semilla'))) ? val('semilla') : Number(val('semilla'))),
  atuendo: val('atuendo'), region: val('region'), anio: num('anio'), clase: val('clase'), sexo: val('sexo'), edad: num('edad'),
  corpulencia: val('corpulencia') !== undefined && val('corpulencia') !== '' && !Number.isNaN(Number(val('corpulencia'))) ? Number(val('corpulencia')) : val('corpulencia'), altura: num('altura'), postura: val('postura'), piel: val('piel'), cabello: val('cabello'),
  detalle: val('detalle'), rasgos: flag('rasgos'), fondo: val('fondo'), pose: val('pose') === 'casual' ? undefined : val('pose'), contorno: val('contorno'),
  compacto: flag('compacto') || undefined, ropaNueva: flag('nueva'), estilo: val('estilo'), esquema: val('esquema'), colores: val('colores-modo'),
  etnia: val('etnia'), saludo: val('saludo'), vestir: val('vestir') ? val('vestir').split(',').map((x) => x.trim()).filter(Boolean) : undefined, peinado: val('peinado'), barba: val('barba'), tocado: val('tocado'),
};
for (const k of Object.keys(opc)) if (opc[k] === undefined) delete opc[k];
let svg, p;
try {
  p = opc.ropaNueva ? generarPersonaConRopaNueva(opc) : generarPersona(opc);
  svg = renderizar(p, { detalle: opc.detalle, fondo: opc.fondo, contorno: opc.contorno, compacto: opc.compacto });
} catch (e) { console.error(`Error: ${e.message}`); process.exit(2); }
if (flag('avisos')) {
  for (const t of [...(p.estimaciones ?? []), ...(p.avisos ?? []).map((a) => `Aviso: ${a}`), ...(p.coloresPrendas ?? []).map((c) => `Color de ${c.nombre}: ${Object.entries(c.roles).map(([r, x]) => `${r} ${x.nombre}${x.pct == null ? '' : ` (${x.pct} %)`}`).join(', ')}`)]) console.error(t);
}
const salida = val('o');
if (salida) { writeFileSync(salida, svg + '\n'); console.error(`Escrito ${salida} (${svg.length} bytes)`); } else console.log(svg);

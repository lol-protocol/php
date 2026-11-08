import { tunica } from './tunica.js';
import { pantalon } from './pantalon.js';

/** Mono (jumpsuit) y ropa de trabajo de una pieza: torso y pantalón del mismo color y tela. */
export function mono(M, p, k, ctx) {
  const t = tunica(M, { escote: 'alto', manga: 'larga', largo: 'cadera', cintura: 0.9, ...(p.torso ?? {}) }, k, ctx);
  const pa = pantalon(M, { largo: 'tobillo', ancho: 'recto', cintura: 'alta', ...(p.pantalon ?? {}) }, k, ctx);
  return { atras: t.atras, partes: [...pa.partes, ...t.partes], detalles: [...pa.detalles, ...t.detalles] };
}

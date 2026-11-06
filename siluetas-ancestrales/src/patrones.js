// Patrones de tela como <pattern> de SVG. Cada uno recibe dos colores y una escala.

const r = (x, y, w, h, fill, op = 1) => `<rect x="${x}" y="${y}" width="${w}" height="${h}" fill="${fill}"${op < 1 ? ` opacity="${op}"` : ''}/>`;

export const PATRONES = {
  rayas: (a, b, e) => [12 * e, 12 * e, r(0, 0, 12 * e, 12 * e, a) + r(3 * e, 0, 4 * e, 12 * e, b)],
  'rayas-h': (a, b, e) => [12 * e, 12 * e, r(0, 0, 12 * e, 12 * e, a) + r(0, 3 * e, 12 * e, 4 * e, b)],
  'rayas-finas': (a, b, e) => [6 * e, 6 * e, r(0, 0, 6 * e, 6 * e, a) + r(0, 0, 1.6 * e, 6 * e, b, 0.8)],
  bandas: (a, b, e) => [40 * e, 40 * e, r(0, 0, 40 * e, 40 * e, a) + r(0, 12 * e, 40 * e, 6 * e, b) + r(0, 22 * e, 40 * e, 2 * e, b, 0.8)],
  cuadros: (a, b, e) => [16 * e, 16 * e, r(0, 0, 16 * e, 16 * e, a) + r(0, 0, 8 * e, 16 * e, b, 0.5) + r(0, 0, 16 * e, 8 * e, b, 0.5)],
  tartan: (a, b, e) => [24 * e, 24 * e, r(0, 0, 24 * e, 24 * e, a) + r(0, 0, 10 * e, 24 * e, b, 0.55) + r(0, 0, 24 * e, 10 * e, b, 0.55) + r(15 * e, 0, 2 * e, 24 * e, '#ffffff', 0.45) + r(0, 15 * e, 24 * e, 2 * e, '#ffffff', 0.45)],
  puntos: (a, b, e) => [14 * e, 14 * e, r(0, 0, 14 * e, 14 * e, a) + `<circle cx="${7 * e}" cy="${7 * e}" r="${2.2 * e}" fill="${b}"/>`],
  rombos: (a, b, e) => [20 * e, 20 * e, r(0, 0, 20 * e, 20 * e, a) + `<path d="M${10 * e} ${2 * e}L${18 * e} ${10 * e}L${10 * e} ${18 * e}L${2 * e} ${10 * e}Z" fill="none" stroke="${b}" stroke-width="${1.6 * e}"/>`],
  zigzag: (a, b, e) => [20 * e, 12 * e, r(0, 0, 20 * e, 12 * e, a) + `<path d="M0 ${9 * e}L${5 * e} ${3 * e}L${10 * e} ${9 * e}L${15 * e} ${3 * e}L${20 * e} ${9 * e}" fill="none" stroke="${b}" stroke-width="${2 * e}"/>`],
  ondas: (a, b, e) => [24 * e, 12 * e, r(0, 0, 24 * e, 12 * e, a) + `<path d="M0 ${6 * e}Q${6 * e} ${0} ${12 * e} ${6 * e}T${24 * e} ${6 * e}" fill="none" stroke="${b}" stroke-width="${1.8 * e}"/>`],
  grecas: (a, b, e) => [28 * e, 20 * e, r(0, 0, 28 * e, 20 * e, a) + `<path d="M2 ${16 * e}h${6 * e}v${-8 * e}h${8 * e}v${6 * e}h${-4 * e}v${-2 * e}h${12 * e}" fill="none" stroke="${b}" stroke-width="${2.4 * e}" transform="translate(0 ${2 * e})"/>`],
  escalonado: (a, b, e) => [24 * e, 24 * e, r(0, 0, 24 * e, 24 * e, a) + `<path d="M0 ${24 * e}h${8 * e}v${-8 * e}h${8 * e}v${-8 * e}h${8 * e}" fill="none" stroke="${b}" stroke-width="${3 * e}"/>`],
  flores: (a, b, e) => [22 * e, 22 * e, r(0, 0, 22 * e, 22 * e, a) + [[0, -3], [0, 3], [-3, 0], [3, 0]].map(([dx, dy]) => `<circle cx="${11 * e + dx * e}" cy="${11 * e + dy * e}" r="${2 * e}" fill="${b}"/>`).join('') + `<circle cx="${11 * e}" cy="${11 * e}" r="${1.4 * e}" fill="${a}"/>`],
  cruces: (a, b, e) => [14 * e, 14 * e, r(0, 0, 14 * e, 14 * e, a) + `<path d="M${4 * e} ${4 * e}l${6 * e} ${6 * e}m0 ${-6 * e}l${-6 * e} ${6 * e}" stroke="${b}" stroke-width="${1.6 * e}" fill="none"/>`],
  piel: (a, b, e) => [30 * e, 30 * e, r(0, 0, 30 * e, 30 * e, a) + `<ellipse cx="${8 * e}" cy="${9 * e}" rx="${4 * e}" ry="${2.6 * e}" fill="${b}" opacity=".7"/><ellipse cx="${22 * e}" cy="${20 * e}" rx="${3 * e}" ry="${4 * e}" fill="${b}" opacity=".7"/><ellipse cx="${6 * e}" cy="${24 * e}" rx="${2.4 * e}" ry="${1.8 * e}" fill="${b}" opacity=".6"/>`],
  malla: (a, b, e) => [10 * e, 10 * e, r(0, 0, 10 * e, 10 * e, a) + `<path d="M0 0l${5 * e} ${5 * e}l${5 * e} ${-5 * e}M0 ${10 * e}l${5 * e} ${-5 * e}l${5 * e} ${5 * e}" fill="none" stroke="${b}" stroke-width="${1 * e}" opacity=".7"/>`],
  escamas: (a, b, e) => [14 * e, 10 * e, r(0, 0, 14 * e, 10 * e, a) + `<path d="M0 ${6 * e}Q${3.5 * e} ${12 * e} ${7 * e} ${6 * e}Q${10.5 * e} ${12 * e} ${14 * e} ${6 * e}" fill="none" stroke="${b}" stroke-width="${1.2 * e}" opacity=".8"/>`],
  kene: (a, b, e) => [30 * e, 30 * e, r(0, 0, 30 * e, 30 * e, a) + `<path d="M4 4h${22 * e}v${22 * e}h${-16 * e}v${-14 * e}h${10 * e}v${6 * e}h${-4 * e}" fill="none" stroke="${b}" stroke-width="${2.2 * e}" transform="scale(1)"/>`],
  jaspe: (a, b, e) => [26 * e, 26 * e, r(0, 0, 26 * e, 26 * e, a) + `<path d="M0 ${6 * e}Q${8 * e} ${2 * e} ${13 * e} ${8 * e}T${26 * e} ${6 * e}M0 ${18 * e}Q${9 * e} ${14 * e} ${14 * e} ${20 * e}T${26 * e} ${18 * e}" fill="none" stroke="${b}" stroke-width="${2 * e}" opacity=".5"/>`],
};
export const PATRONES_ID = Object.keys(PATRONES);

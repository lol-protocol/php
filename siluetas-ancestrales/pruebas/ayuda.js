// Ayudantes compartidos por las pruebas (no es una prueba: el glob 'pruebas/*.test.js' no lo ejecuta).

/** Pares [x, y] de una ruta SVG con comandos absolutos («x y» separados por espacio). */
export const puntosDeRuta = (d) => [...d.matchAll(/(-?\d+(?:\.\d+)?) (-?\d+(?:\.\d+)?)/g)].map((m) => [Number(m[1]), Number(m[2])]);

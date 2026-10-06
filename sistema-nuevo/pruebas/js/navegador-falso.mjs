// Lo mínimo del navegador que los módulos de interfaz/js/ leen al importarse y que Node no trae: idioma.js lee
// localStorage (para recordar el idioma elegido) y nucleo.js lee window.location.hostname (para armar API_BASE).
// Cada prueba que importa esos módulos (o alguno que los use) empieza con `import "./navegador-falso.mjs";` y después
// pide el módulo con import() dinámico. No termina en .test.mjs: node --test no lo corre como prueba.
globalThis.localStorage = { getItem: () => null, setItem: () => {} };
globalThis.window = { location: { hostname: "localhost" } };

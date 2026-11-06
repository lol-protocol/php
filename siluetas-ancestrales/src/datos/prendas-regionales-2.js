// Más prendas regionales: Asia, África, Oceanía y Medio Oriente. Misma forma que prendas.js.

import { P } from './ayuda.js';

const pat = (tipo, escala = 1, rol = 'secundario') => ({ tipo, escala, rol });

export const PRENDAS_REGIONALES_2 = [
  P('lehenga', 'Lehenga (falda bordada de gala)', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.18, pliegues: 9, volantes: 3, bandaPatron: 'flores', ribeteBajo: 0.05 }),
  P('chaqueta-nehru', 'Chaqueta de cuello alto (nehru)', 'sobre', 'chaqueta', { largo: 'muslo', manga: 'larga', botones: 7, cintura: 0.6, abertura: 0.008, aberturaBajo: 0.012, puños: false }),
  P('durumagi', 'Durumagi (abrigo coreano)', 'sobre', 'chaqueta', { largo: 'pantorrilla', manga: 'ancha', botones: 0, cintura: 0.8, abertura: 0.01, aberturaBajo: 0.03, holgura: 0.03, ribete: true }),
  P('yukata', 'Yukata', 'torso', 'tunica', { largo: 'tobillo', manga: 'kimono', escote: 'v', prof: 0.8, cruzada: 'izq', cintura: 0.8, holgura: 0.018, ribete: false, patron: pat('ondas', 1) }),
  P('happi', 'Happi (chaqueta corta)', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'ancha', botones: 0, cintura: 1, abertura: 0.01, aberturaBajo: 0.03, ribete: true }),
  P('changshan', 'Changshan (túnica larga de hombre)', 'torso', 'tunica', { largo: 'tobillo', manga: 'larga', escote: 'alto', botones: 5, cintura: 0.9, holgura: 0.016, ribete: false, puños: false }),
  P('chaqueta-zhongshan', 'Chaqueta zhongshan', 'torso', 'tunica', { largo: 'cadera', manga: 'larga', escote: 'alto', botones: 5, cintura: 0.9, holgura: 0.018, ribete: false }),
  P('cheongsam-invierno', 'Qipao acolchado', 'torso', 'tunica', { largo: 'tobillo', manga: 'larga', escote: 'alto', cintura: 0.1, holgura: 0.008, cruzada: 'der', ribete: true, puños: true }),
  P('chador', 'Chador', 'capa', 'capa', { largo: 'suelo', ancho: 1, cierre: 'abierta', ribete: false }),
  P('kitenge-top', 'Blusa de tela estampada africana', 'torso', 'tunica', { largo: 'cadera', manga: 'corta', escote: 'v', prof: 0.6, cintura: 0.8, holgura: 0.016, ribete: true, patron: pat('rombos', 1) }),
  P('kitenge-falda', 'Falda de tela estampada africana', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.05, pliegues: 4, patron: pat('flores', 1.1) }),
  P('kikoi', 'Kikoi (paño a rayas)', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.01, pliegues: 2, patron: pat('rayas', 1) }),
  P('kaftan-corto', 'Kaftán corto', 'torso', 'tunica', { largo: 'rodilla', manga: 'ancha', escote: 'v', prof: 0.6, cintura: 1, holgura: 0.03, bordadoPecho: 'zigzag', ribete: true }),
  P('muumuu', 'Vestido amplio estampado', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'corta', escote: 'redondo', cintura: 1, holgura: 0.03, vuelo: 0.1, patron: pat('flores', 1.2), ribete: true }),
  P('pareo-estampado', 'Pareo estampado', 'inferior', 'falda', { largo: 'pantorrilla', vuelo: 0.01, pliegues: 2, patron: pat('flores', 1.2) }),
  P('kurta-corta', 'Kurta corta', 'torso', 'tunica', { largo: 'muslo-alto', manga: 'larga', escote: 'ojal', botones: 4, cintura: 1, holgura: 0.016, puños: false, ribete: false }),
  P('sherwani-corta', 'Chaqueta sherwani corta', 'sobre', 'chaqueta', { largo: 'muslo', manga: 'larga', botones: 8, cintura: 0.4, abertura: 0.006, aberturaBajo: 0.01, puños: true, bordadoBorde: true }),
  P('dhoti-sin-camisa', 'Paño envuelto de algodón', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.01, pliegues: 3, ribeteBajo: 0.03 }),
  P('capa-lluvia-paja', 'Capa de paja para la lluvia', 'capa', 'poncho', { largo: 'muslo-alto', ancho: 0.95, listas: 0, punta: 0.03 }),
  P('sombrero-paja-capa', 'Capa corta de fibra', 'capa', 'capa', { largo: 'cintura', ancho: 0.6, cierre: 'abierta', ribete: false, patron: pat('rayas-finas', 1) }),
  P('manto-lana-ancho', 'Manto ancho de lana', 'capa', 'drapeado', { largo: 'pantorrilla', lado: 'der', pliegues: 4, orla: true }),
  P('chaleco-pieles', 'Chaleco de piel', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'sin', botones: 0, cintura: 0.8, abertura: 0.012, aberturaBajo: 0.016, patron: pat('piel', 1.1) }),
  P('falda-lana-gruesa', 'Falda gruesa de lana', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.1, pliegues: 6, ribeteBajo: 0.03 }),
  P('camisa-lino-larga', 'Camisa larga de lino', 'torso', 'tunica', { largo: 'rodilla', manga: 'larga', escote: 'ojal', cintura: 0.9, holgura: 0.02, puños: true, ribete: false }),
  P('tunica-corta-lana', 'Túnica corta de lana', 'torso', 'tunica', { largo: 'muslo-alto', manga: 'larga', escote: 'redondo', cintura: 1, holgura: 0.02, ribete: false }),
  P('vestido-lino-largo', 'Vestido largo de lino', 'torso', 'tunica', { largo: 'tobillo', manga: 'larga', escote: 'redondo', cintura: 0.7, holgura: 0.014, vuelo: 0.05, ribete: false, pliegues: 3 }),
];

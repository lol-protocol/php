// Prendas con los tipos de ropa nuevos: escotes de tirantes, halter, asimétrico y corazón; mangas farol, pagoda, cap y
// ajustada; vestidos y faldas de sirena, trapecio y globo; capuchas, bolsillos, cuellos de camisa, ribetes de piel y
// cordones; pantalones pitillo, palazzo, cargo y jodhpur; monos; cuellos, calzado y accesorios nuevos.
// Misma forma que prendas.js; sus colores están en colores-prendas.js.

import { P } from './ayuda.js';

const pat = (tipo, escala = 1, rol = 'secundario') => ({ tipo, escala, rol });

export const PRENDAS_MODA = [
  // ===== Vestidos =====
  P('vestido-tirantes', 'Vestido de tirantes', 'torso', 'tunica', { largo: 'rodilla', manga: 'sin', escote: 'tirantes', cintura: 0.2, holgura: 0.004, vuelo: 0.07, pliegues: 4, ribete: false }),
  P('vestido-tirantes-largo', 'Vestido largo de tirantes', 'torso', 'tunica', { largo: 'suelo', manga: 'sin', escote: 'tirantes', cintura: 0.1, holgura: 0.004, vuelo: 0.12, pliegues: 6, ribete: false }),
  P('vestido-halter', 'Vestido halter', 'torso', 'tunica', { largo: 'rodilla', manga: 'sin', escote: 'halter', cintura: 0.1, holgura: 0.004, vuelo: 0.09, pliegues: 4, ribete: false }),
  P('vestido-asimetrico', 'Vestido de un hombro', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'sin', escote: 'asimetrico', lado: 'der', cintura: 0.1, holgura: 0.004, vuelo: 0.06, pliegues: 4, ribete: false }),
  P('vestido-corazon', 'Vestido de escote corazón', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'sin', escote: 'corazon', cintura: 0, holgura: 0.003, vuelo: 0.11, pliegues: 5, ribete: true }),
  P('vestido-corazon-gala', 'Vestido de gala de escote corazón', 'torso', 'tunica', { largo: 'suelo', manga: 'sin', escote: 'corazon', cintura: 0, holgura: 0.003, vuelo: 0.16, pliegues: 7, ribeteBajo: 0.03, ribete: true }),
  P('vestido-sirena', 'Vestido de sirena', 'torso', 'tunica', { largo: 'suelo', manga: 'sin', escote: 'redondo', prof: 0.6, cintura: 0, holgura: 0.002, vuelo: 0.13, forma: 'sirena', pliegues: 4, ribete: false }),
  P('vestido-sirena-tirantes', 'Vestido de sirena con tirantes', 'torso', 'tunica', { largo: 'suelo', manga: 'sin', escote: 'tirantes', cintura: 0, holgura: 0.002, vuelo: 0.15, forma: 'sirena', pliegues: 4, ribete: false }),
  P('vestido-trapecio', 'Vestido trapecio', 'torso', 'tunica', { largo: 'rodilla', manga: 'corta', escote: 'redondo', prof: 0.4, cintura: 1, holgura: 0.01, vuelo: 0.14, forma: 'trapecio', ribete: true, pliegues: 0 }),
  P('vestido-globo', 'Vestido globo', 'torso', 'tunica', { largo: 'rodilla', manga: 'sin', escote: 'v', prof: 0.7, cintura: 0.5, holgura: 0.004, vuelo: 0.075, forma: 'globo', ribete: false }),
  P('vestido-tubo', 'Vestido recto', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'tres-cuartos', escote: 'barco', cintura: 0, holgura: 0.002, vuelo: 0, forma: 'tubo', ribete: false }),
  P('vestido-camisero', 'Vestido camisero', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'corta', escote: 'alto', cuelloCamisa: true, botones: 8, cinturon: true, cintura: 0.5, holgura: 0.01, vuelo: 0.06, ribete: false }),
  P('vestido-volantes', 'Vestido de volantes', 'torso', 'tunica', { largo: 'pantorrilla', manga: 'corta', escote: 'cuadrado', cintura: 0.3, holgura: 0.004, vuelo: 0.1, volantes: 3, ribete: false }),
  P('vestido-pico', 'Vestido de bajo en pico', 'torso', 'tunica', { largo: 'rodilla', manga: 'corta', escote: 'v', cintura: 0.5, holgura: 0.006, vuelo: 0.08, bajo: 'pico', ribete: false }),
  // ===== Blusas, camisas y poleras =====
  P('blusa-farol', 'Blusa de manga farol', 'torso', 'tunica', { largo: 'cadera', manga: 'farol', escote: 'cuadrado', cintura: 0.8, holgura: 0.012, puños: true, ribete: true }),
  P('blusa-pagoda', 'Blusa de manga pagoda', 'torso', 'tunica', { largo: 'cadera', manga: 'pagoda', escote: 'v', prof: 0.7, cintura: 0.7, holgura: 0.012, ribete: false }),
  P('camisa-clasica', 'Camisa clásica de cuello', 'torso', 'tunica', { largo: 'muslo-alto', manga: 'larga', escote: 'alto', cuelloCamisa: true, botones: 7, bolsillos: 'pecho', puños: true, cintura: 0.9, holgura: 0.014, ribete: false }),
  P('camisa-ajustada', 'Camisa ajustada', 'torso', 'tunica', { largo: 'muslo-alto', manga: 'ajustada', escote: 'alto', cuelloCamisa: true, botones: 7, puños: true, cintura: 0.3, holgura: 0.004, ribete: false }),
  P('camisa-safari', 'Camisa safari', 'torso', 'tunica', { largo: 'muslo-alto', manga: 'corta', escote: 'alto', cuelloCamisa: true, botones: 5, bolsillos: 'pecho', cinturon: true, cintura: 0.7, holgura: 0.016, ribete: false }),
  P('polo', 'Polo', 'torso', 'tunica', { largo: 'cadera', manga: 'corta', escote: 'ojal', cuelloCamisa: true, botones: 3, cintura: 1, holgura: 0.012, ribete: false }),
  P('camiseta-cap', 'Camiseta de manga corta ajustada', 'torso', 'tunica', { largo: 'cadera', manga: 'cap', escote: 'redondo', prof: 0.4, cintura: 0.8, holgura: 0.008, ribete: false }),
  P('camiseta-sisa', 'Camiseta sin mangas', 'torso', 'tunica', { largo: 'cadera', manga: 'sin', escote: 'redondo', prof: 0.8, cintura: 0.8, holgura: 0.01, ribete: false }),
  P('top-tirantes', 'Top de tirantes', 'torso', 'tunica', { largo: 'cintura', manga: 'sin', escote: 'tirantes', cintura: 0, holgura: 0.003, ribete: false }),
  P('top-halter', 'Top halter', 'torso', 'tunica', { largo: 'cintura', manga: 'sin', escote: 'halter', cintura: 0, holgura: 0.003, ribete: false }),
  P('sudadera-capucha', 'Sudadera con capucha', 'torso', 'tunica', { largo: 'cadera', manga: 'larga', escote: 'redondo', prof: 0.4, capucha: true, bolsillos: 'canguro', puños: true, cintura: 1, holgura: 0.03, ribete: false }),
  P('sueter-oversize', 'Suéter holgado', 'torso', 'tunica', { largo: 'muslo-alto', manga: 'larga', escote: 'redondo', prof: 0.4, cintura: 1, holgura: 0.042, puños: true, ribete: true }),
  // ===== Sacos, chaquetas y abrigos =====
  P('bomber', 'Chaqueta bomber', 'sobre', 'chaqueta', { largo: 'cintura', manga: 'larga', botones: 0, cintura: 0.7, abertura: 0.008, aberturaBajo: 0.01, puños: true, ribete: true, bolsillos: 'pecho' }),
  P('parka-capucha', 'Parka con capucha', 'sobre', 'chaqueta', { largo: 'muslo', manga: 'larga', botones: 5, capucha: true, pieles: 'cuello', bolsillos: 'cadera', cintura: 0.9, holgura: 0.032, abertura: 0.01, aberturaBajo: 0.014, ribete: true }),
  P('anorak', 'Anorak', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'larga', botones: 3, capucha: true, bolsillos: 'pecho', cinturon: true, cintura: 0.9, holgura: 0.028, abertura: 0.01, aberturaBajo: 0.012, puños: true }),
  P('abrigo-piel-total', 'Abrigo con ribetes de piel', 'sobre', 'chaqueta', { largo: 'pantorrilla', manga: 'larga', botones: 6, solapa: true, pieles: 'todo', cintura: 0.7, vuelo: 0.06, abertura: 0.012, aberturaBajo: 0.02, holgura: 0.022, ribete: true }),
  P('abrigo-punos-piel', 'Abrigo con puños de piel', 'sobre', 'chaqueta', { largo: 'rodilla', manga: 'larga', botones: 6, solapa: true, pieles: 'puños', cintura: 0.6, vuelo: 0.05, abertura: 0.012, aberturaBajo: 0.02, holgura: 0.02, ribete: false }),
  P('blazer-ajustado', 'Blazer ajustado', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'larga', botones: 2, solapa: true, cintura: 0.1, abertura: 0.012, aberturaBajo: 0.03, puños: false, bolsillos: 'cadera', ribete: false }),
  P('chaqueta-safari', 'Chaqueta safari', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'larga', botones: 5, bolsillos: 'ambos', cinturon: true, cintura: 0.7, abertura: 0.01, aberturaBajo: 0.012, puños: true }),
  P('chaqueta-motera', 'Chaqueta de motociclista', 'sobre', 'chaqueta', { largo: 'cintura', manga: 'larga', botones: 0, solapa: true, cinturon: true, bolsillos: 'pecho', cintura: 0.5, abertura: 0.008, aberturaBajo: 0.01, puños: true, ribete: false }),
  P('bata-casa', 'Bata de casa', 'sobre', 'chaqueta', { largo: 'tobillo', manga: 'larga', botones: 0, solapa: true, cinturon: true, cintura: 0.9, holgura: 0.03, abertura: 0.012, aberturaBajo: 0.05, ribete: true }),
  P('batin-seda', 'Batín de seda', 'sobre', 'chaqueta', { largo: 'pantorrilla', manga: 'ancha', botones: 0, solapa: true, cinturon: true, cintura: 0.8, holgura: 0.03, abertura: 0.012, aberturaBajo: 0.05, ribete: true }),
  P('corse', 'Corsé con cordones', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'sin', botones: 0, cordones: true, cintura: 0, abertura: 0.006, aberturaBajo: 0.008, ribete: true }),
  P('chaleco-acolchado', 'Chaleco acolchado', 'sobre', 'chaqueta', { largo: 'cadera', manga: 'sin', botones: 5, bolsillos: 'cadera', cintura: 0.9, holgura: 0.03, abertura: 0.01, aberturaBajo: 0.012, ribete: false }),
  // ===== Pantalones, faldas y monos =====
  P('pantalon-pitillo', 'Pantalón pitillo', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'pitillo', cintura: 'natural', bolsillos: 'jean' }),
  P('pantalon-palazzo', 'Pantalón palazzo', 'inferior', 'pantalon', { largo: 'suelo', ancho: 'palazzo', cintura: 'alta', holgura: 0.006 }),
  P('pantalon-jodhpur', 'Pantalón de montar (jodhpur)', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'jodhpur', cintura: 'natural' }),
  P('pantalon-cargo', 'Pantalón cargo', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'recto', cintura: 'natural', bolsillos: 'cargo', puños: false }),
  P('jeans-clasicos', 'Jeans de cinco bolsillos', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'recto', cintura: 'natural', bolsillos: 'jean', puños: true }),
  P('pantalon-pliegues', 'Pantalón de pinzas', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'recto', cintura: 'alta', bolsillos: 'pliegues' }),
  P('pantalon-tubo', 'Pantalón recto estrecho', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'tubo', cintura: 'natural' }),
  P('bermuda', 'Bermudas', 'inferior', 'pantalon', { largo: 'rodilla', ancho: 'recto', cintura: 'natural', bolsillos: 'cargo' }),
  P('pantalon-capri', 'Pantalón capri', 'inferior', 'pantalon', { largo: 'pantorrilla', ancho: 'pitillo', cintura: 'natural' }),
  P('pantalon-pescador', 'Pantalón de pescador', 'inferior', 'pantalon', { largo: 'pantorrilla', ancho: 'amplio', cintura: 'baja', cordon: true }),
  P('leggings', 'Mallas', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'ajustado', cintura: 'alta', holgura: 0.001 }),
  P('sarouel', 'Pantalón sarouel', 'inferior', 'pantalon', { largo: 'tobillo', ancho: 'bombacho', cintura: 'baja' }),
  P('culotte', 'Pantalón culotte', 'inferior', 'pantalon', { largo: 'pantorrilla', ancho: 'amplio', cintura: 'alta' }),
  P('falda-sirena', 'Falda de sirena', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.12, forma: 'sirena', cintura: 'natural', pliegues: 3 }),
  P('falda-globo', 'Falda globo', 'inferior', 'falda', { largo: 'rodilla', vuelo: 0.07, forma: 'globo', cintura: 'natural', pliegues: 4 }),
  P('falda-trapecio', 'Falda trapecio', 'inferior', 'falda', { largo: 'rodilla', vuelo: 0.12, forma: 'trapecio', cintura: 'natural', pliegues: 0 }),
  P('falda-abertura', 'Falda recta con abertura', 'inferior', 'falda', { largo: 'pantorrilla', vuelo: 0.01, forma: 'tubo', cintura: 'natural', abertura: 'frente', pliegues: 0 }),
  P('falda-abertura-lado', 'Falda larga con abertura lateral', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.05, cintura: 'natural', abertura: 'lado', pliegues: 3 }),
  P('falda-mini', 'Minifalda', 'inferior', 'falda', { largo: 'muslo-alto', vuelo: 0.04, forma: 'trapecio', cintura: 'baja', pliegues: 0 }),
  P('falda-midi', 'Falda midi', 'inferior', 'falda', { largo: 'pantorrilla', vuelo: 0.1, cintura: 'natural', pliegues: 6 }),
  P('falda-capas', 'Falda de capas', 'inferior', 'falda', { largo: 'tobillo', vuelo: 0.14, volantes: 4, cintura: 'natural', pliegues: 2 }),
  P('mono-trabajo', 'Mono de trabajo', 'torso', 'mono', { torso: { manga: 'larga', escote: 'alto', cuelloCamisa: true, botones: 6, bolsillos: 'pecho', cinturon: true, largo: 'cadera', puños: true }, pantalon: { ancho: 'recto', largo: 'tobillo', cintura: 'alta' } }),
  P('mono-elegante', 'Mono de noche', 'torso', 'mono', { torso: { manga: 'sin', escote: 'halter', largo: 'cintura', cintura: 0 }, pantalon: { ancho: 'palazzo', largo: 'suelo', cintura: 'alta', holgura: 0.006 } }),
  // ===== Cuello y accesorios =====
  P('cuello-piel', 'Cuello de piel', 'cuello', 'cuello', { tipo: 'piel' }),
  P('esclavina', 'Esclavina', 'cuello', 'cuello', { tipo: 'esclavina' }),
  P('chalina', 'Chalina', 'cuello', 'cuello', { tipo: 'chalina' }),
  P('panuelo-triangulo', 'Pañuelo anudado al cuello', 'cuello', 'cuello', { tipo: 'triangulo' }),
  P('babero-cuello', 'Cuello ancho redondeado', 'cuello', 'cuello', { tipo: 'babero' }),
  P('tirantes-pantalon', 'Tirantes', 'accesorio', 'accesorio', { tipo: 'tirantes' }),
  // ===== Calzado =====
  P('botines', 'Botines', 'calzado', 'calzado', { tipo: 'botines' }),
  P('tacones', 'Zapatos de taco', 'calzado', 'calzado', { tipo: 'tacones' }),
  P('chancletas', 'Chancletas', 'calzado', 'calzado', { tipo: 'chancletas' }),
  P('zapatos-oxford', 'Zapatos oxford', 'calzado', 'calzado', { tipo: 'oxford' }),
];

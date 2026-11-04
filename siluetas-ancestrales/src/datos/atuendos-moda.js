// Atuendos que usan los tipos de ropa nuevos (tirantes, halter, sirena, trapecio, capuchas, pieles, pantalones y faldas
// variados, monos…). Misma forma que atuendos.js. Los colores de cada prenda vienen de su perfil (colores-prendas.js).

import { A } from './ayuda.js';

const CLAROS = 'porcelana clara clara-calida';
const MEST = 'clara-calida oliva-clara oliva canela';
const CIUDAD = { A: 'lana-negra gris-oscuro azul-marino', B: 'crema', C: 'lana-negra', D: 'plata' };
const TRAB = { A: 'caqui-militar azul-marino kaki', B: 'crudo', C: 'lana-negra', D: 'cobre' };
const VEST = { A: 'azul-marino burdeos verde-bosque rosa-viejo', B: 'crema', C: 'lana-negra', D: 'plata' };
const GALA = { A: 'negro-carbon burdeos azul-marino verde-esmeralda', B: 'crema', C: 'plata', D: 'oro' };

const F = (id, n, region, ep, clase, prendas, paleta, extra) => A(id, n, region, ep, 'f', clase, prendas, paleta, extra);
const M = (id, n, region, ep, clase, prendas, paleta, extra) => A(id, n, region, ep, 'm', clase, prendas, paleta, extra);

export const ATUENDOS_MODA = [
  // ===== Mujeres: vestidos =====
  F('moda-chile-verano-1955', 'Chilena de verano con vestido de tirantes (1955)', 'chile-rural', [1948, 1978], 'media', 'vestido-tirantes cardigan tacones perlas', VEST, { peinados: 'ondas bob', tocados: 'pamela ninguno', pieles: MEST }),
  F('moda-chile-gala-1960', 'Chilena de gala con vestido largo de tirantes (1960)', 'chile-rural', [1955, 1985], 'alta', 'vestido-tirantes-largo estola tacones perlas', GALA, { peinados: 'ondas recogido', pieles: MEST }),
  F('moda-chile-halter-1970', 'Chilena con vestido halter (1970)', 'chile-rural', [1965, 1985], 'media', 'vestido-halter chancletas collar-cuentas', VEST, { peinados: 'largo-liso bob', pieles: MEST }),
  F('moda-latam-asimetrico', 'Latinoamericana con vestido de un hombro', 'latinoamerica-moderna', [1960, 1985], 'alta', 'vestido-asimetrico tacones collar-colgante', GALA, { peinados: 'recogido largo-liso', pieles: MEST + ' canela bronce' }),
  F('moda-eeuu-corazon-1955', 'Estadounidense de escote corazón (1955)', 'norteamerica-moderna', [1945, 1965], 'media', 'vestido-corazon tacones perlas', VEST, { peinados: 'ondas', tocados: 'cloche ninguno', pieles: CLAROS }),
  F('moda-gala-corazon', 'Dama de gala con escote corazón', 'europa-xix', [1935, 1975], 'alta', 'vestido-corazon-gala estola tacones perlas', GALA, { peinados: 'ondas recogido', pieles: CLAROS }),
  F('moda-sirena-gala', 'Dama con vestido de sirena y capa de gala', 'europa-xix', [1930, 1985], 'alta', 'vestido-sirena capa-opera tacones perlas', GALA, { peinados: 'ondas recogido', pieles: CLAROS }),
  F('moda-sirena-tirantes', 'Joven con vestido de sirena de tirantes', 'norteamerica-moderna', [1935, 1985], 'alta', 'vestido-sirena-tirantes estola tacones', GALA, { peinados: 'ondas largo-liso', pieles: CLAROS + ' ' + MEST }),
  F('moda-trapecio-1965', 'Joven con vestido trapecio (1965)', 'norteamerica-moderna', [1960, 1975], 'media', 'vestido-trapecio botines perlas', VEST, { peinados: 'bob largo-liso', tocados: 'beanie ninguno', pieles: CLAROS }),
  F('moda-globo', 'Mujer con vestido globo', 'europa-xix', [1955, 1985], 'media', 'vestido-globo botines collar-colgante', VEST, { peinados: 'bob recogido', pieles: CLAROS }),
  F('moda-tubo-1960', 'Ejecutiva con vestido recto y blazer (1960)', 'norteamerica-moderna', [1955, 1975], 'media', 'vestido-tubo blazer-ajustado tacones perlas', CIUDAD, { peinados: 'ondas recogido', pieles: CLAROS }),
  F('moda-chile-camisero-1950', 'Chilena con vestido camisero', 'chile-rural', [1945, 1975], 'media', 'vestido-camisero zapatos-oxford panuelo-triangulo', VEST, { peinados: 'ondas recogido', pieles: MEST }),
  F('moda-latam-volantes', 'Mujer con vestido de volantes y chal', 'latinoamerica-moderna', [1900, 1985], 'media', 'vestido-volantes chal-seda alpargatas', VEST, { peinados: 'trenzas-dos recogido', pieles: MEST + ' canela bronce' }),
  F('moda-pico', 'Mujer con vestido de bajo en pico', 'europa-xix', [1950, 1980], 'media', 'vestido-pico botines panuelo-triangulo', VEST, { peinados: 'bob ondas', pieles: CLAROS }),
  // ===== Mujeres: blusas, faldas y pantalones =====
  F('moda-blusa-farol', 'Mujer con blusa de manga farol', 'europa-xix', [1925, 1965], 'media', 'blusa-farol falda-midi tacones perlas', VEST, { peinados: 'ondas bob', pieles: CLAROS }),
  F('moda-blusa-pagoda', 'Mujer con blusa de manga pagoda', 'norteamerica-moderna', [1950, 1985], 'media', 'blusa-pagoda falda-lapiz tacones panuelo-triangulo', VEST, { peinados: 'ondas recogido', pieles: CLAROS }),
  F('moda-camisa-clasica', 'Chilena con camisa y falda con abertura', 'chile-rural', [1940, 1985], 'media', 'camisa-clasica falda-abertura zapatos-oxford', VEST, { peinados: 'ondas recogido', pieles: MEST }),
  F('moda-camisa-ajustada', 'Joven con camisa ajustada y pitillo', 'norteamerica-moderna', [1955, 1985], 'media', 'camisa-ajustada pantalon-pitillo botines', VEST, { peinados: 'bob largo-liso', pieles: CLAROS + ' ' + MEST }),
  F('moda-camiseta-cap', 'Mujer con camiseta y pantalón capri', 'norteamerica-moderna', [1950, 1985], 'baja', 'camiseta-cap pantalon-capri chancletas', VEST, { peinados: 'bob largo-liso trenza-una', pieles: CLAROS + ' ' + MEST }),
  F('moda-top-tirantes', 'Joven con top de tirantes y culotte', 'latinoamerica-moderna', [1965, 1985], 'baja', 'top-tirantes culotte chancletas', VEST, { peinados: 'largo-liso trenza-una', pieles: MEST + ' canela bronce' }),
  F('moda-top-halter', 'Joven con top halter y pantalón palazzo', 'norteamerica-moderna', [1965, 1985], 'media', 'top-halter pantalon-palazzo tacones', GALA, { peinados: 'largo-liso rizos', pieles: CLAROS + ' ' + MEST }),
  F('moda-sueter-leggings', 'Mujer con suéter holgado y mallas', 'europa-xix', [1970, 1985], 'baja', 'sueter-oversize leggings botines', VEST, { peinados: 'largo-liso bob', tocados: 'beanie ninguno', pieles: CLAROS }),
  F('moda-anorak', 'Mujer con anorak y pantalón de pinzas', 'europa-xix', [1950, 1985], 'baja', 'anorak pantalon-pliegues botines', TRAB, { peinados: 'bob trenza-una', tocados: 'beanie ninguno', pieles: CLAROS }),
  F('moda-abrigo-piel', 'Dama con abrigo de piel y vestido de 1950', 'europa-xix', [1920, 1965], 'alta', 'vestido-50 abrigo-piel-total tacones perlas', GALA, { peinados: 'ondas recogido', tocados: 'cloche ninguno', pieles: CLAROS }),
  F('moda-cuello-piel', 'Mujer con abrigo corto y cuello de piel', 'europa-xix', [1930, 1975], 'alta', 'vestido-40 abrigo-corto cuello-piel tacones perlas', GALA, { peinados: 'ondas recogido', tocados: 'cloche ninguno', pieles: CLAROS }),
  F('moda-blazer', 'Mujer con blazer, blusa y falda', 'norteamerica-moderna', [1950, 1985], 'media', 'blazer-ajustado blusa-seda falda-lapiz tacones perlas', CIUDAD, { peinados: 'ondas recogido bob', pieles: CLAROS + ' ' + MEST }),
  F('moda-bata-casa', 'Mujer en bata de casa', 'europa-xix', [1900, 1980], 'media', 'camison bata-casa descalzo', { A: 'crema rosa-viejo azul-celeste', B: 'crema', C: 'rosa-viejo', D: 'plata' }, { peinados: 'trenza-una largo-liso recogido', pieles: CLAROS }),
  F('moda-corse', 'Mujer con corsé y falda de capas', 'europa-xix', [1850, 1915], 'media', 'blusa-regional corse falda-capas botines', VEST, { peinados: 'recogido', pieles: CLAROS }),
  F('moda-falda-globo', 'Joven con falda globo', 'europa-xix', [1950, 1985], 'media', 'blusa-cuello-bebe falda-globo botines', VEST, { peinados: 'bob ondas', pieles: CLAROS }),
  F('moda-falda-trapecio', 'Joven con falda trapecio', 'norteamerica-moderna', [1960, 1980], 'media', 'polera falda-trapecio botines', VEST, { peinados: 'bob largo-liso', pieles: CLAROS + ' ' + MEST }),
  F('moda-falda-lado', 'Mujer con falda larga de abertura lateral', 'latinoamerica-moderna', [1960, 1985], 'media', 'blusa-seda falda-abertura-lado tacones', VEST, { peinados: 'recogido largo-liso', pieles: MEST }),
  F('moda-mini-1967', 'Joven con minifalda (1967)', 'norteamerica-moderna', [1963, 1975], 'baja', 'polera falda-mini botines', VEST, { peinados: 'bob largo-liso', pieles: CLAROS + ' ' + MEST }),
  F('moda-falda-sirena', 'Mujer con falda de sirena y blusa de seda', 'europa-xix', [1930, 1985], 'alta', 'blusa-seda falda-sirena tacones perlas', GALA, { peinados: 'ondas recogido', pieles: CLAROS }),
  F('moda-mono-gala', 'Mujer con mono de noche', 'norteamerica-moderna', [1970, 1985], 'alta', 'mono-elegante tacones perlas', GALA, { peinados: 'largo-liso recogido', pieles: CLAROS + ' ' + MEST }),
  F('moda-esclavina', 'Dama con vestido y esclavina', 'europa-xix', [1850, 1920], 'alta', 'vestido-victoriano esclavina zapatos-cuero perlas', { A: 'borravino verde-botella azul-prusia lana-negra', B: 'crema', C: 'lana-negra', D: 'plata' }, { peinados: 'recogido', tocados: 'velo ninguno', pieles: CLAROS }),
  F('moda-panuelo', 'Mujer con pañuelo al cuello', 'europa-xix', [1945, 1975], 'media', 'blusa-cuello-bebe falda-lapiz panuelo-triangulo zapatos-vestir', VEST, { peinados: 'ondas bob', pieles: CLAROS }),
  F('moda-babero', 'Mujer con cuello ancho redondeado', 'europa-xix', [1900, 1960], 'media', 'vestido-casa babero-cuello zapatos-vestir', VEST, { peinados: 'recogido ondas', pieles: CLAROS }),
  F('moda-sarouel', 'Mujer con sarouel y camiseta', 'norteamerica-moderna', [1970, 1985], 'baja', 'sarouel camiseta-sisa chancletas', VEST, { peinados: 'largo-liso trenza-una', pieles: CLAROS + ' ' + MEST }),
  // ===== Hombres =====
  M('moda-safari-latam', 'Hombre con camisa safari y bermudas', 'latinoamerica-moderna', [1930, 1975], 'media', 'camisa-safari bermuda zapatillas-lona', TRAB, { peinados: 'corto-raya corto', barbas: 'bigote ninguna', tocados: 'canotier gorra-visera', pieles: MEST }),
  M('moda-polo', 'Hombre con polo y pantalón de pinzas', 'norteamerica-moderna', [1945, 1985], 'media', 'polo pantalon-pliegues zapatos-oxford', CIUDAD, { peinados: 'corto-raya corto', barbas: 'bigote ninguna', pieles: CLAROS }),
  M('moda-camiseta-sisa', 'Hombre con camiseta sin mangas y pantalón cargo', 'norteamerica-moderna', [1950, 1985], 'baja', 'camiseta-sisa pantalon-cargo botas-trabajo', TRAB, { peinados: 'corto rapado', barbas: 'bigote barba-corta ninguna', pieles: CLAROS + ' ' + MEST }),
  M('moda-sudadera', 'Joven con sudadera con capucha y jeans', 'norteamerica-moderna', [1965, 1985], 'baja', 'sudadera-capucha jeans-clasicos zapatillas-lona', TRAB, { peinados: 'corto largo-liso', barbas: 'ninguna', tocados: 'beanie ninguno', pieles: CLAROS + ' ' + MEST }),
  M('moda-bomber', 'Hombre con chaqueta bomber', 'norteamerica-moderna', [1950, 1985], 'baja', 'bomber pantalon-cargo botas-trabajo', TRAB, { peinados: 'corto rapado', barbas: 'bigote ninguna', tocados: 'gorra-visera ninguno', pieles: CLAROS + ' ' + MEST }),
  M('moda-parka', 'Hombre con parka de capucha', 'europa-xix', [1950, 1985], 'baja', 'parka-capucha pantalon-tubo botas-trabajo', TRAB, { peinados: 'corto', barbas: 'barba-corta ninguna', tocados: 'beanie ninguno', pieles: CLAROS }),
  M('moda-abrigo-punos-piel', 'Caballero con abrigo de puños de piel', 'europa-xix', [1900, 1960], 'alta', 'camisa-blanca corbata abrigo-punos-piel pantalon-vestir zapatos-oxford', CIUDAD, { peinados: 'corto-raya', barbas: 'bigote barba-corta', tocados: 'sombrero-copa fedora', pieles: CLAROS }),
  M('moda-chaqueta-safari', 'Hombre con chaqueta safari', 'latinoamerica-moderna', [1950, 1985], 'media', 'chaqueta-safari camisa-cuadros pantalon-cargo botas-trabajo', TRAB, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'gorra-visera', pieles: MEST }),
  M('moda-motera', 'Motociclista con chaqueta de cuero', 'norteamerica-moderna', [1950, 1985], 'baja', 'chaqueta-motera polera jeans-clasicos botas-trabajo', TRAB, { peinados: 'corto', barbas: 'bigote barba-corta ninguna', pieles: CLAROS + ' ' + MEST }),
  M('moda-batin', 'Hombre en batín de seda', 'europa-xix', [1900, 1975], 'alta', 'batin-seda pantalon-vestir chancletas', { A: 'burdeos azul-marino verde-bosque', B: 'crema', C: 'oro', D: 'oro' }, { peinados: 'corto-raya', barbas: 'bigote ninguna', pieles: CLAROS }),
  M('moda-chaleco-acolchado', 'Hombre con chaleco acolchado', 'norteamerica-moderna', [1970, 1985], 'baja', 'chaleco-acolchado camisa-cuadros jeans-clasicos botas-trabajo', TRAB, { peinados: 'corto largo-liso', barbas: 'bigote barba-corta ninguna', pieles: CLAROS }),
  M('moda-jinete', 'Jinete con pantalón de montar', 'europa-xix', [1880, 1960], 'alta', 'camisa-clasica pantalon-jodhpur botas-altas cinturon-fino', { A: 'beige crema', B: 'crema', C: 'cuero-oscuro', D: 'plata' }, { peinados: 'corto-raya', barbas: 'bigote ninguna', tocados: 'gorra-plana ninguno', pieles: CLAROS }),
  M('moda-pescador', 'Hombre con pantalón de pescador', 'latinoamerica-moderna', [1950, 1985], 'baja', 'camiseta-sisa pantalon-pescador chancletas', TRAB, { peinados: 'corto rapado', barbas: 'bigote ninguna', tocados: 'bucket gorra-visera', pieles: MEST + ' canela bronce' }),
  M('moda-mono-trabajo', 'Obrero con mono de trabajo', 'europa-xix', [1930, 1985], 'baja', 'mono-trabajo botas-trabajo', TRAB, { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'casco-obra gorra-plana', pieles: CLAROS }),
  M('moda-chalina', 'Hombre con chalina', 'europa-xix', [1930, 1985], 'media', 'camisa-blanca chaleco-terno pantalon-vestir chalina zapatos-oxford', CIUDAD, { peinados: 'corto-raya', barbas: 'bigote ninguna', tocados: 'fedora ninguno', pieles: CLAROS }),
  M('moda-tirantes', 'Hombre con tirantes', 'europa-xix', [1920, 1960], 'baja', 'camisa-cuadros pantalon-pliegues tirantes-pantalon zapatos-oxford', TRAB, { peinados: 'corto-raya corto', barbas: 'bigote ninguna', tocados: 'gorra-plana', pieles: CLAROS }),
];

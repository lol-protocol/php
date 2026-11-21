// Atuendos de niños y niñas (hasta 12 años) de las regiones y épocas más usadas. Sin ellos un niño se vestía como un adulto
// más pequeño. Para los niños, `buscarAtuendos` prefiere estos si los hay; si no, sale uno de adulto sin prendas solo
// de adulto (armadura, toga, hábito…). Todos de confianza baja: son trajes infantiles genéricos hechos con prendas del catálogo.

import { A, BL, CLAROS, OSCURA } from './ayuda.js';

const E = [2, 12];
const NOTA = 'Traje infantil genérico con prendas del catálogo: la ropa de los niños varió mucho por familia y condición social.';
const base = { edades: E, conf: 'baja', nota: NOTA, barbas: 'ninguna', tocados: 'ninguno' };

export const ATUENDOS_INFANTILES = [
  A('europa-campesino-nino', 'Niño campesino europeo', 'europa-campesina', [1750, 1950], 'm', 'baja', 'camisa-blanca chaleco-campesino calzones-rodilla botas-cuero', { A: 'lana-parda umbra azul-plomo', B: BL, C: 'lana-negra', D: 'cobre' }, { ...base, peinados: 'corto', pieles: CLAROS }),
  A('europa-campesina-nina', 'Niña campesina europea', 'europa-campesina', [1750, 1950], 'f', 'baja', 'blusa-campesina falda-larga delantal-blanco zapatos-cuero', { A: 'rojo-granza lana-parda azul-plomo', B: BL, C: BL, D: 'cobre' }, { ...base, peinados: 'trenza-una trenzas-dos', pieles: CLAROS }),
  A('europa-medieval-nino', 'Niño medieval', 'europa-medieval', [1000, 1500], 'm', 'baja', 'camisa-medieval calzas botas-cuero', { A: 'lana-parda verde-musgo', B: BL, C: 'lana-negra', D: 'cobre' }, { ...base, peinados: 'corto', pieles: CLAROS }),
  A('europa-medieval-nina', 'Niña medieval', 'europa-medieval', [1000, 1500], 'f', 'baja', 'cota-campesina delantal-campesino botas-cuero', { A: 'rojo-granza lana-parda', B: BL, C: BL, D: 'cobre' }, { ...base, peinados: 'trenza-una recogido', pieles: CLAROS }),
  A('roma-nino', 'Niño romano', 'roma', [-200, 400], 'm', 'baja', 'tunica-plebeya cinturon-cuerda sandalia-romana', { A: 'crudo lana-clara', B: BL, C: 'rojo-granza', D: 'bronce' }, { ...base, peinados: 'corto romano', pieles: 'clara-calida oliva-clara oliva' }),
  A('roma-nina', 'Niña romana', 'roma', [-200, 400], 'f', 'baja', 'tunica-romana cinturon-cuerda sandalia-romana', { A: 'crudo lana-clara rosa-palido', B: BL, C: 'rojo-granza', D: 'bronce' }, { ...base, peinados: 'trenza-una recogido', pieles: 'clara-calida oliva-clara oliva' }),
  A('grecia-nino', 'Niño griego', 'grecia', [-600, 300], 'm', 'baja', 'quiton-corto cinturon-cuerda sandalias-cuero', { A: 'blanco-lino crudo', B: BL, C: 'rojo-granza', D: 'bronce' }, { ...base, peinados: 'corto', pieles: 'clara-calida oliva-clara oliva' }),
  A('grecia-nina', 'Niña griega', 'grecia', [-600, 300], 'f', 'baja', 'quiton-corto cinturon-cuerda sandalias-cuero', { A: 'blanco-lino crudo rosa-palido', B: BL, C: 'azul-egipcio', D: 'bronce' }, { ...base, peinados: 'trenza-una recogido', pieles: 'clara-calida oliva-clara oliva' }),
  A('andes-nino', 'Niño andino', 'andes', [1500, 1950], 'm', 'baja', 'unku poncho-andino sandalias-cuero', { A: 'lana-parda alpaca-cafe rojo-cochinilla', B: 'lana-clara', C: 'rojo-cochinilla', D: 'plata' }, { ...base, peinados: 'corto', pieles: OSCURA }),
  A('andes-nina', 'Niña andina', 'andes', [1500, 1950], 'f', 'baja', 'blusa-campesina pollera lliclla sandalias-cuero', { A: 'rojo-cochinilla azul-andino verde-andino', B: 'lana-clara', C: 'rojo-cochinilla', D: 'plata' }, { ...base, peinados: 'trenzas-dos', pieles: OSCURA }),
  A('corea-nino', 'Niño coreano', 'asia-este', [1700, 1950], 'm', 'baja', 'hanbok-jeogori hanbok-baji zapatos-tela', { A: 'azul-celeste verde-agua crema', B: BL, C: 'rojo-cartamo', D: 'plata' }, { ...base, peinados: 'corto', pieles: 'clara clara-calida oliva-clara' }),
  A('corea-nina', 'Niña coreana', 'asia-este', [1700, 1950], 'f', 'baja', 'hanbok-jeogori hanbok-chima zapatos-tela', { A: 'rosa-palido azul-celeste verde-agua', B: BL, C: 'rojo-cartamo', D: 'plata' }, { ...base, peinados: 'trenza-una trenzas-dos', pieles: 'clara clara-calida oliva-clara' }),
  A('japon-nino', 'Niño japonés', 'asia-este', [1700, 1950], 'm', 'baja', 'yukata obi geta', { A: 'indigo azul-celeste', B: BL, C: 'indigo-profundo', D: 'plata' }, { ...base, peinados: 'corto', pieles: 'clara clara-calida oliva-clara' }),
  A('japon-nina', 'Niña japonesa', 'asia-este', [1700, 1950], 'f', 'baja', 'yukata obi geta', { A: 'rosa-palido rojo-cartamo azul-celeste', B: BL, C: 'rojo-cartamo', D: 'plata' }, { ...base, peinados: 'corto recogido', pieles: 'clara clara-calida oliva-clara' }),
  A('africa-oeste-nina', 'Niña de África occidental', 'africa-oeste', [1800, 1980], 'f', 'baja', 'kitenge-top kitenge-falda sandalias-cuero', { A: 'azafran rojo-cartamo azul-ultramar', B: BL, C: 'verde-hoja', D: 'oro' }, { ...base, peinados: 'trenzas-pegadas rapado', pieles: 'ebano-claro ebano ebano-profundo' }),
  A('africa-oeste-nino', 'Niño de África occidental', 'africa-oeste', [1800, 1980], 'm', 'baja', 'dashiki sokoto sandalias-cuero', { A: 'azul-ultramar azafran rojo-cartamo', B: BL, C: 'verde-hoja', D: 'oro' }, { ...base, peinados: 'rapado corto', pieles: 'ebano-claro ebano ebano-profundo' }),
  A('norteamerica-nino', 'Niño de Estados Unidos o Canadá', 'norteamerica-moderna', [1850, 1980], 'm', 'baja', 'camisa-cuadros pantalon-overol zapatillas-lona', { A: 'rojo-granza azul-denim verde-bosque', B: 'denim-claro', C: 'lana-negra', D: 'cobre' }, { ...base, peinados: 'corto', pieles: CLAROS }),
  A('norteamerica-nina', 'Niña de Estados Unidos o Canadá', 'norteamerica-moderna', [1850, 1980], 'f', 'baja', 'vestido-sencillo-corto delantal-blanco zapatos-hebilla', { A: 'azul-celeste rosa-palido verde-agua', B: BL, C: BL, D: 'plata' }, { ...base, peinados: 'trenza-una trenzas-dos', pieles: CLAROS }),
  A('latinoamerica-nino', 'Niño latinoamericano', 'latinoamerica-moderna', [1850, 1980], 'm', 'baja', 'camisa-blanca short chaleco-lana zapatos-vestir', { A: 'azul-marino gris-oscuro lana-parda', B: BL, C: 'lana-negra', D: 'plata' }, { ...base, peinados: 'corto', pieles: 'clara-calida oliva-clara oliva canela' }),
  A('latinoamerica-nina', 'Niña latinoamericana', 'latinoamerica-moderna', [1850, 1980], 'f', 'baja', 'vestido-sencillo-corto delantal-blanco zapatos-hebilla', { A: 'azul-celeste rosa-palido verde-agua', B: BL, C: BL, D: 'plata' }, { ...base, peinados: 'trenza-una trenzas-dos', pieles: 'clara-calida oliva-clara oliva canela' }),
];

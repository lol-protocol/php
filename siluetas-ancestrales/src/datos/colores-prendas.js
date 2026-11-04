// Catálogo de colores por prenda, con la probabilidad de cada color.
//
// Hay dos orígenes:
//  1. PERFIL: la prenda tiene sus propios colores y pesos (los jeans solo en azules y grises; la camisa blanca casi
//     siempre blanca; el calzado en cueros y negros). Los pesos son relativos: se normalizan a 100 %.
//  2. ATUENDO: la prenda no tiene perfil propio y toma el color de la paleta del atuendo donde aparece (el chamal
//     mapuche, el kimono…). Su probabilidad se calcula con lo que el generador hace de verdad: el reparto
//     entre los atuendos que la llevan y, dentro de cada uno, entre los tintes de su letra de la paleta.
// En los dos casos se descartan los tintes que no existían en el año pedido (el denim es posterior a 1873).
//
// Los pesos son una estimación razonada, no una estadística medida: sirven para que la ropa se vea verosímil y
// para que se pueda ver y cambiar con qué frecuencia sale cada color.

import { TINTES_POR_ID } from './colores.js';
import { ATUENDOS } from './atuendos.js';
import { PRENDAS_POR_ID } from './prendas.js';

/** Perfiles reutilizables: nombre → [tinte, peso]. */
export const PERFILES = {
  BLANCOS: [['blanco-lino', 30], ['crudo', 22], ['marfil', 14], ['algodon-crudo', 12], ['blanco-optico', 10], ['crema', 8], ['lana-clara', 4]],
  BLANCOS_RUSTICOS: [['crudo', 35], ['algodon-crudo', 20], ['lana-clara', 15], ['blanco-lino', 15], ['beige', 8], ['marfil', 7]],
  LINOS_ANTIGUOS: [['blanco-lino', 55], ['crudo', 25], ['marfil', 12], ['lana-clara', 8]],
  NOVIA: [['blanco-optico', 40], ['marfil', 30], ['crema', 20], ['blanco-lino', 10]],
  LUTO: [['negro-carbon', 45], ['lana-negra', 25], ['negro-agalla', 12], ['gris-oscuro', 10], ['borravino', 4], ['azul-marino', 4]],
  DENIM: [['azul-denim', 40], ['denim-oscuro', 25], ['denim-claro', 18], ['denim-gris', 9], ['denim-negro', 8]],
  OVEROL: [['azul-denim', 35], ['denim-oscuro', 20], ['indigo', 15], ['azul-marino', 10], ['caqui-militar', 8], ['gris-oscuro', 6], ['kaki', 6]],
  PANTALON_70: [['azul-denim', 30], ['denim-claro', 20], ['denim-oscuro', 12], ['camel', 10], ['gris-oscuro', 10], ['negro-carbon', 8], ['burdeos', 5], ['mostaza', 5]],
  AZULES_TRABAJO: [['indigo', 30], ['azul-marino', 15], ['azul-plomo', 15], ['indigo-profundo', 12], ['gris-pizarra', 10], ['denim-gris', 6], ['caqui-militar', 6], ['kaki', 6]],
  TRABAJO: [['caqui-militar', 22], ['azul-marino', 16], ['kaki', 16], ['gris-oscuro', 14], ['indigo', 12], ['nogal', 8], ['verde-oliva-oscuro', 7], ['lana-parda', 5]],
  LANAS_OSCURAS: [['lana-negra', 20], ['gris-oscuro', 18], ['azul-marino', 16], ['gris-pizarra', 12], ['nogal', 8], ['chocolate', 6], ['gris-ceniza', 6], ['borravino', 4], ['verde-oliva-oscuro', 4], ['camel', 3], ['burdeos', 3]],
  ABRIGOS: [['camel', 20], ['gris-claro', 16], ['beige', 14], ['gris-oscuro', 12], ['azul-marino', 10], ['nogal', 8], ['crema', 6], ['lana-negra', 7], ['umbra', 7]],
  TIERRAS: [['lana-parda', 22], ['nogal', 14], ['umbra', 14], ['tabaco', 10], ['castana', 8], ['kaki', 8], ['gris-ceniza', 8], ['verde-musgo', 8], ['siena', 4], ['chocolate', 4]],
  PIELES: [['camel', 20], ['chocolate', 20], ['lana-negra', 18], ['gris-oscuro', 15], ['beige', 12], ['crema', 8], ['nogal', 7]],
  IMPERMEABLE: [['beige', 28], ['caqui-militar', 22], ['gris-oscuro', 16], ['azul-marino', 14], ['lana-negra', 12], ['verde-oliva-oscuro', 8]],
  UNIFORME_MILITAR: [['verde-militar', 30], ['caqui-militar', 28], ['azul-militar', 18], ['gris-oscuro', 10], ['lana-negra', 8], ['verde-oliva-oscuro', 6]],
  UNIFORME_AZUL: [['azul-marino', 40], ['azul-militar', 25], ['gris-oscuro', 15], ['lana-negra', 10], ['gris-pizarra', 10]],
  FALDA_COLEGIAL: [['azul-marino', 35], ['gris-oscuro', 22], ['burdeos', 12], ['verde-bosque', 10], ['gris-claro', 8], ['azul-militar', 8], ['verde-oliva-oscuro', 5]],
  FALDAS_CIUDAD: [['lana-negra', 16], ['azul-marino', 14], ['gris-oscuro', 14], ['burdeos', 10], ['nogal', 8], ['verde-bosque', 8], ['camel', 8], ['gris-claro', 8], ['terracota', 6], ['azul-real', 4], ['rosa-viejo', 4]],
  PANTALON_VESTIR: [['lana-negra', 18], ['gris-oscuro', 18], ['azul-marino', 14], ['gris-claro', 12], ['nogal', 10], ['chocolate', 8], ['beige', 8], ['caqui-militar', 6], ['camel', 6]],
  CAMISA_CUADROS: [['rojo-granza', 18], ['azul-marino', 14], ['verde-bosque', 12], ['indigo', 10], ['burdeos', 10], ['caqui-militar', 8], ['mostaza', 8], ['gris-pizarra', 8], ['terracota', 6], ['azul-real', 6]],
  CAMISA_TRABAJO: [['azul-celeste', 20], ['crudo', 15], ['caqui-militar', 15], ['gris-claro', 12], ['azul-marino', 10], ['kaki', 10], ['blanco-optico', 8], ['verde-militar', 5], ['beige', 5]],
  POLERAS: [['blanco-optico', 20], ['azul-marino', 12], ['gris-claro', 12], ['rojo-vivo', 10], ['negro-carbon', 8], ['verde-bosque', 8], ['azul-real', 8], ['mostaza', 7], ['amarillo-limon', 5], ['burdeos', 5], ['crema', 5]],
  SUETERES: [['crema', 16], ['gris-oscuro', 14], ['azul-marino', 12], ['burdeos', 10], ['verde-bosque', 10], ['camel', 10], ['chocolate', 8], ['lana-parda', 8], ['gris-claro', 8], ['rojo-granza', 4]],
  SEDA: [['crema', 20], ['marfil', 15], ['blanco-optico', 12], ['rosa-viejo', 12], ['azul-celeste', 10], ['lila', 8], ['verde-agua', 8], ['burdeos', 5], ['azafran', 4], ['rosa-fucsia', 3], ['amarillo-limon', 3]],
  VESTIDOS_DIA: [['azul-marino', 12], ['verde-bosque', 9], ['burdeos', 8], ['rosa-viejo', 8], ['azul-celeste', 8], ['lana-negra', 8], ['mostaza', 6], ['crema', 6], ['terracota', 6], ['azul-real', 6], ['verde-agua', 6], ['rojo-vivo', 5], ['gris-claro', 5], ['lila', 4], ['azafran', 3]],
  VESTIDOS_GALA: [['negro-carbon', 16], ['burdeos', 14], ['azul-marino', 10], ['verde-esmeralda', 10], ['rojo-vivo', 10], ['azul-real', 9], ['violeta', 7], ['marfil', 6], ['rosa-fucsia', 5], ['plata', 5], ['rosa-viejo', 4], ['oro', 4]],
  GUAYABERA: [['blanco-lino', 35], ['crema', 20], ['azul-celeste', 15], ['beige', 12], ['marfil', 8], ['verde-agua', 5], ['rosa-viejo', 5]],
  LIQUILIQUI: [['blanco-lino', 30], ['crema', 25], ['beige', 15], ['marfil', 10], ['caqui-militar', 8], ['azul-celeste', 7], ['verde-agua', 5]],
  GUARDAPOLVO: [['blanco-optico', 45], ['blanco-lino', 20], ['azul-celeste', 12], ['crema', 10], ['gris-claro', 8], ['caqui-militar', 5]],
  CAMISA_MANTA: [['crudo', 50], ['algodon-crudo', 25], ['blanco-lino', 15], ['beige', 10]],
  CAMISON: [['blanco-lino', 35], ['crema', 20], ['rosa-viejo', 12], ['azul-celeste', 12], ['marfil', 11], ['lila', 5], ['verde-agua', 5]],
  ESTAMPADOS_VIVOS: [['azul-real', 14], ['turquesa', 12], ['rojo-vivo', 12], ['verde-lima', 10], ['naranja-vivo', 10], ['amarillo-limon', 10], ['rosa-fucsia', 10], ['azul-celeste', 8], ['blanco-optico', 8], ['lila', 6]],
  CHAMANTO: [['rojo-granza', 22], ['azul-andino', 18], ['verde-hoja', 14], ['rojo-cochinilla', 14], ['lana-negra', 12], ['azafran', 10], ['crudo', 6], ['violeta', 4]],
  CORBATA: [['azul-marino', 20], ['burdeos', 18], ['lana-negra', 14], ['verde-bosque', 10], ['gris-oscuro', 8], ['borravino', 8], ['rojo-vivo', 8], ['azul-real', 6], ['mostaza', 5], ['camel', 3]],
  CALZADO_CUERO: [['cuero-oscuro', 34], ['negro-humo', 28], ['cuero', 20], ['nogal', 10], ['chocolate', 5], ['cuero-claro', 3]],
  CUERO_CHAQUETA: [['cuero-oscuro', 38], ['negro-humo', 28], ['cuero', 22], ['tabaco', 8], ['nogal', 4]],
  CALZADO_RUSTICO: [['cuero', 32], ['cuero-oscuro', 25], ['cuero-claro', 18], ['tabaco', 12], ['nogal', 8], ['umbra', 5]],
  CALZADO_TELA: [['crudo', 26], ['negro-carbon', 20], ['azul-marino', 15], ['indigo', 12], ['gris-claro', 10], ['blanco-optico', 10], ['rojo-vivo', 7]],
  CALZADO_GOMA: [['negro-carbon', 60], ['gris-oscuro', 15], ['verde-militar', 15], ['azul-marino', 10]],
  CINTURONES: [['cuero-oscuro', 40], ['negro-humo', 28], ['cuero', 22], ['nogal', 10]],
  METALES: [['oro', 32], ['plata', 30], ['bronce', 14], ['cobre', 12], ['hierro', 6], ['turquesa', 6]],
  PERLAS: [['marfil', 45], ['blanco-optico', 20], ['crema', 20], ['rosa-viejo', 10], ['gris-claro', 5]],
  CUENTAS: [['turquesa', 15], ['rojo-cartamo', 14], ['azul-ultramar', 10], ['azafran', 10], ['blanco-lino', 12], ['verde-malaquita', 8], ['negro-humo', 8], ['azul-celeste', 8], ['bermellon', 8], ['oro', 7]],
  COSTURA_DENIM: [['mostaza', 35], ['ocre-amarillo', 30], ['crudo', 20], ['cobre', 15]],
  BOTONES: [['marfil', 30], ['crudo', 18], ['cuero-oscuro', 14], ['negro-humo', 14], ['bronce', 10], ['plata', 8], ['cobre', 6]],
  BOTONES_METAL: [['cobre', 45], ['bronce', 30], ['hierro', 15], ['plata', 10]],
  CAMISA_VESTIR: [['blanco-optico', 30], ['azul-celeste', 20], ['crema', 12], ['rosa-viejo', 8], ['gris-claro', 8], ['lila', 5], ['verde-agua', 5], ['mostaza', 4], ['azul-marino', 4], ['burdeos', 4]],
  SAFARI: [['beige', 30], ['caqui-militar', 25], ['crema', 15], ['kaki', 12], ['verde-oliva-oscuro', 10], ['blanco-lino', 8]],
  SUDADERA: [['gris-claro', 24], ['azul-marino', 16], ['gris-oscuro', 14], ['burdeos', 10], ['verde-bosque', 10], ['negro-carbon', 10], ['rojo-vivo', 6], ['mostaza', 5], ['azul-real', 5]],
  PARKA: [['verde-militar', 24], ['azul-marino', 20], ['caqui-militar', 15], ['negro-carbon', 14], ['burdeos', 10], ['gris-oscuro', 10], ['mostaza', 7]],
  BOMBER: [['negro-carbon', 28], ['verde-militar', 20], ['azul-marino', 20], ['burdeos', 12], ['gris-oscuro', 10], ['rojo-vivo', 5], ['mostaza', 5]],
  ABRIGOS_LUJO: [['camel', 22], ['lana-negra', 18], ['burdeos', 14], ['chocolate', 14], ['gris-oscuro', 12], ['verde-oliva-oscuro', 8], ['crema', 6], ['azul-marino', 6]],
  SASTRE: [['azul-marino', 22], ['lana-negra', 20], ['gris-oscuro', 15], ['crema', 10], ['burdeos', 10], ['camel', 10], ['verde-bosque', 7], ['rosa-viejo', 6]],
  BATA: [['crema', 20], ['rosa-viejo', 16], ['azul-celeste', 14], ['burdeos', 12], ['verde-agua', 12], ['lila', 10], ['gris-claro', 8], ['azul-marino', 8]],
  BATIN: [['burdeos', 24], ['azul-marino', 20], ['verde-bosque', 16], ['violeta', 12], ['lana-negra', 14], ['mostaza', 7], ['azul-real', 7]],
  CORSE: [['negro-carbon', 28], ['burdeos', 22], ['crema', 14], ['rosa-viejo', 12], ['azul-marino', 10], ['verde-bosque', 8], ['marfil', 6]],
  CARGO: [['caqui-militar', 28], ['verde-militar', 22], ['kaki', 18], ['beige', 14], ['gris-oscuro', 10], ['azul-marino', 8]],
  CAPRI: [['azul-denim', 20], ['crema', 18], ['rosa-viejo', 12], ['beige', 12], ['blanco-optico', 12], ['azul-marino', 10], ['negro-carbon', 8], ['rojo-vivo', 8]],
  PESCADOR: [['crudo', 30], ['beige', 25], ['azul-denim', 15], ['caqui-militar', 12], ['indigo', 10], ['blanco-optico', 8]],
  LEGGINGS: [['negro-carbon', 55], ['gris-oscuro', 15], ['azul-marino', 12], ['burdeos', 8], ['verde-bosque', 5], ['lana-negra', 5]],
  SAROUEL: [['crema', 20], ['terracota', 15], ['azul-marino', 12], ['verde-oliva-oscuro', 12], ['burdeos', 10], ['mostaza', 10], ['gris-claro', 10], ['rosa-viejo', 6], ['camel', 5]],
  PALAZZO: [['negro-carbon', 24], ['crema', 16], ['azul-marino', 14], ['burdeos', 12], ['verde-esmeralda', 10], ['blanco-optico', 10], ['rosa-viejo', 8], ['camel', 6]],
  JODHPUR: [['beige', 40], ['crema', 20], ['camel', 15], ['kaki', 15], ['blanco-optico', 10]],
  MONO: [['azul-marino', 25], ['caqui-militar', 20], ['verde-militar', 15], ['gris-oscuro', 15], ['indigo', 10], ['kaki', 10], ['blanco-optico', 5]],
  ACCESORIO_TELA: [['crema', 20], ['burdeos', 12], ['azul-marino', 12], ['rosa-viejo', 10], ['verde-bosque', 10], ['lana-negra', 10], ['mostaza', 8], ['camel', 8], ['gris-claro', 5], ['rojo-vivo', 5]],
  PIEL_CLARA: [['crema', 25], ['camel', 20], ['gris-claro', 18], ['chocolate', 15], ['lana-negra', 12], ['blanco-optico', 10]],
  TIRANTES: [['negro-carbon', 24], ['cuero-oscuro', 20], ['burdeos', 16], ['azul-marino', 14], ['verde-bosque', 10], ['mostaza', 8], ['rojo-vivo', 8]],
  TACONES: [['negro-carbon', 28], ['cuero-oscuro', 18], ['burdeos', 14], ['crema', 10], ['rojo-vivo', 8], ['azul-marino', 8], ['nogal', 8], ['marfil', 6]],
  CHANCLETAS: [['azul-real', 15], ['negro-carbon', 15], ['rojo-vivo', 12], ['crema', 12], ['verde-lima', 10], ['cuero', 12], ['blanco-optico', 10], ['naranja-vivo', 7], ['rosa-fucsia', 7]],
};

// Asignación: [rol, perfil, ids de prenda separados por espacios]. La primera coincidencia de (prenda, rol) manda.
const GRUPOS = [
  // Blancos y lienzos
  ['principal', 'BLANCOS', 'camisa-blanca sobrepelliz toga toga-praetexta delantal-blanco blusa-regional stola'],
  ['principal', 'LINOS_ANTIGUOS', 'kalasiris kalasiris-plisada shenti shenti-plisado peplos quiton-jonico quiton-corto tunica-bizantina dalmatica tunica-etrusca tunica-ibera tunica-fenicia'],
  ['principal', 'BLANCOS_RUSTICOS', 'camisa-medieval blusa-campesina camisa-lino-larga vestido-lino-largo tunica-corta-lana camisa-rotos camisa-globo cota-campesina rubakha rubakha-larga'],
  ['principal', 'NOVIA', 'vestido-novia'],
  ['principal', 'LUTO', 'vestido-luto sotana'],
  ['principal', 'CAMISA_MANTA', 'camisa-manta'],
  ['principal', 'GUARDAPOLVO', 'guardapolvo'],
  ['principal', 'GUAYABERA', 'guayabera'],
  ['principal', 'LIQUILIQUI', 'liquiliqui'],
  ['principal', 'CAMISON', 'camison'],
  // Azules, grises y trabajo
  ['principal', 'DENIM', 'pantalon-vaquero chaqueta-denim camisa-denim'],
  ['principal', 'OVEROL', 'pantalon-overol overol-peto'],
  ['principal', 'PANTALON_70', 'pantalon-campana'],
  ['principal', 'AZULES_TRABAJO', 'bombachas pantalon-chino'],
  ['principal', 'TRABAJO', 'pantalon-trabajo'],
  ['principal', 'CAMISA_TRABAJO', 'camisa-trabajo camisa-manga-corta'],
  ['principal', 'CAMISA_CUADROS', 'camisa-cuadros'],
  ['principal', 'POLERAS', 'polera polera-rayas'],
  ['principal', 'SUETERES', 'sueter-lana sueter-cuello-alto sueter-nordico cardigan chaleco-lana'],
  ['principal', 'SEDA', 'blusa-seda blusa-cuello-bebe'],
  ['principal', 'ESTAMPADOS_VIVOS', 'camisa-aloha muumuu pareo-estampado'],
  // Ciudad
  ['principal', 'LANAS_OSCURAS', 'saco-terno saco-cruzado chaleco-terno traje-sastre levita frac chaleco-corto chaleco-largo casaca jubon-renacentista chaqueta-corta'],
  ['principal', 'PANTALON_VESTIR', 'pantalon-vestir pantalon-recto pantalon-ajustado pantalon-corte-alto pantalon-golf short calzones-rodilla calzones-medievales'],
  ['principal', 'FALDAS_CIUDAD', 'falda-lapiz falda-campana falda-plisada falda-larga'],
  ['principal', 'FALDA_COLEGIAL', 'falda-colegial'],
  ['principal', 'VESTIDOS_DIA', 'vestido-casa vestido-30 vestido-40 vestido-50 vestido-flapper vestido-sencillo vestido-sencillo-corto'],
  ['principal', 'VESTIDOS_GALA', 'vestido-fiesta vestido-fiesta-manga'],
  ['principal', 'ABRIGOS', 'abrigo-corto abrigo-largo gabardina'],
  ['principal', 'PIELES', 'abrigo-pieles chaleco-pieles'],
  ['principal', 'IMPERMEABLE', 'impermeable'],
  ['principal', 'UNIFORME_MILITAR', 'chaqueta-militar pantalon-militar'],
    ['principal', 'CUERO_CHAQUETA', 'chaqueta-cuero delantal-obrero lederhosen'],
  ['principal', 'CHAMANTO', 'chamanto chamanto-fiesta'],
  ['principal', 'TIERRAS', 'calzon-manta bragas-holgadas ruana poncho-liso manta-huasa'],
  ['principal', 'CORBATA', 'corbata panuelo-cuello lazo-cuello'],
  ['principal', 'CINTURONES', 'cinturon-cuero cinturon-vaquero cinturon-fino cinturon-bolsa'],
  ['principal', 'PERLAS', 'perlas'],
  ['principal', 'METALES', 'collar-usej collar-imperial gargantilla collar-colgante collar-cemi hei-tiki trapelacucha brazaletes brazaletes-altos'],
  ['principal', 'CUENTAS', 'collar-cuentas cuentas-largas collar-ancho-cuentas collares-zulu guirnalda broche-ovalado'],
  ['principal', 'CALZADO_GOMA', 'botas-goma'],
  ['principal', 'CALZADO_TELA', 'zapatillas-lona zapatos-tela zapatillas'],
  ['principal', 'CALZADO_RUSTICO', 'zuecos botas-trabajo botas-cuero polainas-lana sandalias-cuero sandalia-romana caliga opanke mocasines botas-piel'],
  ['principal', 'CALZADO_CUERO', 'zapatos-cuero zapatos-hebilla zapatos-vestir botas-altas botas-vaqueras botas-militares botas-mongolas botas-tibetanas'],
  ['principal', 'BLANCOS', 'enaguas tallit dishdasha cuello-alto kihei'],
  ['principal', 'TIERRAS', 'bragae escapulario faldon-campesina capa-viaje capa-lluvia-paja sombrero-paja-capa manto-lana-ancho falda-lana-gruesa'],
  ['principal', 'BLANCOS_RUSTICOS', 'delantal-peto'],
  ['principal', 'LANAS_OSCURAS', 'kepam'],
  ['principal', 'VESTIDOS_GALA', 'capa-ceremonial capa-opera'],
  ['principal', 'LUTO', 'chador'],
  ['principal', 'SEDA', 'kaftan-corto'],
  ['principal', 'CHAMANTO', 'quechquemitl'],
  ['principal', 'CUENTAS', 'obijime'],
  // Ropa de los tipos nuevos
  ['principal', 'VESTIDOS_DIA', 'vestido-tirantes vestido-halter vestido-asimetrico vestido-corazon vestido-trapecio vestido-globo vestido-tubo vestido-camisero vestido-volantes vestido-pico falda-mini falda-capas'],
  ['principal', 'VESTIDOS_GALA', 'vestido-tirantes-largo vestido-corazon-gala vestido-sirena vestido-sirena-tirantes falda-sirena'],
  ['principal', 'SEDA', 'blusa-farol blusa-pagoda top-tirantes top-halter'],
  ['principal', 'CAMISA_VESTIR', 'camisa-clasica camisa-ajustada'],
  ['principal', 'SAFARI', 'camisa-safari chaqueta-safari'],
  ['principal', 'POLERAS', 'polo camiseta-cap camiseta-sisa'],
  ['principal', 'SUDADERA', 'sudadera-capucha'],
  ['principal', 'SUETERES', 'sueter-oversize'],
  ['principal', 'PARKA', 'parka-capucha anorak chaleco-acolchado'],
  ['principal', 'BOMBER', 'bomber'],
  ['principal', 'ABRIGOS_LUJO', 'abrigo-piel-total abrigo-punos-piel'],
  ['principal', 'SASTRE', 'blazer-ajustado'],
  ['principal', 'CUERO_CHAQUETA', 'chaqueta-motera'],
  ['principal', 'BATA', 'bata-casa'],
  ['principal', 'BATIN', 'batin-seda'],
  ['principal', 'CORSE', 'corse'],
  ['principal', 'DENIM', 'jeans-clasicos'],
  ['principal', 'PANTALON_VESTIR', 'pantalon-pliegues pantalon-tubo'],
  ['principal', 'PANTALON_70', 'pantalon-pitillo'],
  ['principal', 'CARGO', 'pantalon-cargo bermuda'],
  ['principal', 'CAPRI', 'pantalon-capri'],
  ['principal', 'PESCADOR', 'pantalon-pescador'],
  ['principal', 'LEGGINGS', 'leggings'],
  ['principal', 'SAROUEL', 'sarouel'],
  ['principal', 'FALDAS_CIUDAD', 'culotte falda-globo falda-trapecio falda-abertura falda-abertura-lado falda-midi'],
  ['principal', 'PALAZZO', 'pantalon-palazzo mono-elegante'],
  ['principal', 'JODHPUR', 'pantalon-jodhpur'],
  ['principal', 'MONO', 'mono-trabajo'],
  ['principal', 'PIEL_CLARA', 'cuello-piel'],
  ['principal', 'ACCESORIO_TELA', 'esclavina chalina panuelo-triangulo babero-cuello'],
  ['principal', 'TIRANTES', 'tirantes-pantalon'],
  ['principal', 'TACONES', 'tacones'],
  ['principal', 'CHANCLETAS', 'chancletas'],
  ['principal', 'CALZADO_CUERO', 'botines zapatos-oxford'],
  ['ribete', 'COSTURA_DENIM', 'jeans-clasicos'],
  ['detalle', 'BOTONES_METAL', 'jeans-clasicos'],
  ['detalle', 'BOTONES', 'camisa-clasica camisa-ajustada camisa-safari polo vestido-camisero chaqueta-safari parka-capucha anorak abrigo-piel-total abrigo-punos-piel blazer-ajustado chaleco-acolchado vestido-tirantes vestido-halter'],
  // Costuras y botones
  ['ribete', 'COSTURA_DENIM', 'pantalon-vaquero chaqueta-denim camisa-denim pantalon-overol overol-peto'],
  ['detalle', 'BOTONES_METAL', 'pantalon-vaquero chaqueta-denim camisa-denim pantalon-overol overol-peto'],
  ['detalle', 'BOTONES', 'camisa-blanca camisa-cuadros camisa-trabajo camisa-manga-corta polera sueter-lana cardigan chaleco-lana saco-terno saco-cruzado chaleco-terno traje-sastre gabardina abrigo-corto impermeable chaqueta-militar chaqueta-cuero guayabera liquiliqui guardapolvo vestido-casa vestido-30 vestido-40 vestido-50 vestido-novia vestido-luto blusa-seda blusa-cuello-bebe camison chaleco-corto chaleco-largo levita frac'],
];

const PERFIL_PRENDA = {};
for (const [rol, perfil, ids] of GRUPOS) {
  for (const id of ids.split(/\s+/).filter(Boolean)) {
    if (!PRENDAS_POR_ID[id]) continue; // ids de grupos que no existen se ignoran (ver las pruebas)
    PERFIL_PRENDA[id] ??= {};
    PERFIL_PRENDA[id][rol] ??= perfil;
  }
}
/** Ids que los grupos nombran y no existen: para que las pruebas los vean. */
export const IDS_DE_GRUPOS = GRUPOS.flatMap(([, , ids]) => ids.split(/\s+/).filter(Boolean));

/** Perfil propio de una prenda y un rol, o null si toma el color del atuendo. */
export function perfilDe(prendaId, rol = 'principal') {
  const p = PERFIL_PRENDA[prendaId]?.[rol];
  if (p) return { nombre: p, pesos: PERFILES[p] };
  return null;
}

const disponible = (tinteId, anio) => {
  const t = TINTES_POR_ID[tinteId];
  return !!t && (anio === undefined || anio === null || (anio >= t.desde && anio <= t.hasta));
};

function normalizar(pares, anio) {
  let lista = pares.filter(([id]) => disponible(id, anio));
  if (!lista.length) lista = pares.filter(([id]) => TINTES_POR_ID[id]);
  const total = lista.reduce((s, [, w]) => s + w, 0) || 1;
  return lista.map(([id, w]) => ({ id, nombre: TINTES_POR_ID[id].nombre, hex: TINTES_POR_ID[id].hex, pct: Math.round((w / total) * 1000) / 10 }))
    .sort((a, b) => b.pct - a.pct);
}

const cache = new Map();

/**
 * Probabilidad de cada color de una prenda para un rol ('principal', 'secundario', 'ribete', 'detalle').
 * Devuelve [{ id, nombre, hex, pct }] de mayor a menor, sumando 100. Con `anio` descarta los tintes que aún no existían;
 * con `atuendo` (id) limita el cálculo derivado a ese atuendo.
 */
export function distribucionColor(prendaId, rol = 'principal', { anio, atuendo } = {}) {
  const clave = `${prendaId}|${rol}|${anio ?? ''}|${atuendo ?? ''}`;
  if (cache.has(clave)) return cache.get(clave);
  const prenda = PRENDAS_POR_ID[prendaId];
  if (!prenda) throw new Error(`Prenda desconocida: ${prendaId}`);
  let r;
  const propio = perfilDe(prendaId, rol);
  if (propio) r = normalizar(propio.pesos, anio).map((x) => ({ ...x, origen: `perfil ${propio.nombre}` }));
  else {
    const letra = prenda.col[rol];
    let usos = ATUENDOS.filter((a) => a.prendas.includes(prendaId));
    if (atuendo) usos = usos.filter((a) => a.id === atuendo);
    else if (anio !== undefined && anio !== null) { const cerca = usos.filter((a) => anio >= a.epoca[0] && anio <= a.epoca[1]); if (cerca.length) usos = cerca; }
    const acum = new Map();
    for (const a of usos) {
      const ids = (a.paleta[letra] ?? []).filter((id) => disponible(id, anio));
      const validos = ids.length ? ids : (a.paleta[letra] ?? []);
      for (const id of validos) acum.set(id, (acum.get(id) ?? 0) + 1 / usos.length / validos.length);
    }
    r = acum.size ? normalizar([...acum.entries()], anio).map((x) => ({ ...x, origen: 'atuendo' })) : [];
  }
  cache.set(clave, r);
  return r;
}

/** Todos los roles de color de una prenda: { principal: [...], secundario: [...], ... } (solo los que tienen datos). */
export function catalogoColores(prendaId, opc = {}) {
  const prenda = PRENDAS_POR_ID[prendaId];
  if (!prenda) throw new Error(`Prenda desconocida: ${prendaId}`);
  const out = {};
  for (const rol of Object.keys(prenda.col)) {
    const d = distribucionColor(prendaId, rol, opc);
    if (d.length) out[rol] = d;
  }
  return out;
}

/**
 * Elige un color del perfil propio de la prenda (null si no tiene perfil para ese rol).
 * `preferidos`: ids de tintes que el atuendo ya usa; pesan `refuerzo` veces más.
 */
export function muestrearColor(prendaId, rol, azar, { anio, preferidos = [], refuerzo = 2.5 } = {}) {
  const propio = perfilDe(prendaId, rol);
  if (!propio) return null;
  const base = normalizar(propio.pesos, anio);
  const pesos = base.map((x) => [x, x.pct * (preferidos.includes(x.id) ? refuerzo : 1)]);
  const elegido = azar.ponderado(pesos);
  return { id: elegido.id, nombre: elegido.nombre, hex: elegido.hex, pct: elegido.pct };
}

/**
 * Elige un color para cualquier prenda: del perfil propio si lo tiene y, si no, del reparto derivado de los atuendos
 * que la llevan (para vestir una prenda suelta, sin atuendo). Devuelve null si la prenda no tiene colores para ese papel.
 */
export function muestrearColorLibre(prendaId, rol, azar, { anio, preferidos = [], refuerzo = 2.5 } = {}) {
  const lista = distribucionColor(prendaId, rol, { anio });
  if (!lista.length) return null;
  const elegido = azar.ponderado(lista.map((x) => [x, x.pct * (preferidos.includes(x.id) ? refuerzo : 1)]));
  return { id: elegido.id, nombre: elegido.nombre, hex: elegido.hex, pct: elegido.pct, origen: elegido.origen };
}

// Pueblos y culturas para estimar, a partir de la etnia, la región del atuendo, la piel, el cabello, los peinados,
// la barba y la estatura. Son promedios aproximados de poblaciones, no reglas sobre una persona: dentro de cada pueblo
// hay mucha variación y por eso el generador siempre elige al azar dentro de un rango. «Etnia» aquí es cultura de
// origen y no una categoría biológica.
//
// pieles/peinados: listas de ids (colores.js, cabeza.js). cabellos: [id, peso]. altura: [mujer, hombre] en cm de adultos.
// regiones: de dónde salen los atuendos (atuendos.js). prefijos: para separar pueblos que comparten región por el id del atuendo.
// barba: probabilidad de que un adulto lleve barba o bigote. corpulencia: [nombre, peso].

import { E, OSC, MORENO, CLARO, MED, MESTIZO } from './ayuda.js';
import { ETNIAS_AMPLIADAS } from './etnias-ampliadas.js';

export const ETNIAS = [
  // ----- Chile y el cono sur -----
  E('mapuche', 'Mapuche', { grupo: 'Pueblos originarios de Chile y Argentina', regiones: 'mapuche', pieles: 'canela bronce cobre oliva', cabellos: OSC, peinados: 'trenzas-dos largo-liso flequillo corto', barba: 0.08, altura: [153, 165], corpulencia: [['delgada', 1], ['media', 3], ['robusta', 2]], nota: 'Incluye pehuenche, huilliche y lafkenche cuando no hay un traje propio en el catálogo.' }),
  E('huilliche', 'Huilliche', { grupo: 'Pueblos originarios de Chile y Argentina', regiones: 'mapuche chile-rural', pieles: 'canela bronce cobre oliva', cabellos: OSC, peinados: 'trenzas-dos largo-liso flequillo corto', barba: 0.08, altura: [153, 165] }),
  E('aymara', 'Aymara', { grupo: 'Pueblos andinos', regiones: 'andes', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'trenzas-dos largo-liso flequillo corto', barba: 0.06, altura: [150, 162], corpulencia: [['media', 3], ['robusta', 3], ['delgada', 0.7]], nota: 'Pecho ancho por la altura; la estimación de estatura es de poblaciones del altiplano.' }),
  E('quechua', 'Quechua', { grupo: 'Pueblos andinos', regiones: 'andes', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'trenzas-dos largo-liso flequillo corto', barba: 0.06, altura: [151, 163], corpulencia: [['media', 3], ['robusta', 2.5], ['delgada', 0.8]] }),
  E('inca', 'Inca (histórico)', { grupo: 'Pueblos andinos', regiones: 'andes', prefijos: 'inca', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'flequillo largo-liso trenzas-dos corto', barba: 0.04, altura: [150, 162] }),
  E('atacameno', 'Atacameño (lickanantay)', { grupo: 'Pueblos andinos', regiones: 'andes', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'trenzas-dos largo-liso corto', barba: 0.06, altura: [152, 164] }),
  E('selknam', 'Selk\'nam (ona)', { grupo: 'Pueblos de la Patagonia y Tierra del Fuego', regiones: 'patagonia-fueguina', prefijos: 'selknam', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'largo-liso corto', barba: 0.05, altura: [165, 178], corpulencia: [['media', 4], ['robusta', 2]], nota: 'Se cuentan entre los pueblos más altos de América en los registros del siglo XIX.' }),
  E('kawesqar', 'Kawésqar y yagán', { grupo: 'Pueblos de la Patagonia y Tierra del Fuego', regiones: 'patagonia-fueguina', prefijos: 'kawesqar', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'largo-liso corto', barba: 0.05, altura: [148, 160], nota: 'Pueblos canoeros; el catálogo los representa con trajes de piel genéricos de la región.' }),
  E('rapanui', 'Rapanui', { grupo: 'Polinesia', regiones: 'rapa-nui', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'corto largo-ondulado trenza-una mono-alto', barba: 0.3, altura: [160, 172], corpulencia: [['media', 3], ['robusta', 3]] }),
  E('polinesio', 'Polinesio', { grupo: 'Polinesia', regiones: 'polinesia rapa-nui', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'corto largo-ondulado trenza-una mono-alto', barba: 0.3, altura: [160, 173], corpulencia: [['media', 3], ['robusta', 3]] }),
  E('criollo-chileno', 'Criollo y mestizo chileno', { grupo: 'Chile', regiones: 'chile-rural hispanoamerica-colonial', pieles: 'clara-calida oliva-clara oliva canela', cabellos: MESTIZO, peinados: 'corto trenzas-dos recogido bob', barba: 0.4, altura: [155, 167], nota: 'Mezcla de ascendencia europea e indígena; la piel se estima en un rango amplio.' }),
  E('espanol', 'Español y vasco', { grupo: 'Europa', regiones: 'hispanoamerica-colonial europa-moderna europa-xix europa-medieval', pieles: 'porcelana clara clara-calida oliva-clara', cabellos: MED, peinados: 'corto recogido bob', barba: 0.45, altura: [157, 168] }),
  E('rioplatense', 'Criollo rioplatense', { grupo: 'Río de la Plata', regiones: 'rio-de-la-plata', pieles: 'clara clara-calida oliva-clara oliva', cabellos: MESTIZO, peinados: 'corto', barba: 0.5, altura: [158, 170] }),
  E('afrodescendiente', 'Afrodescendiente de las Américas', { grupo: 'Pueblos africanos y afrodescendientes', regiones: 'hispanoamerica-colonial africa-oeste', pieles: 'castana ebano-claro ebano', cabellos: [['negro', 9], ['castano-oscuro', 1]], peinados: 'afro rizos trenzas-dos rapado corto', barba: 0.4, altura: [158, 170], nota: 'El catálogo no tiene trajes específicos de comunidades afrodescendientes de las Américas: usa los coloniales y los de África occidental.' }),
  // ----- Mesoamérica y Norteamérica -----
  E('maya', 'Maya', { grupo: 'Pueblos de Mesoamérica', regiones: 'mesoamerica', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'trenzas-dos largo-liso flequillo', barba: 0.05, altura: [145, 157] }),
  E('nahua', 'Nahua (azteca)', { grupo: 'Pueblos de Mesoamérica', regiones: 'mesoamerica', pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'coletas corona-trenzas flequillo corto', barba: 0.05, altura: [147, 160] }),
  E('pueblos-llanuras', 'Pueblos de las llanuras norteamericanas', { grupo: 'Pueblos de Norteamérica', regiones: 'norteamerica', pieles: 'canela bronce cobre oliva', cabellos: OSC, peinados: 'trenzas-dos largo-liso', barba: 0.05, altura: [160, 174] }),
  E('inuit', 'Inuit', { grupo: 'Pueblos del Ártico', regiones: 'artico', pieles: 'clara-calida oliva-clara oliva', cabellos: OSC, peinados: 'trenzas-dos largo-liso corto', barba: 0.08, altura: [150, 160], corpulencia: [['media', 3], ['robusta', 3]] }),
  // ----- Europa -----
  E('europeo-occidental', 'Europeo occidental', { grupo: 'Europa', regiones: 'europa-medieval europa-moderna europa-xix', pieles: 'porcelana clara clara-calida', cabellos: CLARO, peinados: 'corto bob trenza-una recogido', barba: 0.4, altura: [160, 171] }),
  E('europeo-mediterraneo', 'Europeo mediterráneo (italiano, griego)', { grupo: 'Europa', regiones: 'roma grecia bizancio europa-moderna europa-xix europa-medieval', pieles: 'clara-calida oliva-clara oliva', cabellos: MED, peinados: 'corto recogido romano', barba: 0.5, altura: [157, 169] }),
  E('europeo-del-norte', 'Europeo del norte (nórdico, germano)', { grupo: 'Europa', regiones: 'germano europa-medieval europa-moderna', pieles: 'porcelana clara', cabellos: [['rubio', 4], ['rubio-oscuro', 3], ['castano-claro', 2], ['pelirrojo', 1.5], ['castano', 1]], peinados: 'trenza-una largo-liso corto recogido', barba: 0.5, altura: [166, 178] }),
  E('celta', 'Celta (Irlanda, Escocia, Gales)', { grupo: 'Europa', regiones: 'germano', pieles: 'porcelana clara', cabellos: [['pelirrojo', 2.5], ['castano', 3], ['castano-claro', 2], ['castano-oscuro', 2], ['negro', 1], ['rubio-oscuro', 1]], peinados: 'largo-liso corto largo-ondulado', barba: 0.45, altura: [160, 172] }),
  E('eslavo', 'Eslavo', { grupo: 'Europa', regiones: 'eslavo', pieles: 'porcelana clara clara', cabellos: [['rubio-oscuro', 3], ['castano-claro', 3], ['rubio', 2], ['castano', 2], ['rubio-ceniza', 1]], peinados: 'trenza-una coletas bob corto', barba: 0.55, altura: [162, 174] }),
  E('judio-askenazi', 'Judío askenazi', { grupo: 'Comunidades judías', regiones: 'judio', pieles: 'porcelana clara clara-calida oliva-clara', cabellos: MED, peinados: 'corto', barba: 0.7, altura: [157, 168] }),
  E('hebreo-antiguo', 'Hebreo antiguo', { grupo: 'Comunidades judías', regiones: 'judio', pieles: 'oliva-clara oliva canela', cabellos: [['negro', 6], ['castano-oscuro', 3]], peinados: 'corto largo-ondulado trenzas-dos', barba: 0.75, altura: [152, 164] }),
  E('egipcio-antiguo', 'Egipcio antiguo', { grupo: 'Mundo antiguo', regiones: 'egipto', pieles: 'oliva canela bronce', cabellos: [['negro', 8], ['castano-oscuro', 1]], peinados: 'peluca-egipcia bob rapado', barba: 0.05, altura: [152, 165] }),
  E('mesopotamico', 'Sumerio, acadio y asirio', { grupo: 'Mundo antiguo', regiones: 'mesopotamia', pieles: 'oliva-clara oliva canela', cabellos: [['negro', 7], ['castano-oscuro', 2]], peinados: 'rizos recogido corto', barba: 0.85, altura: [152, 165] }),
  E('griego-antiguo', 'Griego antiguo', { grupo: 'Mundo antiguo', regiones: 'grecia', pieles: 'clara-calida oliva-clara oliva', cabellos: MED, peinados: 'corto rizos recogido corona-trenzas', barba: 0.6, altura: [153, 167] }),
  E('romano-antiguo', 'Romano antiguo', { grupo: 'Mundo antiguo', regiones: 'roma', pieles: 'clara-calida oliva-clara oliva', cabellos: MED, peinados: 'romano corto recogido', barba: 0.15, altura: [154, 168], nota: 'Los hombres romanos solían ir afeitados.' }),
  E('bizantino', 'Bizantino', { grupo: 'Mundo antiguo', regiones: 'bizancio', pieles: 'clara-calida oliva-clara oliva', cabellos: MED, peinados: 'corto recogido', barba: 0.55, altura: [154, 167] }),
  // ----- Medio Oriente y África del norte -----
  E('arabe', 'Árabe', { grupo: 'Medio Oriente y África del norte', regiones: 'medio-oriente africa-norte', prefijos: 'beduino mujer-arabe maghreb palestin liban yemenit arabe-urbano', pieles: 'oliva-clara oliva canela bronce', cabellos: [['negro', 7], ['castano-oscuro', 2]], peinados: 'corto recogido', barba: 0.7, altura: [157, 169] }),
  E('persa', 'Persa', { grupo: 'Medio Oriente y África del norte', regiones: 'medio-oriente', prefijos: 'persa', pieles: 'oliva-clara oliva', cabellos: [['negro', 6], ['castano-oscuro', 3]], peinados: 'corto largo-liso', barba: 0.65, altura: [157, 170] }),
  E('turco-otomano', 'Turco y otomano', { grupo: 'Medio Oriente y África del norte', regiones: 'medio-oriente', prefijos: 'otomano otomana', pieles: 'clara-calida oliva-clara oliva', cabellos: MED, peinados: 'corto', barba: 0.7, altura: [158, 171] }),
  E('bereber', 'Bereber y magrebí', { grupo: 'Medio Oriente y África del norte', regiones: 'africa-norte', pieles: 'oliva-clara oliva canela bronce', cabellos: [['negro', 6], ['castano-oscuro', 3]], peinados: 'corto trenzas-dos recogido', barba: 0.65, altura: [157, 169] }),
  // ----- Asia -----
  E('indio-subcontinente', 'Indio del subcontinente', { grupo: 'Asia', regiones: 'asia-sur asia-sur-regional', pieles: 'oliva canela bronce cobre castana', cabellos: [['negro', 8], ['negro-azulado', 2]], peinados: 'trenza-una recogido corto', barba: 0.55, altura: [151, 164] }),
  E('chino-han', 'Chino han', { grupo: 'Asia', regiones: 'asia-este', prefijos: 'china-', pieles: 'clara clara-calida oliva-clara', cabellos: [['negro', 8], ['negro-azulado', 2]], peinados: 'mono-alto recogido trenza-una bob', barba: 0.2, altura: [155, 167] }),
  E('japones', 'Japonés', { grupo: 'Asia', regiones: 'asia-este', prefijos: 'japon-', pieles: 'porcelana clara clara-calida', cabellos: [['negro', 8], ['negro-azulado', 2]], peinados: 'mono-chonmage recogido mono-alto', barba: 0.2, altura: [153, 165] }),
  E('coreano', 'Coreano', { grupo: 'Asia', regiones: 'asia-este', prefijos: 'corea-', pieles: 'porcelana clara clara-calida', cabellos: [['negro', 8], ['negro-azulado', 2]], peinados: 'recogido trenza-una mono-chonmage', barba: 0.2, altura: [155, 167] }),
  E('malayo-indonesio', 'Malayo e indonesio', { grupo: 'Asia', regiones: 'sudeste-asiatico', prefijos: 'sudeste', pieles: 'oliva canela bronce', cabellos: [['negro', 8], ['negro-azulado', 2]], peinados: 'recogido corto mono-alto', barba: 0.25, altura: [148, 160] }),
  // ----- África subsahariana -----
  E('africano-occidental', 'Africano occidental', { grupo: 'África subsahariana', regiones: 'africa-oeste', pieles: 'castana ebano-claro ebano ebano-profundo', cabellos: [['negro', 10]], peinados: 'rapado corto afro trenzas-dos rizos', barba: 0.4, altura: [160, 172] }),
  E('zulu', 'Zulú y bantú austral', { grupo: 'África subsahariana', regiones: 'africa-este-sur', prefijos: 'zulu-', pieles: 'castana ebano-claro ebano', cabellos: [['negro', 10]], peinados: 'rapado corto', barba: 0.3, altura: [157, 169] }),
  E('maasai', 'Maasái', { grupo: 'África subsahariana', regiones: 'africa-este-sur', prefijos: 'maasai', pieles: 'ebano-claro ebano ebano-profundo', cabellos: [['negro', 10]], peinados: 'rapado rastas', barba: 0.05, altura: [165, 178], corpulencia: [['delgada', 3], ['media', 3]], nota: 'Estatura alta y complexión delgada son típicas de los registros de pastores de África oriental.' }),
  ...ETNIAS_AMPLIADAS,
];

export const ETNIAS_POR_ID = Object.fromEntries(ETNIAS.map((e) => [e.id, e]));

# Listado de funciones por plataforma

> Generado desde [`catalogo.json`](catalogo.json) con `php interruptores/bin/generar.php`. **No lo edites a mano**: cambia el catálogo y vuelve a generar.

- **60 funciones** (39 de genealogía y 21 de POS).
- **209 opciones** (sub-interruptores), y **269 interruptores** en total.
- **8 funciones supuestas**, marcadas con *(supuesto)*: no vienen de la lista de la persona, las propuse a partir de sus respuestas. Hay que confirmarlas o quitarlas.

Cada función tiene su interruptor. Si tiene opciones, cada una tiene el suyo: una opción está encendida solo si su función también lo está. «Mínimo de opciones» dice cuántas tienen que quedar encendidas mientras la función esté encendida.

## Tipos

| Tipo | Significado |
|---|---|
| `producto` | Lo ve o lo usa una persona fuera del equipo |
| `interno` | Herramienta del equipo u operación |
| `datos` | Datos o base de referencia |
| `visual` | Recursos visuales (iconos, siluetas, portadas) |
| `contenido` | Textos y contenido |

## Orígenes

| Origen | Significado |
|---|---|
| `repo` | El código está en una carpeta de este repositorio (se comprueba que exista) |
| `fuera_del_repo` | La función vive en la aplicación principal, que no está en este repositorio |
| `pr_abierto` | Su carpeta todavía está en un PR abierto, no en master |

## A) Genealogía

| Id | Función | Opciones | Tipo | Origen | Por defecto | Depende de |
|---|---|---|---|---|---|---|
| `gen.vistas` | Modos de visualización del árbol | 14 (mín. 1) | producto | `fuera_del_repo` | encendida | — |
| `gen.pagos` | Medios de pago | 3 (mín. 1) | producto | `fuera_del_repo` | encendida | — |
| `gen.mascotas` | Perritos y gatitos (asistentes) | 2 | contenido | `fuera_del_repo` | encendida | — |
| `gen.exportar` | Exportar el árbol | 3 | producto | `fuera_del_repo` | encendida | — |
| `gen.idiomas` | Idioma de la interfaz | 2 (mín. 1) | producto | `repo` | encendida | — |
| `gen.estado-civil` | Estados de una persona | 8 | producto | `fuera_del_repo` | encendida | — |
| `gen.vinculos` | Tipos de vínculo | 12 | producto | `fuera_del_repo` | encendida | — |
| `gen.ficha` | Secciones de la ficha | 12 | producto | `fuera_del_repo` | encendida | — |
| `gen.verificacion` | Indicador de verificación | — | producto | `fuera_del_repo` | encendida | — |
| `gen.privacidad` | Protección de personas que podrían estar vivas | — | producto | `repo` | encendida | — |
| `gen.estilo-iconos` | Estilo de iconos | 3 (mín. 1) | visual | `repo` | encendida | — |
| `gen.acc-agregar` | Agregar familiares | 4 | producto | `fuera_del_repo` | encendida | — |
| `gen.acc-editar` | Editar y deshacer | 6 | producto | `fuera_del_repo` | encendida | — |
| `gen.acc-conectar` | Conectar personas | 5 | producto | `fuera_del_repo` | encendida | — |
| `gen.acc-fusion` | Fusionar, dividir y copiar fichas | 3 | producto | `fuera_del_repo` | encendida | — |
| `gen.acc-plegar` | Plegar y acercar el árbol | 4 | producto | `fuera_del_repo` | encendida | — |
| `gen.acc-compartir` | Compartir | 3 | producto | `fuera_del_repo` | encendida | — |
| `gen.acc-buscar` | Buscar y filtrar | 2 | producto | `fuera_del_repo` | encendida | — |
| `gen.acc-archivos` | Importar, subir e imprimir | 4 | producto | `fuera_del_repo` | encendida | — |
| `gen.fuentes-externas` | Importar desde fuentes externas | 16 | interno | `repo` | encendida | `gen.privacidad` |
| `gen.directorios` | Importar directorios telefónicos históricos | — | interno | `repo` | encendida | `gen.privacidad` |
| `gen.siluetas` | Siluetas de ancestros | 3 | visual | `repo` | encendida | — |
| `gen.mortalidad` | Datos de mortalidad histórica | 5 | datos | `repo` | encendida | — |
| `gen.paises` | Mapa y tabla de países | 3 | datos | `repo` | encendida | — |
| `gen.marketing` | Textos de marketing | 4 | contenido | `repo` | encendida | — |
| `gen.legal` | Consulta de leyes | 2 | datos | `repo` | encendida | — |
| `gen.filtro` | Filtro de contenido | 3 | producto | `repo` | encendida | — |
| `gen.retiros` | Pedidos de retiro de información | — | producto | `repo` | encendida | `gen.filtro:textos` |
| `gen.backoffice` | Backoffice de actividad | 5 | interno | `repo` | encendida | — |
| `gen.bak-notas` | Backoffice: notas por acción | — | interno | `repo` | encendida | `gen.backoffice`, `gen.filtro:textos` |
| `gen.cobros` | Cobros y cartera | 6 | interno | `repo` | encendida | — |
| `gen.funnel` | Funnel y cohortes | 3 | interno | `repo` | encendida | — |
| `gen.busqueda` | Búsqueda de personas en archivos y registros externos *(supuesto)* | — | interno | `fuera_del_repo` | encendida | `gen.privacidad` |
| `gen.prensa` | Búsqueda en diarios y revistas *(supuesto)* | 2 | interno | `fuera_del_repo` | encendida | `gen.privacidad` |
| `gen.congresos` | Búsqueda en congresos nacionales *(supuesto)* | — | interno | `fuera_del_repo` | encendida | `gen.privacidad` |
| `gen.deduccion` | Deducción de parentescos y datos *(supuesto)* | — | interno | `fuera_del_repo` | encendida | `gen.privacidad` |
| `gen.inferencia` | Inferencia de datos faltantes *(supuesto)* | — | interno | `fuera_del_repo` | encendida | `gen.privacidad` |
| `gen.estimacion` | Estimación de fechas y lugares *(supuesto)* | 2 | interno | `fuera_del_repo` | encendida | `gen.privacidad` |
| `gen.pasarela` | Pasarela de pagos *(supuesto)* | 1 | producto | `fuera_del_repo` | encendida | — |

### `gen.vistas` · Modos de visualización del árbol

Cómo se dibuja el árbol. Cada modo es una opción; sus iconos están en iconos-genealogia/ (prefijo view_).

Carpetas: `iconos-genealogia/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.vistas:circular` | Circular | encendida |
| `gen.vistas:abanico` | Abanico | encendida |
| `gen.vistas:arbol` | Árbol | encendida |
| `gen.vistas:pedigri` | Pedigrí | encendida |
| `gen.vistas:ancestros` | Ancestros | encendida |
| `gen.vistas:descendientes` | Descendientes | encendida |
| `gen.vistas:familia` | Grupo familiar | encendida |
| `gen.vistas:generaciones` | Generaciones | encendida |
| `gen.vistas:reloj-de-arena` | Reloj de arena | encendida |
| `gen.vistas:linea-de-tiempo` | Línea de tiempo | encendida |
| `gen.vistas:lista` | Lista | encendida |
| `gen.vistas:tarjetas` | Tarjetas | encendida |
| `gen.vistas:red` | Red | encendida |
| `gen.vistas:dispersion` | Dispersión | encendida |

### `gen.pagos` · Medios de pago

Qué formas de pago ofrece el sitio. La lista sale de los métodos que registra el panel de cobros (transferencia, tarjeta, efectivo); si hay más, se agregan aquí.

Al apagar: `no_disponible` (se muestra como no disponible en vez de ocultarse).

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.pagos:transferencia` | Transferencia | encendida |
| `gen.pagos:tarjeta` | Tarjeta | encendida |
| `gen.pagos:efectivo` | Efectivo | encendida |

### `gen.mascotas` · Perritos y gatitos (asistentes)

Dos asistentes, uno por interruptor: el perrito ladra y el gatito maúlla frases motivacionales con enfoque comercial.

*Nota:* Las frases comerciales deben seguir la guía de psychology-and-marketing/: persuadir sin presión indebida y sin usar datos personales.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.mascotas:perritos` | Perritos | encendida |
| `gen.mascotas:gatitos` | Gatitos | encendida |

### `gen.exportar` · Exportar el árbol

Formatos en los que se puede descargar un árbol o una ficha (iconos ui_export_*).

Carpetas: `iconos-genealogia/`.

Al apagar: `no_disponible` (se muestra como no disponible en vez de ocultarse).

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.exportar:csv` | CSV | encendida |
| `gen.exportar:gedcom` | GEDCOM | encendida |
| `gen.exportar:pdf` | PDF | encendida |

### `gen.idiomas` · Idioma de la interfaz

Idiomas en que se sirve el sitio. El enrutador los separa por dominio (spa/eng).

Carpetas: `url-routing/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.idiomas:es` | Español | encendida |
| `gen.idiomas:en` | Inglés | encendida |

### `gen.estado-civil` · Estados de una persona

Estados que se pueden marcar en una persona (iconos state_*).

Carpetas: `iconos-genealogia/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.estado-civil:vivo` | Vivo | encendida |
| `gen.estado-civil:fallecido` | Fallecido | encendida |
| `gen.estado-civil:casado` | Casado | encendida |
| `gen.estado-civil:divorciado` | Divorciado | encendida |
| `gen.estado-civil:viudo` | Viudo | encendida |
| `gen.estado-civil:separado` | Separado | encendida |
| `gen.estado-civil:soltero` | Soltero | encendida |
| `gen.estado-civil:comprometido` | Comprometido | encendida |

### `gen.vinculos` · Tipos de vínculo

Tipos de relación que se pueden registrar entre dos personas (iconos relationship_*).

Carpetas: `iconos-genealogia/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.vinculos:sangre` | Sangre | encendida |
| `gen.vinculos:medio` | Medio parentesco | encendida |
| `gen.vinculos:politico` | Parentesco político | encendida |
| `gen.vinculos:adopcion` | Adopción | encendida |
| `gen.vinculos:acogida` | Acogida | encendida |
| `gen.vinculos:familia-nuclear` | Familia nuclear | encendida |
| `gen.vinculos:familia-extensa` | Familia extensa | encendida |
| `gen.vinculos:monoparental` | Familia monoparental | encendida |
| `gen.vinculos:reconstituida` | Familia reconstituida | encendida |
| `gen.vinculos:herencia` | Herencia | encendida |
| `gen.vinculos:linaje` | Linaje | encendida |
| `gen.vinculos:generacional` | Generacional | encendida |

### `gen.ficha` · Secciones de la ficha

Qué bloques aparecen en la ficha de una persona o de un registro.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.ficha:calendario` | Calendario | encendida |
| `gen.ficha:eventos` | Eventos | encendida |
| `gen.ficha:lugares` | Lugares | encendida |
| `gen.ficha:fuentes` | Fuentes | encendida |
| `gen.ficha:notas` | Notas | encendida |
| `gen.ficha:fotos` | Fotos | encendida |
| `gen.ficha:adn` | ADN | encendida |
| `gen.ficha:colecciones` | Colecciones | encendida |
| `gen.ficha:miembros` | Miembros | encendida |
| `gen.ficha:organizaciones` | Organizaciones | encendida |
| `gen.ficha:aportes` | Aportes | encendida |
| `gen.ficha:enlaces` | Enlaces | encendida |

### `gen.verificacion` · Indicador de verificación

Marca cada dato como verificado o sin verificar (iconos ui_verified y ui_unverified).

### `gen.privacidad` · Protección de personas que podrían estar vivas

Protege a quien podría estar vivo: no se guardan ni se muestran sus datos. Todo lo que busca, deduce, infiere o estima personas depende de esta función.

Carpetas: `phone-directory/`, `Parsing-fb-in-mas/`.

### `gen.estilo-iconos` · Estilo de iconos

Qué variante del set de iconos se muestra.

Carpetas: `iconos-genealogia/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.estilo-iconos:color` | Color | encendida |
| `gen.estilo-iconos:lineas` | Líneas | encendida |
| `gen.estilo-iconos:gris` | Gris | encendida |

### `gen.acc-agregar` · Agregar familiares

Botones para añadir un familiar desde una persona (iconos action_add_*).

Carpetas: `iconos-genealogia/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.acc-agregar:padre-madre` | Padre o madre | encendida |
| `gen.acc-agregar:hijo` | Hijo o hija | encendida |
| `gen.acc-agregar:hermano` | Hermano o hermana | encendida |
| `gen.acc-agregar:conyuge` | Cónyuge | encendida |

### `gen.acc-editar` · Editar y deshacer

Acciones sobre el contenido de una ficha.

Carpetas: `iconos-genealogia/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.acc-editar:editar` | Editar | encendida |
| `gen.acc-editar:duplicar` | Duplicar | encendida |
| `gen.acc-editar:eliminar` | Eliminar | encendida |
| `gen.acc-editar:guardar` | Guardar | encendida |
| `gen.acc-editar:deshacer` | Deshacer | encendida |
| `gen.acc-editar:rehacer` | Rehacer | encendida |

### `gen.acc-conectar` · Conectar personas

Enlazar o desenlazar dos personas, y sincronizar datos entre árboles.

Carpetas: `iconos-genealogia/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.acc-conectar:conectar` | Conectar | encendida |
| `gen.acc-conectar:desconectar` | Desconectar | encendida |
| `gen.acc-conectar:enlazar` | Enlazar | encendida |
| `gen.acc-conectar:desenlazar` | Desenlazar | encendida |
| `gen.acc-conectar:sincronizar` | Sincronizar | encendida |

### `gen.acc-fusion` · Fusionar, dividir y copiar fichas

Juntar dos fichas que son la misma persona, separar una, o copiarla.

Carpetas: `iconos-genealogia/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.acc-fusion:fusionar` | Fusionar | encendida |
| `gen.acc-fusion:dividir` | Dividir | encendida |
| `gen.acc-fusion:copiar` | Copiar | encendida |

### `gen.acc-plegar` · Plegar y acercar el árbol

Abrir o cerrar ramas, y acercar o alejar la vista.

Carpetas: `iconos-genealogia/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.acc-plegar:plegar` | Plegar | encendida |
| `gen.acc-plegar:expandir` | Expandir | encendida |
| `gen.acc-plegar:acercar` | Acercar | encendida |
| `gen.acc-plegar:alejar` | Alejar | encendida |

### `gen.acc-compartir` · Compartir

Compartir una ficha o un árbol, y retirar un aporte propio.

Carpetas: `iconos-genealogia/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.acc-compartir:compartir` | Compartir ficha | encendida |
| `gen.acc-compartir:compartir-arbol` | Compartir árbol | encendida |
| `gen.acc-compartir:retirar-aporte` | Retirar aporte | encendida |

### `gen.acc-buscar` · Buscar y filtrar

Buscar personas y filtrar la lista.

Carpetas: `iconos-genealogia/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.acc-buscar:buscar` | Buscar | encendida |
| `gen.acc-buscar:filtrar` | Filtrar | encendida |

### `gen.acc-archivos` · Importar, subir e imprimir

Traer datos desde un archivo, subir archivos, descargarlos o imprimir.

Carpetas: `iconos-genealogia/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.acc-archivos:importar` | Importar | encendida |
| `gen.acc-archivos:subir` | Subir | encendida |
| `gen.acc-archivos:descargar` | Descargar | encendida |
| `gen.acc-archivos:imprimir` | Imprimir | encendida |

### `gen.fuentes-externas` · Importar desde fuentes externas

Qué fuentes externas se leen para proponer datos. Cada una pasa por revisión humana antes de guardarse.

Carpetas: `Parsing-fb-in-mas/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.fuentes-externas:facebook` | Facebook | encendida |
| `gen.fuentes-externas:linkedin` | LinkedIn | encendida |
| `gen.fuentes-externas:twitter-x` | Twitter / X | encendida |
| `gen.fuentes-externas:instagram` | Instagram | encendida |
| `gen.fuentes-externas:tiktok` | TikTok | encendida |
| `gen.fuentes-externas:youtube` | YouTube | encendida |
| `gen.fuentes-externas:github` | GitHub | encendida |
| `gen.fuentes-externas:pinterest` | Pinterest | encendida |
| `gen.fuentes-externas:bluesky` | Bluesky | encendida |
| `gen.fuentes-externas:threads` | Threads | encendida |
| `gen.fuentes-externas:mastodon` | Mastodon | encendida |
| `gen.fuentes-externas:wikitree` | WikiTree | encendida |
| `gen.fuentes-externas:find-a-grave` | Find a Grave | encendida |
| `gen.fuentes-externas:billiongraves` | BillionGraves | encendida |
| `gen.fuentes-externas:schema-org` | schema.org | encendida |
| `gen.fuentes-externas:wikidata` | Wikidata | encendida |

### `gen.directorios` · Importar directorios telefónicos históricos

Convierte directorios telefónicos históricos (6 idiomas) en registros, con búsqueda fonética.

Carpetas: `phone-directory/`.

### `gen.siluetas` · Siluetas de ancestros

Partes de la silueta que se muestran: atuendo, colores y árbol de ascendencia de hasta cuatro generaciones.

Carpetas: `siluetas-ancestrales/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.siluetas:atuendo` | Atuendo | encendida |
| `gen.siluetas:colores` | Colores | encendida |
| `gen.siluetas:arbol` | Árbol de ascendencia | encendida |

### `gen.mortalidad` · Datos de mortalidad histórica

Qué datos de contexto se muestran al leer edades y causas de muerte de un antepasado.

Carpetas: `mortalidad-historica/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.mortalidad:esperanza-de-vida` | Esperanza de vida | encendida |
| `gen.mortalidad:epidemias` | Epidemias | encendida |
| `gen.mortalidad:causas` | Glosario de causas | encendida |
| `gen.mortalidad:hereditarias` | Enfermedades hereditarias | encendida |
| `gen.mortalidad:registro-civil` | Registro civil | encendida |

### `gen.paises` · Mapa y tabla de países

Cómo se muestran los 208 países.

Carpetas: `Listado-paises/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.paises:mapa` | Mapa | encendida |
| `gen.paises:tabla` | Tabla | encendida |
| `gen.paises:nacimientos-historicos` | Estimación de nacimientos históricos | encendida |

### `gen.marketing` · Textos de marketing

Qué piezas de texto se usan en el sitio y en los canales.

Carpetas: `marketing/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.marketing:lemas` | Lemas | encendida |
| `gen.marketing:esloganes` | Eslóganes | encendida |
| `gen.marketing:redes` | Redes sociales | encendida |
| `gen.marketing:correo` | Correo | encendida |

### `gen.legal` · Consulta de leyes

Bases de leyes que se pueden consultar. Informativas, no son asesoría legal.

Carpetas: `privacy-laws/`, `copyright-laws/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.legal:privacidad` | Privacidad | encendida |
| `gen.legal:derechos-de-autor` | Derechos de autor | encendida |

### `gen.filtro` · Filtro de contenido

Revisa insultos, ridiculización, acusaciones y datos personales en lo que escribe la gente. Cada opción es una superficie.

Carpetas: `defamatory-content-review/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.filtro:nombres` | Nombres y apellidos | encendida |
| `gen.filtro:chat` | Mensajes de chat | encendida |
| `gen.filtro:textos` | Textos libres | encendida |

### `gen.retiros` · Pedidos de retiro de información

Recibe los pedidos de retiro. Cada texto pasa por el filtro de textos libres.

Carpetas: `url-routing/`.

Al apagar: `no_disponible` (se muestra como no disponible en vez de ocultarse).

*Nota:* Ocultar un pedido de retiro de datos sería peor que avisar que no está disponible por ahora (derechos de la persona; ver RECLAMOS.md). Política supuesta, confirmar.

### `gen.backoffice` · Backoffice de actividad

Panel del equipo para revisar la actividad de un usuario. Requiere iniciar sesión.

Carpetas: `sistema-nuevo/`.

*Nota:* Lo consume sistema-nuevo/ (BACKOFFICE_INTERRUPTORES). La opción «comparacion» todavía no la aplica: viaja dentro de la respuesta de la línea de tiempo.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.backoffice:flujo` | Flujo cronológico | encendida |
| `gen.backoffice:comparacion` | Comparación con el universo | encendida |
| `gen.backoffice:kpis` | KPIs y evolución | encendida |
| `gen.backoffice:alertas` | Alertas proactivas | encendida |
| `gen.backoffice:filtros` | Filtros guardados | encendida |

### `gen.bak-notas` · Backoffice: notas por acción

Notas sobre cada acción. Pasan por el filtro de textos libres.

Carpetas: `sistema-nuevo/`.

### `gen.cobros` · Cobros y cartera

Panel de cobros: ingresos, cartera, cobranza, flujo, tasas y pagos.

Carpetas: `cobros-ingresos-funnels/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.cobros:ingresos-cartera` | Ingresos y cartera | encendida |
| `gen.cobros:cobranza` | Cobranza | encendida |
| `gen.cobros:flujo-caja` | Flujo de caja esperado | encendida |
| `gen.cobros:tasas` | Tasas de cambio | encendida |
| `gen.cobros:pagos-mes` | Pagos por mes y método | encendida |
| `gen.cobros:exportar` | Exportar a CSV | encendida |

### `gen.funnel` · Funnel y cohortes

Análisis de conversión de visitante a cliente.

Carpetas: `cobros-ingresos-funnels/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.funnel:embudo` | Embudo de conversión | encendida |
| `gen.funnel:cohortes` | Cohortes | encendida |
| `gen.funnel:segmentacion` | Segmentación de clientes | encendida |

### `gen.busqueda` · Búsqueda de personas en archivos y registros externos

Busca a una persona en archivos y registros fuera del sitio.

### `gen.prensa` · Búsqueda en diarios y revistas

Busca menciones de una persona en diarios y revistas.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.prensa:diarios` | Diarios | encendida |
| `gen.prensa:revistas` | Revistas | encendida |

### `gen.congresos` · Búsqueda en congresos nacionales

Busca a una persona en las actas y registros de congresos nacionales.

### `gen.deduccion` · Deducción de parentescos y datos

Deduce parentescos y datos a partir de otros registros ya confirmados.

### `gen.inferencia` · Inferencia de datos faltantes

Infiere datos que faltan a partir de lo que se sabe de la persona y su entorno.

### `gen.estimacion` · Estimación de fechas y lugares

Estima fechas y lugares sin registro, con datos de contexto como la mortalidad histórica.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.estimacion:fechas` | Fechas | encendida |
| `gen.estimacion:lugares` | Lugares | encendida |

### `gen.pasarela` · Pasarela de pagos

Pasarelas con las que se cobra. Hoy solo Stripe.

Al apagar: `no_disponible` (se muestra como no disponible en vez de ocultarse).

*Nota:* Cuando está apagada el pago se muestra como «no disponible», no se oculta. La relación exacta con la opción «tarjeta» de gen.pagos está por confirmar.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `gen.pasarela:stripe` | Stripe | encendida |

## B) Librería y etiquetado POS

| Id | Función | Opciones | Tipo | Origen | Por defecto | Depende de |
|---|---|---|---|---|---|---|
| `pos.mb-v-movimientos` | Marcalibros verticales: movimientos literarios | 15 (mín. 1) | visual | `repo` | encendida | — |
| `pos.mb-v-linea-marca` | Marcalibros verticales: línea de marca | 3 | visual | `repo` | encendida | — |
| `pos.mb-v-editor` | Editor de marcalibro propio (vertical) | — | producto | `repo` | encendida | `gen.filtro:textos` |
| `pos.mb-v-lector` | Modelo de datos del lector | — | producto | `repo` | encendida | — |
| `pos.mb-h-movimientos` | Marcalibros horizontales: movimientos literarios | 11 (mín. 1) | visual | `repo` | encendida | — |
| `pos.mb-h-tipos-texto` | Marcalibros horizontales: tipos de texto | 16 | visual | `repo` | encendida | — |
| `pos.mb-h-propios` | Marcalibros horizontales: marcalibros propios (PHP) | — | producto | `repo` | encendida | `gen.filtro:textos` |
| `pos.mb-h-app` | Aplicación web de marcalibros (sin cuentas) | — | producto | `repo` | encendida | `pos.mb-h-propios` |
| `pos.mb-h-paisajes` | Paisajes y vista previa del catálogo horizontal | — | visual | `repo` | encendida | — |
| `pos.chat-libros` | Chat con un libro | 2 | producto | `pr_abierto` | apagada | — |
| `pos.colores-lista` | Léxico de colores: lista completa | — | datos | `repo` | encendida | — |
| `pos.colores-css` | Léxico de colores: 148 colores CSS anotados | — | datos | `repo` | encendida | — |
| `pos.colores-categorias` | Categorías gramaticales de los colores | — | datos | `repo` | encendida | — |
| `pos.colores-exportes` | Exportes para PLN | 5 | datos | `repo` | encendida | `pos.colores-lista` |
| `pos.colores-informes` | Informes de calidad del léxico | 4 | datos | `repo` | encendida | `pos.colores-lista` |
| `pos.colores-revision` | Revisión por hablantes nativos | — | interno | `repo` | apagada | `pos.colores-lista` |
| `pos.citas` | Citas coloreadas por categoría | 3 | contenido | `repo` | apagada | `pos.colores-categorias` |
| `pos.iconos-pos` | Iconos del punto de venta | — | visual | `repo` | encendida | — |
| `pos.iconos-biblioteca` | Iconos de biblioteca virtual | — | visual | `repo` | encendida | — |
| `pos.portadas` | Portadas de libro en SVG | 4 | visual | `repo` | encendida | — |
| `pos.pasarela` | Pasarela de pagos del POS *(supuesto)* | 1 | producto | `fuera_del_repo` | encendida | — |

### `pos.mb-v-movimientos` · Marcalibros verticales: movimientos literarios

Movimientos del catálogo vertical 1:7 (200×1400 px). Cada uno sale con 10 o 15 autores.

Carpetas: `marcalibros_b/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `pos.mb-v-movimientos:romanticismo` | Romanticismo | encendida |
| `pos.mb-v-movimientos:gotico` | Gótico | encendida |
| `pos.mb-v-movimientos:realismo-magico` | Realismo mágico | encendida |
| `pos.mb-v-movimientos:realismo` | Realismo | encendida |
| `pos.mb-v-movimientos:naturalismo` | Naturalismo | encendida |
| `pos.mb-v-movimientos:modernismo` | Modernismo | encendida |
| `pos.mb-v-movimientos:vanguardias` | Vanguardias | encendida |
| `pos.mb-v-movimientos:barroco` | Barroco | encendida |
| `pos.mb-v-movimientos:clasicismo` | Clasicismo | encendida |
| `pos.mb-v-movimientos:simbolismo` | Simbolismo | encendida |
| `pos.mb-v-movimientos:renacimiento` | Renacimiento | encendida |
| `pos.mb-v-movimientos:ilustracion` | Ilustración | encendida |
| `pos.mb-v-movimientos:expresionismo` | Expresionismo | encendida |
| `pos.mb-v-movimientos:surrealismo` | Surrealismo | encendida |
| `pos.mb-v-movimientos:oriente` | Literaturas de Oriente | encendida |

### `pos.mb-v-linea-marca` · Marcalibros verticales: línea de marca

Logos, iconos y cabeceras de la línea de marca.

Carpetas: `marcalibros_b/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `pos.mb-v-linea-marca:logos` | Logos | encendida |
| `pos.mb-v-linea-marca:iconos` | Iconos | encendida |
| `pos.mb-v-linea-marca:cabeceras` | Cabeceras | encendida |

### `pos.mb-v-editor` · Editor de marcalibro propio (vertical)

Cada persona crea su marcalibro, con subidas de imagen seguras. El texto pasa por el filtro de textos libres.

Carpetas: `marcalibros_b/`.

### `pos.mb-v-lector` · Modelo de datos del lector

Modelo de datos del lector de textos que usa los marcalibros verticales.

Carpetas: `marcalibros_b/`.

### `pos.mb-h-movimientos` · Marcalibros horizontales: movimientos literarios

Once movimientos del catálogo horizontal 7:1 (1400×200 px).

Carpetas: `marcalibros-plataforma-pos-brand-identity/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `pos.mb-h-movimientos:romanticismo` | Romanticismo | encendida |
| `pos.mb-h-movimientos:realismo` | Realismo | encendida |
| `pos.mb-h-movimientos:naturalismo` | Naturalismo | encendida |
| `pos.mb-h-movimientos:modernismo-hispano` | Modernismo hispanoamericano | encendida |
| `pos.mb-h-movimientos:modernismo-anglo` | Modernismo anglosajón | encendida |
| `pos.mb-h-movimientos:vanguardias` | Vanguardias | encendida |
| `pos.mb-h-movimientos:gotico` | Gótico | encendida |
| `pos.mb-h-movimientos:barroco` | Barroco | encendida |
| `pos.mb-h-movimientos:clasicismo` | Clasicismo | encendida |
| `pos.mb-h-movimientos:simbolismo` | Simbolismo | encendida |
| `pos.mb-h-movimientos:realismo-magico` | Realismo mágico | encendida |

### `pos.mb-h-tipos-texto` · Marcalibros horizontales: tipos de texto

Dieciséis tipos de texto del catálogo horizontal.

Carpetas: `marcalibros-plataforma-pos-brand-identity/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `pos.mb-h-tipos-texto:manuales` | Manuales | encendida |
| `pos.mb-h-tipos-texto:libros-sagrados` | Libros sagrados | encendida |
| `pos.mb-h-tipos-texto:leyes` | Leyes | encendida |
| `pos.mb-h-tipos-texto:cartas` | Cartas | encendida |
| `pos.mb-h-tipos-texto:diarios` | Diarios | encendida |
| `pos.mb-h-tipos-texto:tratados-cientificos` | Tratados científicos | encendida |
| `pos.mb-h-tipos-texto:recetas` | Recetas | encendida |
| `pos.mb-h-tipos-texto:filosofia` | Filosofía | encendida |
| `pos.mb-h-tipos-texto:teatro` | Teatro | encendida |
| `pos.mb-h-tipos-texto:viajes` | Viajes | encendida |
| `pos.mb-h-tipos-texto:cuentos-leyendas` | Cuentos y leyendas | encendida |
| `pos.mb-h-tipos-texto:poesia` | Poesía | encendida |
| `pos.mb-h-tipos-texto:historia` | Historia | encendida |
| `pos.mb-h-tipos-texto:periodismo` | Periodismo | encendida |
| `pos.mb-h-tipos-texto:diccionarios` | Diccionarios y enciclopedias | encendida |
| `pos.mb-h-tipos-texto:discursos` | Discursos | encendida |

### `pos.mb-h-propios` · Marcalibros horizontales: marcalibros propios (PHP)

Módulo PHP para crear marcalibros a partir de imágenes subidas. Revisa el texto con el filtro de textos libres.

Carpetas: `marcalibros-plataforma-pos-brand-identity/`.

### `pos.mb-h-app` · Aplicación web de marcalibros (sin cuentas)

Explorar los 1350 marcalibros y crear los propios. Independiente, sin login, sin conexión a Plataforma POS.

Carpetas: `marcalibros-plataforma-pos-brand-identity/`.

### `pos.mb-h-paisajes` · Paisajes y vista previa del catálogo horizontal

Paisajes JPG y la vista previa JPG del catálogo.

Carpetas: `marcalibros-plataforma-pos-brand-identity/`.

### `pos.chat-libros` · Chat con un libro

Chat con un libro, en PHP y en Java. Sus opciones son sinónimos y un modo opcional con IA. Vive en el PR #603, sin fusionar.

Carpetas: `chat-con-libros/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `pos.chat-libros:sinonimos` | Sinónimos | apagada |
| `pos.chat-libros:ia` | Modo con IA | apagada |

### `pos.colores-lista` · Léxico de colores: lista completa

31.935 nombres de color con su hex y su color CSS más cercano.

Carpetas: `Listado-colores/`.

### `pos.colores-css` · Léxico de colores: 148 colores CSS anotados

Significados psicológico y místico en 30 idiomas, con su evidencia.

Carpetas: `Listado-colores/`.

### `pos.colores-categorias` · Categorías gramaticales de los colores

Adjetivo, sustantivo, nombre propio o verbo, para las palabras de color de una sola palabra en inglés y español. Es la base del etiquetado.

Carpetas: `Listado-colores/`.

### `pos.colores-exportes` · Exportes para PLN

Formatos de exporte del léxico para procesamiento de lenguaje natural.

Carpetas: `Listado-colores/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `pos.colores-exportes:jsonl` | JSONL | encendida |
| `pos.colores-exportes:tsv` | TSV | encendida |
| `pos.colores-exportes:sqlite` | SQLite | encendida |
| `pos.colores-exportes:spacy` | Patrones de spaCy | encendida |
| `pos.colores-exportes:universal-dependencies` | Universal Dependencies | encendida |

### `pos.colores-informes` · Informes de calidad del léxico

Informes generados sobre el léxico.

Carpetas: `Listado-colores/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `pos.colores-informes:ambiguedad` | Ambigüedad | encendida |
| `pos.colores-informes:nombres-sospechosos` | Nombres sospechosos | encendida |
| `pos.colores-informes:cobertura` | Cobertura | encendida |
| `pos.colores-informes:auditoria` | Auditoría de nombres completos | encendida |

### `pos.colores-revision` · Revisión por hablantes nativos

Registro y visor de revisión. Todavía vacío. El PR #595 lo marca @deprecated.

Carpetas: `Listado-colores/`.

### `pos.citas` · Citas coloreadas por categoría

Dónde se muestran las citas coloreadas. Todavía no hay citas publicadas.

Carpetas: `citas-pos/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `pos.citas:sitio` | Sitio | apagada |
| `pos.citas:correo` | Correo | apagada |
| `pos.citas:juego` | Juego «Adivina la categoría» | apagada |

### `pos.iconos-pos` · Iconos del punto de venta

23 iconos: catálogo, pedidos, cuenta, carrito y pago.

Carpetas: `iconos-pos/`.

### `pos.iconos-biblioteca` · Iconos de biblioteca virtual

151 iconos de biblioteca virtual.

Carpetas: `iconos-biblioteca/`.

### `pos.portadas` · Portadas de libro en SVG

Piezas de la cubierta de un libro, con medidas de corte para imprenta.

Carpetas: `portadas-svg/`.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `pos.portadas:portada` | Portada | encendida |
| `pos.portadas:contratapa` | Contratapa | encendida |
| `pos.portadas:lomo` | Lomo | encendida |
| `pos.portadas:cubierta-completa` | Cubierta completa | encendida |

### `pos.pasarela` · Pasarela de pagos del POS

Pasarelas con las que se cobra en el POS. Hoy solo Stripe.

Al apagar: `no_disponible` (se muestra como no disponible en vez de ocultarse).

*Nota:* Se supone la misma pasarela que en genealogía; confirmar.

| Opción (ref) | Nombre | Por defecto |
|---|---|---|
| `pos.pasarela:stripe` | Stripe | encendida |

## Fuera del interruptor

Carpetas del repo que no son una función de ninguna plataforma, o que el interruptor no debe tocar.

| Carpeta | Motivo |
|---|---|
| `web-animations/` | Galería de demostración de animaciones; no es de ninguna plataforma. |
| `astros-3d/` | Visor WebGL de astros; no es de ninguna plataforma. |
| `signos-zodiacales/` | Referencia cultural de signos; no es de ninguna plataforma. |
| `psychology-and-marketing/` | Solo documentos, sin código. |
| `iconos-tools/` | Generador de los sets de iconos; herramienta de construcción. |
| `leyes-comunes/` | Código compartido de las dos bases de leyes; no es una función. |
| `document-formats/` | Formatos de papel por país. Sin plataforma asignada: a confirmar si va en B (impresión de libros) o en A. |
| `vps-setup/` | Infraestructura: instala el servidor. |
| `landing-page/` | Página estática que despliega vps-setup. |

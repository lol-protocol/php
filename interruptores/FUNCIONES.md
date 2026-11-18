# Listado de funciones por plataforma

> Generado desde [`catalogo.json`](catalogo.json) con `php interruptores/bin/generar.php`. **No lo edites a mano**: cambia el catálogo y vuelve a generar.

Cada fila es un interruptor del panel. «Por defecto» dice si arranca encendida; «Depende de» son las funciones que tienen que estar encendidas para que esta funcione.

Total: **51 funciones** en 3 plataformas.

## Tipos

| Tipo | Significado |
|---|---|
| `producto` | Lo ve o lo usa una persona fuera del equipo |
| `interno` | Herramienta del equipo u operación |
| `datos` | Datos o base de referencia |
| `visual` | Recursos visuales (iconos, siluetas, portadas) |
| `contenido` | Textos y contenido |
| `por-confirmar` | No está claro si es del producto o interno |

## A) Genealogía (sitio de genealogía y herramientas de su operación)

| Id | Función | Carpeta | Tipo | Por defecto | Depende de | Qué hace |
|---|---|---|---|---|---|---|
| `gen.urls` | Enrutamiento de URLs | `url-routing` | producto | encendida | — | El dominio (Host) decide el sitio; la forma del primer segmento de la URL decide el tipo de recurso (cantidad de dígitos, letras). Español e inglés. |
| `gen.retiros` | Pedidos de retiro de información | `url-routing` | producto | encendida | `gen.filtro-textos` | Recibe y gestiona los pedidos de retiro; cada texto pasa por el filtro de textos libres. |
| `gen.filtro-nombres` | Filtro de nombres y apellidos | `defamatory-content-review` | producto | encendida | — | Detecta insultos, ridiculización y fusión fonética entre nombre y apellido («Elba Gina» → «el vagina»). Protege apellidos legítimos. 11 tipos de riesgo, 33 idiomas. |
| `gen.filtro-chat` | Filtro de mensajes de chat | `defamatory-content-review` | producto | encendida | — | Revisa línea a línea los mensajes de chat (ChatLineReviewer). En el navegador, un tope de letras repetidas antes de enviar. |
| `gen.filtro-textos` | Filtro de textos libres | `defamatory-content-review` | producto | encendida | — | Marca acusaciones contra personas, datos sensibles de terceros (salud, religión, deudas…) y datos personales (RUT, teléfono, correo, dirección) para revisión humana. |
| `gen.directorio` | Lector de directorios telefónicos históricos | `phone-directory` | interno | encendida | — | Convierte directorios telefónicos históricos (6 idiomas) en registros, con búsqueda fonética de apellidos, base de datos y línea de comandos. Aplica reglas de privacidad a personas que podrían estar vivas. |
| `gen.lector-perfiles` | Lectores de perfiles guardados y cruce de fallecidos | `Parsing-fb-in-mas` | interno | encendida | — | Lee páginas de perfil ya guardadas (redes sociales, WikiTree, Find a Grave, schema.org), volcados de Wikidata y respuestas de la API de WikiTree; propone cruces solo entre fallecidos. De una persona viva guarda solo nombre, titular, lugar y descripción. Todo queda para revisión humana. |
| `gen.siluetas` | Siluetas de ancestros y árbol de ascendencia | `siluetas-ancestrales` | visual | encendida | — | Siluetas por edad, sexo, complexión y época, con catálogo de atuendos y colores por región, y un árbol de hasta cuatro generaciones. Es una aproximación histórica para ilustrar, no un estudio de indumentaria. |
| `gen.iconos` | Iconos del árbol genealógico | `iconos-genealogia` | visual | encendida | — | 207 iconos SVG 2.5D sin texto, en 3 variantes. |
| `gen.mortalidad` | Mortalidad histórica | `mortalidad-historica` | datos | encendida | — | Esperanza de vida por país y década (1800–2009), epidemias, glosario de causas de defunción en 20 idiomas y consultas en Python, PHP y JavaScript. Salvo la esperanza de vida, es borrador sin verificar. |
| `gen.marketing` | Textos de marketing | `marketing` | contenido | encendida | — | Lemas, eslóganes, textos para redes sociales y correo, con dos scripts PHP sueltos para renderizar. |
| `gen.bak-acceso` | Backoffice: inicio de sesión | `sistema-nuevo` | interno | encendida | — | Requiere iniciar sesión para entrar al panel de actividad de usuarios. |
| `gen.bak-flujo` | Backoffice: flujo cronológico de un usuario | `sistema-nuevo` | interno | encendida | `gen.bak-acceso` | Cada acción de un usuario en orden, con su duración, su monto pagado, la ruta o archivo del backend que la atendió, y la IP de la sesión (país, hora local y proveedor). |
| `gen.bak-comparacion` | Backoffice: comparación con el universo | `sistema-nuevo` | interno | encendida | `gen.bak-acceso` | Compara cada acción con el promedio de un universo filtrable (grupos de países, edad, género, tipo de acción) y muestra percentiles. |
| `gen.bak-kpis` | Backoffice: KPIs y gráfico de evolución | `sistema-nuevo` | interno | encendida | `gen.bak-acceso` | Dashboard de indicadores y gráfico de evolución temporal de la actividad. |
| `gen.bak-alertas` | Backoffice: alertas proactivas | `sistema-nuevo` | interno | encendida | `gen.bak-acceso` | Avisa, por ejemplo, de IPs que no coinciden con el país declarado, y permite configurar las alertas. |
| `gen.bak-notas` | Backoffice: notas por acción | `sistema-nuevo` | interno | encendida | `gen.bak-acceso`, `gen.filtro-textos` | Notas sobre cada acción, con su filtro y su registro. Pasan por el filtro de textos libres. |
| `gen.bak-filtros` | Backoffice: filtros guardados y paginación | `sistema-nuevo` | interno | encendida | `gen.bak-acceso` | Paginación, filtro por tipo de acción y filtros guardados para reutilizar. |
| `gen.bak-idioma` | Backoffice: idioma de la interfaz | `sistema-nuevo` | interno | encendida | `gen.bak-acceso` | Interfaz en español e inglés. Los nombres de usuario se leen bien en latino, japonés, árabe, hebreo, chino, coreano y cirílico. |

## B) Librería y etiquetado POS (Contrastocolor, Plataforma POS y Marcalibros)

| Id | Función | Carpeta | Tipo | Por defecto | Depende de | Qué hace |
|---|---|---|---|---|---|---|
| `pos.urls` | Enrutamiento del sitio POS (Contrastocolor) | `url-routing` | producto | encendida | — | El mismo enrutador que el de genealogía, con su propio Host y su propia base de datos. *Nota:* Comparte código con gen.urls: apagarlo por separado exige que el enrutador lea este interruptor. |
| `pos.mb-v-generador` | Marcalibros verticales: generador | `marcalibros_b` | visual | encendida | — | Genera 2000 marcalibros verticales 1:7 (200×1400 px), de forma determinista e incremental, y los revisa en Chromium. |
| `pos.mb-v-linea-marca` | Marcalibros verticales: línea de marca | `marcalibros_b` | visual | encendida | — | Logos, iconos y cabeceras de la línea de marca. |
| `pos.mb-v-lector` | Marcalibros verticales: modelo de datos del lector | `marcalibros_b` | producto | encendida | — | Modelo de datos del lector de textos que usa los marcalibros. |
| `pos.mb-v-editor` | Marcalibros verticales: editor de usuario | `marcalibros_b` | producto | encendida | `gen.filtro-textos` | Editor donde cada persona crea su marcalibro, con subidas de imagen seguras. *Nota:* El texto que escribe la persona pasa por el filtro (CLAUDE.md de la carpeta lo exige). |
| `pos.mb-h-propios` | Marcalibros horizontales: marcalibros propios (PHP) | `marcalibros-plataforma-pos-brand-identity` | producto | encendida | `gen.filtro-textos` | Módulo PHP para crear marcalibros horizontales 7:1 (1400×200) a partir de imágenes subidas. |
| `pos.mb-h-app` | Marcalibros horizontales: aplicación web sin cuentas | `marcalibros-plataforma-pos-brand-identity` | producto | encendida | `pos.mb-h-propios` | Aplicación independiente para explorar los 1350 marcalibros (11 movimientos y 16 tipos de texto) y crear los propios. Sin login; no está conectada a Plataforma POS. |
| `pos.mb-h-catalogo` | Marcalibros horizontales: catálogo y paisajes | `marcalibros-plataforma-pos-brand-identity` | visual | encendida | — | Tokens de marca, iconos SVG, vista previa JPG del catálogo y paisajes JPG. |
| `pos.chat-libros` | Chat con un libro | `chat-con-libros` | producto | apagada | — | Chat con un libro en PHP y en Java, con sinónimos y un modo opcional con IA. Pendiente de fusionar (PR abierto). *Nota:* Vive en un PR abierto, no está en master. Por defecto apagado. |
| `pos.colores-lista` | Léxico de colores: lista completa | `Listado-colores` | datos | encendida | — | 31.935 nombres de color con su hex, y una marca de buenos nombres con su color CSS más cercano. |
| `pos.colores-css` | Léxico de colores: 148 colores CSS anotados | `Listado-colores` | datos | encendida | — | Tabla de los 148 colores CSS con dos significados (psicológico y místico) en 30 idiomas, y su evidencia y matices culturales. |
| `pos.colores-categorias` | Léxico de colores: categorías gramaticales | `Listado-colores` | datos | encendida | — | Categoría gramatical (adjetivo, sustantivo, nombre propio, verbo) de las palabras de color de una sola palabra en inglés y español, con frase de ejemplo. Es la base del etiquetado. |
| `pos.colores-exportes` | Léxico de colores: exportes para PLN | `Listado-colores` | datos | encendida | `pos.colores-lista` | JSONL, TSV y SQLite; patrones de spaCy; léxico y ejemplos en Universal Dependencies; forma normalizada para texto ruidoso; manifiesto con la versión de los datos. |
| `pos.colores-informes` | Léxico de colores: informes de calidad | `Listado-colores` | datos | encendida | `pos.colores-lista` | Informes de ambigüedad (formas con más de un color), de nombres sospechosos, de cobertura y de auditoría de nombres completos. |
| `pos.colores-revision` | Léxico de colores: revisión por hablantes nativos | `Listado-colores` | interno | apagada | `pos.colores-lista` | Registro de revisión por hablantes nativos, con su informe y su visor HTML. Todavía vacío. *Nota:* El PR #595 (abierto) la marca @deprecated. Por defecto apagado. |
| `pos.citas` | Citas coloreadas por categoría gramatical | `citas-pos` | contenido | apagada | `pos.colores-categorias` | Citas de autores de dominio público con cada palabra en el color de su categoría, para el sitio y el correo, y el juego «Adivina la categoría». Todavía no hay citas publicadas. *Nota:* Sin citas publicadas: por defecto apagado. |
| `pos.iconos-pos` | Iconos del punto de venta | `iconos-pos` | visual | encendida | — | 23 iconos SVG 2.5D: catálogo, pedidos, cuenta del cliente, carrito y pago. |
| `pos.iconos-biblioteca` | Iconos de biblioteca virtual | `iconos-biblioteca` | visual | encendida | — | 151 iconos SVG 2.5D para biblioteca virtual, en 3 variantes. |
| `pos.portadas` | Portadas de libro en SVG | `portadas-svg` | visual | encendida | — | Portada, contratapa, lomo y cubierta completa a partir de un JSON o de una lista CSV; fuentes libres incrustadas; medidas de corte para imprenta. |

## Transversal (por confirmar a cuál plataforma sirve)

| Id | Función | Carpeta | Tipo | Por defecto | Depende de | Qué hace |
|---|---|---|---|---|---|---|
| `cob.ingresos-cartera` | Cobros: ingresos y cartera | `cobros-ingresos-funnels` | interno | encendida | — | Ingresos devengados frente a cobros reales, y cartera pendiente con antigüedad de saldo (al día, 1–30, 31–60, 61+ días). |
| `cob.cobranza` | Cobros: cobranza cliente por cliente | `cobros-ingresos-funnels` | interno | encendida | `cob.ingresos-cartera` | Quién debe, cuánto (total y vencido, en USD a la tasa de hoy), desde cuándo, con su correo y acceso directo a registrar el pago. |
| `cob.flujo` | Cobros: flujo de caja esperado | `cobros-ingresos-funnels` | interno | encendida | `cob.ingresos-cartera` | Lo que falta cobrar, por mes de vencimiento, a 3, 6 o 12 meses. |
| `cob.tasas` | Cobros: tasas de cambio | `cobros-ingresos-funnels` | interno | encendida | — | Tasa de hoy por moneda, cambio en 30 días e historial paginado. Las tasas las carga un comando aparte. |
| `cob.pagos` | Cobros: pagos por mes y método | `cobros-ingresos-funnels` | interno | encendida | — | Pagos por mes y por método (transferencia, tarjeta, efectivo). |
| `cob.funnel` | Funnel de conversión | `cobros-ingresos-funnels` | interno | encendida | — | Visitante → registrado → lead → cliente, con tasas por etapa, por canal de adquisición, país, género y rango de edad. |
| `cob.cohortes` | Cohortes de conversión | `cobros-ingresos-funnels` | interno | encendida | `cob.funnel` | Qué porcentaje de cada cohorte (mes de primera visita) llegó a cliente en 0, 1, 2 o 3 meses. |
| `cob.segmentacion` | Segmentación de clientes | `cobros-ingresos-funnels` | interno | encendida | — | Top de país, ciudad, idioma, género y rango de edad por facturación. |
| `cob.exportar` | Cobros: exportar a CSV | `cobros-ingresos-funnels` | interno | encendida | — | Exporta a CSV algunas de sus pantallas (las tasas, entre ellas). |
| `lg.paises` | Listado de países | `Listado-paises` | datos | encendida | — | 208 países con población, IDH, PIB, alfabetización, internet y emigración; índices de interés para genealogía y de viabilidad para el etiquetador POS; estimación de nacimientos históricos; vista web con mapa. |
| `lg.leyes-privacidad` | Base de leyes de privacidad | `privacy-laws` | por-confirmar | encendida | — | Leyes de privacidad por país con buscador web, API PHP y esquema MySQL. Informativa, no es asesoría legal. |
| `lg.leyes-derechos` | Base de leyes de derechos de autor | `copyright-laws` | por-confirmar | encendida | — | Leyes de derechos de autor por jurisdicción con buscador web, API PHP y esquema MySQL. Informativa, no es asesoría legal. |
| `lg.formatos-papel` | Formatos de documento y papel por país | `document-formats` | datos | encendida | — | Base de formatos de papel y documento por país, con equivalencias, CSV y una demo web. |

## Fuera del interruptor

Carpetas del repo que no son una función de ninguna de las dos plataformas, o que el interruptor no debe tocar.

| Carpeta | Motivo |
|---|---|
| `web-animations` | Galería de demostración de animaciones; no es de ninguna plataforma. |
| `astros-3d` | Visor WebGL de astros; no es de ninguna plataforma. |
| `signos-zodiacales` | Referencia cultural de signos; no es de ninguna plataforma. |
| `psychology-and-marketing` | Solo documentos, sin código. |
| `iconos-tools` | Generador de los sets de iconos; herramienta de construcción. |
| `leyes-comunes` | Código compartido de las dos bases de leyes; no es una función. |
| `vps-setup` | Infraestructura: instala el servidor. |
| `landing-page` | Página estática que despliega vps-setup. |

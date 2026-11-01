# Cumplimiento de la Ley 21.719: qué hace el sitio y qué falta

> **No es asesoría legal.** Es una comparación entre lo que el sitio hace hoy
> (comprobado en el código y los documentos de este repositorio) y lo que la
> Ley 21.719 exige según las fuentes que hay a mano. La persona a cargo decidió
> **no consultar a un abogado** (26 de octubre de 2026), así que este documento
> existe para que decida con la lista delante y sepa qué comprobar ella misma.
>
> Preparado el **31 de octubre de 2026**. La ley rige desde el **1 de diciembre de
> 2026**: faltan **31 días**.

## Cómo leerlo

- **Lo que dice el sitio** es verificable: cada fila apunta al archivo donde se ve.
- **Lo que dice la ley** no está comprobado contra el texto oficial. Desde la
  sesión que preparó esto no se llega a `bcn.cl` ni a `leychile.cl`, y las
  fuentes del repositorio son resúmenes de terceros
  ([`privacy-laws/countries/cl/laws.json`](privacy-laws/countries/cl/laws.json),
  `confidence: medium`). Por eso cada afirmación sobre la ley lleva su nivel de
  confianza (§2) y la sección 7 lista, como preguntas, lo que hay que cotejar
  con el texto oficial.
- Estados de la tabla de brechas (§4): ✅ resuelto · ◐ a medias · ❌ falta · ❔ no
  se puede saber sin el texto de la ley.

---

## 1. En una página

**Lo que ya juega a favor** (y está probado, no solo escrito):

- **A un visitante no se le muestra a nadie que pueda estar vivo.** Se oculta a
  quien nació hace menos de 110 años y no tiene defunción, y a quien el
  propietario marca como vivo (`url-routing/Support/Privacidad.php`).
- **Hay una salida para quien reclama**: el formulario `/r/{id}/`, una cola con
  historial que solo se agrega, y ocultamiento automático a las 24 horas
  ([`RECLAMOS.md`](RECLAMOS.md), [`url-routing/docs/ACCESO.md`](url-routing/docs/ACCESO.md));
  el ocultamiento automático vale desde que el cron del servidor lleve `--ocultar`.
- **Se guarda poco de quien visita**: sin cuentas de visitante, sin cookie para
  quien solo lee, el pedido de retiro no deja quién lo pidió en el log, la IP del
  limitador se guarda como hash y se borra a la hora.
- **No se toman pagos** y la tienda solo recibe una dirección de envío.
- **Se puede contestar «¿qué tienen de mí?» y «bórrenme»** con `bin/persona.php`
  ([`url-routing/docs/ACCESO.md`](url-routing/docs/ACCESO.md)): lo primero imprime todo lo
  que hay de una persona; lo segundo la borra, deja un registro sin su nombre y se puede
  repetir si algún día se restaura una copia de seguridad vieja.

**Lo que falta para poder abrir el sitio** (detalle en §5):

1. La política de privacidad no está escrita y los términos tienen huecos
   (responsable, domicilio, correo, país del servidor, plazos).
2. Solo los pedidos de retiro tienen un plazo de conservación (`--depurar`, 12 meses, y falta ponerlo en
   el cron); las direcciones de la tienda, los logs y las copias de seguridad no.
3. Para **borrar** a una persona hay herramienta (`bin/persona.php suprimir`), pero
   falta ensayarla con datos reales y decidir cómo se comprueba quién la pide.
   «Retirar» solo oculta; corregir sigue siendo a mano en la base.
4. No hay un procedimiento escrito si hay una fuga de datos.
5. Nadie ha decidido en qué país vive el servidor ni quién es el responsable ante
   la ley.

**Lo que no se sabe y se puede resolver en una tarde** (§7): si la ley protege los
datos de personas fallecidas, si hay una exención para la investigación
histórica o genealógica, y los plazos exactos de respuesta.

---

## 2. Lo que se sabe de la ley, y con cuánta confianza

| Tema | Qué se sabe | De dónde sale | Confianza |
|---|---|---|---|
| Vigencia | Rige desde el 1 de diciembre de 2026 y reemplaza a la Ley 19.628 | Fuentes del repositorio (az.cl, CMS) | Media-alta: varias fuentes concuerdan, ninguna es el texto |
| Autoridad | Agencia de Protección de Datos Personales | Fuentes del repositorio | Media-alta |
| Multas | Hasta 20.000 UTM en las infracciones muy graves | Fuentes del repositorio (un blog jurídico) | Media |
| Bases para tratar datos | Hay otras además del consentimiento (contrato, interés legítimo) | Fuentes del repositorio (DLA Piper) | Media-alta |
| Obligaciones nombradas | Portabilidad, aviso de vulneraciones de seguridad, modelo de cumplimiento o delegado de protección de datos | Fuentes del repositorio | Media: se nombran, no se detallan |
| Derechos del titular | Acceso, rectificación, supresión, oposición y portabilidad | Resumen de terceros que aparece en una búsqueda (Alaya Trust) | Media: coincide con lo que ya dice [`TERMINOS-DE-USO.md`](TERMINOS-DE-USO.md) |
| Plazo de respuesta | 30 días corridos, prorrogables en igual plazo | Una nota de Carey de agosto de 2024 **sobre el proyecto**, no sobre el texto final | **Baja**: comprobar en el texto |
| Datos sensibles | Los genéticos y los de perfil biológico lo son | Fuentes del repositorio (Prey) | Media |
| Investigación histórica o científica | Habría una excepción al consentimiento; el artículo **no está confirmado** | Fuentes del repositorio, marcado «unconfirmed» | **Baja**, y es la más importante para una plataforma de genealogía |
| Personas fallecidas | **No se sabe.** El campo `deceased_data` de Chile está vacío en el repositorio | Nada | ❔ |
| Cambios pendientes | Un resultado de búsqueda (Garrigues) menciona una moción, Boletín 18.060-07, que busca modificar la ley | Extracto de una búsqueda | Baja: comprobar si prosperó |

El texto oficial está en la Biblioteca del Congreso:
<https://www.bcn.cl/leychile/navegar?idLey=21719>. Con él abierto, la sección 7
es una lista de preguntas con respuesta de sí o no.

---

## 3. Qué datos personales guarda el sitio

Es el «registro de lo que se trata», y sirve de base para escribir la política de
privacidad. Cada fila se comprobó en el código.

### Sitio de genealogía (`url-routing/`)

| Dato | Dónde | Para qué | Quién lo ve | Cuánto dura |
|---|---|---|---|---|
| Nombres, apellidos, sexo, padres y apellido de cada persona | `personas` | La ficha genealógica | Visitantes: solo quien no pueda estar vivo ni sea privado ni esté retirado. Propietario: todo | Sin plazo |
| Fechas y lugares de nacimiento, matrimonio y defunción | `sucesos` | Lo mismo | Igual; si un participante está oculto, el suceso no se ve | Sin plazo |
| Documentos de respaldo (actas, censos) | `registros` | La fuente de cada dato | Igual | Sin plazo |
| Árboles de personas con nombre de autor | `colecciones`, `usuarios` | Publicar un árbol | Los públicos, todos; los privados, solo el propietario | Sin plazo |
| Correo y nombre de quien aportó | `usuarios` | Saber quién aportó | Solo el propietario | Sin plazo |
| **Pedidos de retiro**: motivo, texto libre, **correo de contacto** | `pedidos_retiro`, `pedido_retiro_eventos` | Atender el reclamo y dejar el registro | Solo el propietario | El correo, el texto y las marcas se borran 12 meses después de resolverse **si el cron lleva `--depurar`** (opcional; [`url-routing/docs/ACCESO.md`](url-routing/docs/ACCESO.md)). Se conservan el número, la página, el motivo, el estado y el historial. Pendientes u ocultos no se tocan |
| **Registro de supresiones**: el id que se borró, cuándo, el pedido y cuántas filas se quitaron. **Nunca el nombre** | `supresiones_persona` | Probar que se hizo y repetirlo tras restaurar una copia | Solo el propietario | Sin plazo |
| Cookie de sesión (`PHPSESSID`) con el token CSRF, el carrito o mensajes | Archivo de sesión en el servidor | Que un formulario funcione | Nadie | Caduca a la hora sin actividad, o a las 12 horas |
| IP de quien visita, como hash SHA-256 | Archivo del limitador, en el directorio temporal | Frenar el abuso (100 peticiones por minuto) | Nadie | Se borra a la hora |
| Log de la aplicación: errores, y el número y la página de un pedido de retiro, **nunca quién lo pidió** | `LOG_FILE` | Operar el sitio | Quien administra el servidor | Lo que dure el archivo: no hay rotación definida para este |
| **Log de acceso de nginx**: IP, navegador y dirección pedida | `/var/log/nginx/url-routing/access.log` | Operar el sitio | Quien administra el servidor | 14 días, si el servidor se armó con `vps-setup/` (`07_D-setup-logrotate.sh`); si no, lo que tenga el sistema |
| Copias de seguridad | Donde las guarde quien administra | Recuperarse de un desastre | Quien las guarde | **Lo que cada quien decida**: no hay copias automáticas |

### Tienda (POS, mismo código)

| Dato | Dónde | Notas |
|---|---|---|
| Dirección de envío, escrita por el cliente en un campo de texto | `ordenes.direccion_envio` | Se conserva mientras exista el pedido, sin purga automática (decisión documentada en [`url-routing/docs/DATABASE.md`](url-routing/docs/DATABASE.md)). Puede traer un nombre y un teléfono si el cliente los escribe |
| Libreta de direcciones y lista de deseos | `direcciones`, `deseos` | Existen en el esquema |
| Pagos | — | **No se toman**: el checkout termina antes (`CheckoutController`). No hay cuentas de cliente: los pedidos quedan a nombre de un usuario fijo del sitio |

### Guías telefónicas históricas (`phone-directory/`)

Nombre, dirección y teléfono de personas, de guías de hasta 1975
([`phone-directory/README.md`](phone-directory/README.md)). Un visitante solo ve lo
de hace más de 92 años o lo de quien el propietario marca como fallecido con su
comprobante. Las razones sociales (empresas) no se filtran; que lleven un nombre
propio es la issue #233, **sin resolver**.

---

## 4. Derechos y obligaciones frente a lo que hace el sitio

| # | Qué pide la ley (según §2) | Qué hace el sitio hoy | Estado | Qué falta |
|---|---|---|---|---|
| 1 | **Informar** quién trata los datos, para qué y con qué base | Hay un borrador de términos con huecos y **no hay política de privacidad** | ❌ | Escribirla (puedo redactar el borrador a partir de §3) y completar `[NOMBRE]`, `[CORREO]`, `[PLAZO]` |
| 2 | **Base para tratar** los datos de cada persona | Solo se muestra a quien no puede estar vivo; no hay una base escrita (interés legítimo, fuente pública, investigación) | ◐ | Elegir y escribir la base **por tipo de dato**. Depende de las preguntas 2 y 3 de §7 |
| 3 | **Acceso**: la persona pide ver qué se tiene de ella | `php bin/persona.php acceso <id>` imprime en JSON la ficha, sus sucesos, registros, colecciones y organizaciones. Los parientes salen como cantidades y de los pedidos de retiro no se dice quién los pidió ni qué escribió | ◐ | **Cómo se comprueba que quien pide es la persona** (o su familiar) antes de mandarle el resultado: imprime también lo que un visitante no ve. Y ensayarlo con datos reales |
| 4 | **Rectificación** | Se corrige a mano en la base. No hay pantalla ni registro de qué se corrigió | ◐ | Procedimiento escrito; idealmente anotar qué y cuándo |
| 5 | **Supresión** | «Retirar» **oculta**. Borrar de verdad: `php bin/persona.php suprimir <id> [--pedido=N] --confirmar`, en una transacción, con un registro sin el nombre y `reaplicar` si se restaura una copia vieja. Lista los textos libres que nombran a la persona **para leerlos a mano** (no los toca) | ◐ | Ensayarla con datos reales. Leer esos textos. Confirmar tres decisiones que tomé yo: se **borra** (no se anonimiza), los **registros** se conservan, los **sucesos compartidos** se conservan con ella fuera |
| 6 | **Oposición** | El formulario `/r/{id}/` con el motivo «habla de mí» o «de un familiar fallecido» | ✅ | Solo atender dentro del plazo |
| 7 | **Portabilidad** | Exporta a GEDCOM una colección (`/{id}/3/`) | ◐ | No hay salida para una persona suelta. Comprobar si aplica a estos datos (§7, pregunta 5) |
| 8 | **Bloqueo temporal** mientras se revisa | El estado «oculto» hace justo eso. A las 24 horas se aplica solo, **cuando el cron del servidor lleve `--ocultar`** (decidido; falta instalarlo) | ✅ | Comprobar que la ley lo pide con ese nombre, e instalar el cron |
| 9 | **Plazo de respuesta** | El borrador de términos promete 10 días hábiles para responder | ◐ | Si el plazo legal son 30 días corridos, lo prometido es más estricto; no firmar un plazo que no se puede cumplir |
| 10 | **Decisiones automatizadas** sobre personas | Ninguna. El filtro de difamación solo marca para quien revisa; decide una persona | ✅ | — |
| 11 | **Seguridad** | HTTPS obligatorio, un solo secreto que se rota, cabeceras, limitador, sin contraseñas de visitantes, sin pagos. **Sin cifrado de la base en reposo** (depende del servidor) | ◐ | Decidir copias de seguridad y dónde viven (paso 11 de [`DESPLIEGUE.md`](url-routing/docs/DESPLIEGUE.md)) |
| 12 | **Avisar de una vulneración** a la Agencia y a las personas | [`SECURITY.md`](SECURITY.md) dice cómo **reportarle** una falla al mantenedor. No hay un procedimiento para cuando **ya pasó** | ❌ | Una hoja de «si pasa»: detectar, contener (rotar el token cierra todas las sesiones), evaluar, avisar, anotar. Puedo redactarla |
| 13 | **Registro de lo que se trata** | §3 de este documento | ◐ | Pasarlo a un lugar estable y mantenerlo al día |
| 14 | **Conservación limitada** | Los pedidos de retiro tienen `--depurar` (12 meses; hay que ponerlo en el cron: la guía de despliegue aún no lo trae). Caducan las sesiones, el hash de IP y el log de nginx. **No tienen plazo**: la ficha y sus sucesos, las direcciones de la tienda, el log de la aplicación y las copias | ◐ | Decidir un plazo para esos cuatro y poner `--depurar` en el cron (§5, punto 2) |
| 15 | **Encargados y transferencias** | El servidor es un VPS de un proveedor; no se ha decidido el país | ❌ | Decidir el país y comprobar qué exige la ley con el proveedor |
| 16 | **Datos de categorías especiales** | Genéticos o de salud no se piden. Un acta puede decir la causa de una muerte, una religión o un origen | ❔ | Mirar si alguna ficha o registro lo dice (§7, pregunta 4) |
| 17 | **Menores** | Los vivos no se muestran, así que no hay fichas de niños visibles. El formulario de retiro pide un correo a quien sea | ✅ | — |
| 18 | **Cookies** | Solo la técnica, solo si usas un formulario o entras como propietario ([`ACCESO.md`](url-routing/docs/ACCESO.md)) | ✅ | Decir lo mismo en la política |
| 19 | **Delegado o modelo de cumplimiento** | Una persona administra todo | ❔ | Comprobar si es obligatorio o voluntario para un sitio así (§7, pregunta 6) |
| 20 | **Razones sociales con nombre propio** (guías históricas) | No se filtran | ❌ | Issue #233. El PR #321 (abierto) corrige la otra mitad: que una persona se guarde como empresa y se salte el filtro |
| 21 | **Personas vivas sin fechas** | Quien no tiene nacimiento ni defunción **no** se presume viva (decisión documentada en `Privacidad.php`) | ◐ | Es una decisión que tomó la persona a cargo; mantenerla por escrito y marcar a mano los casos que se conozcan |

---

## 5. Lo que bloquea abrir el sitio y lo que puede esperar

**Antes de abrir** (sin esto, el sitio no debería recibir público):

1. **Política de privacidad escrita y términos completos**, con un correo real que
   alguien lea.
2. **Plazos de conservación** decididos para las direcciones de la tienda, los logs
   de la aplicación y las copias de seguridad, y `--depurar` puesto en el cron de los
   pedidos de retiro (ya existe, con 12 meses como valor inicial).
3. **Una forma de borrar de verdad** cuando alguien lo pida y corresponda, con
   registro. Ya existe (`bin/persona.php suprimir`): se borra a la persona, sus sucesos
   propios, sus vínculos y su lugar en colecciones y organizaciones; sus hijos conservan
   su fila; se conservan los registros y los sucesos compartidos; el registro no lleva el
   nombre. Falta **ensayarla con datos reales** y escribir **cómo se comprueba quién
   pide** antes de actuar o de mandar un acceso.
4. **País del servidor y responsable** (nombre, domicilio) decididos y puestos.
5. **Una hoja de «si hay una fuga»** de una página.

**Puede esperar a después de abrir:**

- Una pantalla para rectificar y para pedir acceso (el acceso y la supresión ya tienen
  una herramienta de línea de comandos; la rectificación sigue siendo a mano, y sirve
  mientras haya pocas solicitudes si el procedimiento está escrito).
- Cifrado de la base en reposo y copias automáticas.
- Un registro de actividades más fino que el de §3.

---

## 6. Plan de 31 días

| Cuándo | Qué | Quién |
|---|---|---|
| **Semana 1** (31 oct – 6 nov) | Abrir el texto oficial y responder las preguntas de §7. Decidir país del servidor, responsable y plazos de conservación | Persona a cargo |
| **Semana 2** (7 – 13 nov) | Redactar la política de privacidad y completar los términos con los datos reales | Claude propone el borrador; la persona a cargo lo ajusta |
| **Semana 3** (14 – 20 nov) | Ensayar `bin/persona.php` con datos reales y escribir el procedimiento de acceso (incluida la comprobación de quién pide), de supresión y de rectificación | Claude propone el procedimiento; la persona a cargo prueba la herramienta |
| **Semana 4** (21 – 27 nov) | La hoja de «si hay una fuga». Probar de punta a punta: pedir un retiro, recibir el aviso por correo de cron, atender una solicitud de borrado | Persona a cargo con Claude |
| **28 nov – 1 dic** | Repasar este documento fila por fila y poner la fecha en cada ❌ que se decida dejar | Persona a cargo |

---

## 7. Qué comprobar contra el texto oficial

Con <https://www.bcn.cl/leychile/navegar?idLey=21719> abierto. Cada pregunta
cambia una fila de §4:

1. **Vigencia.** ¿Rige el 1 de diciembre de 2026 para todas las obligaciones o
   algunas tienen otra fecha? ¿Prosperó la moción que la modifica (Boletín 18.060-07)?
2. **Personas fallecidas.** ¿La ley protege los datos de una persona muerta? Si lo
   hace, ¿quién puede pedir en su nombre y durante cuánto tiempo? (Filas 2 y 5.
   Para una plataforma de genealogía es la pregunta que más pesa.)
3. **Investigación histórica o genealógica.** ¿Hay una excepción, con qué
   condiciones y se aplica a un sitio que publica? (Fila 2.)
4. **Datos de categorías especiales.** ¿Cuáles son? ¿El origen étnico, la religión
   o la causa de una muerte que dice un acta cuentan? (Fila 16.)
5. **Portabilidad.** ¿A qué datos aplica: a los que la persona entregó? ¿Hay que
   dar un archivo? (Fila 7.)
6. **Delegado y modelo de cumplimiento.** ¿Obligatorios para quien administra un
   sitio así, o voluntarios? (Fila 19.)
7. **Plazos.** ¿Cuánto hay para responder a cada derecho, y se puede prorrogar?
   (Fila 9.)
8. **Vulneraciones.** ¿A quién se avisa, en qué plazo y en qué casos?
   (Fila 12.)
9. **Conservación.** ¿Hay un plazo máximo o solo «el necesario»? (Fila 14.)
10. **Encargados y transferencias al extranjero.** ¿Qué exige la ley si el
    servidor está fuera de Chile? (Fila 15.)

---

## 8. Lo que no pude comprobar

- **El texto de la ley.** `bcn.cl` y `leychile.cl` no responden desde la sesión
  (la red solo deja pasar los dominios permitidos). Todo lo de §2 viene de las
  fuentes del repositorio y de extractos de una búsqueda web.
- **Cómo se comporta el sitio en un servidor real.** Los datos de §3 salen del
  código y de las migraciones; lo que hace nginx o el sistema (log de acceso,
  rotación) sale de los scripts de [`vps-setup/`](vps-setup/README.md) y del
  ejemplo de nginx, no de un servidor encendido.
- **El volumen de datos.** No hay un catálogo real en el repositorio, así que no
  se sabe cuántas fichas, cuántas razones sociales con nombre propio ni cuántos
  datos de categorías especiales hay.

Documentos relacionados: [`PRIVACIDAD-Y-HONRA.md`](PRIVACIDAD-Y-HONRA.md),
[`TERMINOS-DE-USO.md`](TERMINOS-DE-USO.md), [`RECLAMOS.md`](RECLAMOS.md),
[`PREGUNTAS-PARA-ABOGADO.md`](PREGUNTAS-PARA-ABOGADO.md), [`SECURITY.md`](SECURITY.md).

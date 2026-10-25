# Preguntas para el abogado

> **No es asesoría legal ni una opinión sobre la ley.** Es la lista de lo que el
> equipo no puede decidir solo, ordenada para llevarla a una reunión con un
> abogado chileno. Reúne lo que ya está marcado con **⚖️** en
> [`TERMINOS-DE-USO.md`](TERMINOS-DE-USO.md) y lo que
> [`PRIVACIDAD-Y-HONRA.md`](PRIVACIDAD-Y-HONRA.md) dice que hay que confirmar, y
> agrega lo que sale de mirar qué hace hoy el sitio. Donde se nombra una ley, es
> la que ya cita el repositorio; las demás aparecen solo como **tema**, para que
> el abogado diga si aplica.
>
> Preparada el **23 de octubre de 2026**. La Ley 21.719 rige desde el
> **1 de diciembre de 2026** según las fuentes del repositorio
> (`privacy-laws/countries/cl/`): ese día faltaban 39 días. Esas fuentes son un
> resumen de un tercero (`confidence: medium`, revisado el 14 de octubre de 2026),
> no el texto de la ley: la primera pregunta es si la fecha y las obligaciones
> son esas.

## Qué llevar a la reunión

1. Este documento.
2. [`TERMINOS-DE-USO.md`](TERMINOS-DE-USO.md) (el borrador) y la sección «Marco
   legal chileno» de [`PRIVACIDAD-Y-HONRA.md`](PRIVACIDAD-Y-HONRA.md).
3. La hoja de «Qué hace hoy el sitio» de más abajo, para no tener que explicar el
   código.
4. El origen del proyecto: [`defamatory-content-review/docs/ORIGEN.md`](defamatory-content-review/docs/ORIGEN.md).
   Cuenta el reclamo que cerró la plataforma anterior sin dar el nombre completo
   de la persona; si el abogado necesita más, que se le diga en persona y no
   quede en el repositorio, que es público.

## Qué hace hoy el sitio (para no tener que leer el código)

Cada punto sale de un documento del repositorio, que se cita.

- **No se puede escribir.** El sitio de genealogía es de solo lectura: los datos
  entran por semillas e importaciones, no hay cuentas por persona ni formularios
  de contenido ([`marketing/VERIFICACION.md`](marketing/VERIFICACION.md)). Los
  términos de uso, en cambio, hablan de usuarios que suben textos y fotos: son
  para la plataforma que se quiere abrir, no para lo que hay.
- **Un solo propietario**, identificado con un secreto, que lo ve todo
  ([`url-routing/docs/ACCESO.md`](url-routing/docs/ACCESO.md)).
- **Personas vivas.** Se presume viva a quien nació hace menos de 110 años y no
  tiene defunción, o a quien el propietario marca. Un visitante ve «Persona viva»
  sin nombre, fechas ni lugar. Es una deducción por fechas, no una certeza.
- **Colecciones privadas.** Un visitante recibe un 404; quien solo figura en
  colecciones privadas se muestra como «Persona privada».
- **Pedir el retiro.** Cada página lleva «Pedir el retiro de esta página»
  (`/r/{id}/`), sin cuenta. El pedido guarda motivo, texto libre y **el correo de
  quien lo pide**, y un historial que solo se agrega. **Recibir un pedido no
  oculta nada**; el propietario decide en `/0/3/`. Hay un script de cron
  (`bin/pedidos-retiro.php`) que avisa de los que esperan y, solo si se activa,
  oculta los que pasan de 24 horas.
- **Guías telefónicas históricas** (`phone-directory/`): nombre, dirección y
  teléfono de personas, de guías de hasta 1975. Un visitante solo ve las de hace
  más de 92 años, o las de quien el propietario marca como fallecido con su
  comprobante.
- **Exportación a GEDCOM** de una colección ([`url-routing/docs/ACCESO.md`](url-routing/docs/ACCESO.md)).
- **Lo que se guarda de quien visita:** quien solo lee páginas no recibe cookie
  ni deja sesión en el servidor; la cookie de sesión (`PHPSESSID`) se pone solo a
  quien usa un formulario (pedido de retiro, carrito y pago de la tienda) o entra
  como propietario. Además, un archivo por IP (guardado con un hash, no la IP) en el directorio temporal
  para el limitador de peticiones, y el log de la aplicación (que anota el número de un
  pedido de retiro y la página, nunca quién lo pidió).
- **El filtro de insultos y acusaciones existe, pero el sitio no lo usa** todavía,
  porque no tiene dónde escribir texto
  ([`PRIVACIDAD-Y-HONRA.md`](PRIVACIDAD-Y-HONRA.md), cabo suelto 1).
- **El marketing** está escrito en español y en 15 idiomas más, como borrador sin
  revisión nativa ([`marketing/`](marketing/)).
- **Faltan datos que solo la persona a cargo tiene:** quién es el responsable, su
  domicilio, el correo de contacto, en qué país está el servidor.

## A. Antes del 1 de diciembre de 2026 (Ley 21.719)

1. ¿La ley rige esa fecha y reemplaza a la Ley 19.628 sin período de transición?
   ¿Dónde está el texto de referencia para citar?
2. **¿Es responsable de datos esta plataforma?** Trata datos de personas
   fallecidas, de personas vivas que no la conocen (guías telefónicas, fichas) y
   de quien pide un retiro. ¿Los datos de las personas fallecidas están
   protegidos? ¿Hasta cuándo?
3. **Base de licitud.** Para fichas de personas vivas que no consintieron
   (cuando existan), ¿cuál sirve: interés legítimo, fuentes de acceso público,
   otra? ¿Cómo se documenta?
4. **Guías telefónicas de hasta 1975.** Son impresas y se publicaron, pero hoy
   se reúnen en una base consultable, con nombre, dirección y teléfono. ¿Cambia
   algo que el dato esté en una fuente accesible al público? ¿Sirve la regla
   actual (solo se ve lo de hace más de 92 años) o hace falta otra, por ejemplo
   que el retiro opere también sobre esas entradas?
5. **Datos sensibles de terceros.** Salud, filiación, origen étnico o creencias
   aparecen en la genealogía de cualquier familia («murió de…», «hijo no
   reconocido»). ¿Qué trato exige la ley a quien los publica y qué hace falta
   para el caso de personas fallecidas?
6. **Derechos de acceso, rectificación, supresión, oposición y portabilidad.**
   ¿Cómo se atienden cuando quien los pide no tiene cuenta y es una persona de
   la que solo hay una ficha? ¿Qué se pide para acreditar quién es? ¿Cuál es el
   plazo de respuesta?
7. **Registro y delegado.** ¿Hay que inscribirse ante la Agencia de Protección de
   Datos Personales? ¿Hace falta un delegado de protección de datos o un modelo
   de prevención, a esta escala (una persona)?
8. **Brechas de seguridad.** ¿Qué hay que avisar, a quién y en qué plazo? Hoy
   no hay un protocolo escrito.
9. **Transferencias al extranjero.** ¿Importa en qué país está el servidor? ¿Y
   que haya visitantes y marketing en otros países?
10. **Conservación.** El registro de pedidos de retiro y de publicaciones, los
    respaldos y los logs: ¿cuánto tiempo se pueden o deben conservar? ¿Cómo se
    concilia con un pedido de supresión (el historial de un retiro solo se
    agrega)?
11. **Sanciones.** El resumen de las fuentes habla de multas de hasta 20.000 UTM
    por infracciones muy graves. ¿Qué infracciones son las que realmente aplican
    a este sitio?

## B. Antes de abrir la plataforma al público (términos de uso)

Estas son las marcas **⚖️** del borrador, por sección.

12. **§2, licencia.** Alcance y duración del permiso que da quien publica (mostrar,
    guardar, copiar). ¿Es revocable? ¿Qué pasa con las copias de seguridad?
13. **§3, lo que no se puede publicar.** ¿La lista cubre lo que hace falta? ¿Es
    exigible lo de «aunque creas que es cierto»? ¿Qué pasa con una sentencia
    firme: se puede publicar con fuente, y quién lo decide?
14. **§4, el filtro.** Se promete que todo texto libre pasa por un filtro
    automático y lo dudoso por una persona. ¿Es prudente prometer eso, o crea
    una obligación que después no se cumpla (hoy el filtro sólo revisa las notas
    internas del panel; el sitio de genealogía no tiene dónde escribir)?
15. **§5, plazos del retiro.** El borrador dice «24 horas» para ocultar y «10 días
    hábiles» para responder. ¿Son plazos que se pueden comprometer? ¿Qué pasa si
    no se cumplen? ¿Es correcto ocultar sin haber revisado (hoy es opcional, con
    `--ocultar`), o conviene no prometer ocultar?
16. **§5, quiénes pueden pedir el retiro por un difunto.** Grado de parentesco, y
    cómo se acredita sin pedir más datos personales.
17. **§6, el registro.** Plazo de conservación y base legal para guardar quién
    publicó qué. ¿Se entrega a un tercero solo con orden judicial?
18. **§7, política de privacidad.** Falta redactarla. ¿Se escribe a partir de la
    sección 7 de los términos o es un documento aparte?
19. **§8, sanciones.** Graduación (retirar, avisar, suspender, cerrar) y aviso
    previo. ¿Cuándo se puede entregar el registro a la autoridad por una
    calumnia o una amenaza?
20. **§9, responsabilidad de la plataforma.** El riesgo que cerró el proyecto
    anterior: el reclamo fue contra quien mantenía el sitio, no contra quienes
    escribieron. ¿Cuál es hoy el alcance de la responsabilidad de quien aloja
    contenido de terceros en Chile? ¿Qué lo reduce: aviso y retiro rápido,
    revisión previa, términos?
21. **§10, cambios y ley aplicable.** Plazo de aviso (el borrador dice 30 días) y
    ciudad de los tribunales.
22. **Quién firma.** ¿Persona natural o sociedad? Si el responsable es una persona
    natural, ¿qué riesgo patrimonial y qué convendría cambiar antes de abrir?

## C. Honra y difamación

23. **Calumnia e injuria por escrito y con publicidad** (Código Penal, arts. 412 a
    431, según `PRIVACIDAD-Y-HONRA.md`). ¿Qué hace que un texto de un tercero se
    atribuya a quien lo aloja? ¿Cambia si hubo aviso y no se retiró?
24. **Prueba de la verdad.** El repositorio decide que lo que «resulta cierto
    después» no se podía publicar antes. ¿Es una regla de prudencia razonable o
    más estricta que la ley?
25. **Personas fallecidas.** Se dice que la familia puede perseguir la calumnia o
    la injuria contra un difunto. ¿Quiénes? ¿Sin límite de tiempo?
26. **Una acusación con fuente pública** (una sentencia, una noticia): ¿qué
    condiciones debe cumplir para publicarse sin exponerse?

## D. Marketing y correo electrónico

27. **Envío de correos** a quien se registre o a una lista, en varios países y 15
    idiomas: ¿qué consentimiento exige cada mercado? ¿Hay que registrar ese
    consentimiento?
28. **Afirmaciones de privacidad y seguridad.** `marketing/VERIFICACION.md` deja
    tres frases que el producto respalda hoy («Las personas que podrían estar
    vivas no se muestran…», «Tus colecciones privadas solo las ves tú», «Puedes
    llevarte tu árbol: exporta a GEDCOM») y aparta otras («espacio seguro»,
    «tus datos son tuyos»). ¿Alguna de las tres puede ser engañosa tal como está
    escrita? ¿Qué dice la ley de protección al consumidor sobre este tipo de
    promesas?
29. **Cookies.** El sitio pone su cookie de sesión (`PHPSESSID`) solo cuando hace
    falta: a quien envía un formulario (pedido de retiro, carrito y pago de la
    tienda) y al propietario que entra. Quien solo lee páginas no recibe ninguna
    (antes sí la recibía cada visita). La cookie guarda el token que valida el
    envío del formulario y el carrito; no sirve para seguir a nadie. ¿Hace falta
    un aviso o un consentimiento para esa cookie? ¿Y para el archivo por IP, con
    hash, que usa el limitador de peticiones?
30. **Marca y nombres.** `conce.com` y «Contrastocolor»: ¿conviene registrarlas
    antes de un lanzamiento? (Es una pregunta de propiedad industrial, fuera del
    alcance de este documento si no la atiende el mismo abogado.)
31. **§5, retirar una persona.** Al retirarla, el sitio la muestra como «Persona
    privada» en todas las páginas y oculta los sucesos y registros en los que
    participa, pero **no borra los datos**. ¿Basta para atender un pedido de
    supresión o de oposición, o hay que borrarlos? ¿Se puede conservar la
    información para responder a un reclamo?
32. **§7, direcciones de envío de la tienda.** La dirección de un pedido se
    conserva mientras exista el pedido, sin purga automática (decisión de la
    persona a cargo, documentada en `url-routing/docs/DATABASE.md`). ¿Es lícito
    desde el 1 de diciembre de 2026, y por cuánto tiempo conviene conservarla
    (la garantía legal es de seis meses)?

## Lo que tiene que decidir la persona a cargo antes de la reunión

Sin esto el abogado no puede cerrar los textos:

- Quién es el responsable (persona o sociedad), su domicilio, el correo de contacto
  y la ciudad de los tribunales.
- En qué país está el servidor y quién más tiene acceso a los respaldos.
- Si se va a activar el ocultamiento automático de los pedidos de retiro
  (`--ocultar`) y con qué plazo.
- Qué mercados e idiomas se van a usar de verdad, y en cuáles se va a mandar
  correo.
- Si habrá cuentas y contenido subido por usuarios, o si el sitio seguirá siendo
  de solo lectura. Los términos de uso cambian mucho según la respuesta.

## Qué hacer con las respuestas

1. Quitar las marcas **⚖️** de [`TERMINOS-DE-USO.md`](TERMINOS-DE-USO.md) y
   completar los datos entre corchetes.
2. Escribir la política de privacidad (pregunta 18).
3. Actualizar «Marco legal chileno» en [`PRIVACIDAD-Y-HONRA.md`](PRIVACIDAD-Y-HONRA.md)
   con lo que el abogado confirme, y corregir lo que no sea así.
4. Si cambian los plazos (24 horas, 10 días, 30 días), cambiarlos también en el
   código: el valor por omisión de `RETIRO_PLAZO_HORAS` en `url-routing/` y la
   guía de despliegue.

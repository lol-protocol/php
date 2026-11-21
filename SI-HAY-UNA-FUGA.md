# Si hay una fuga de datos — borrador

> **Borrador. No es asesoría legal.** Es lo que haría quien administra el sitio el día que pase,
> con las herramientas que el repositorio ya tiene. Está pensado para leerse **antes**, una vez, y
> para abrirse con prisa el día que haga falta. Para **reportar** una vulnerabilidad que alguien
> encontró, ver [`SECURITY.md`](SECURITY.md); esto es para cuando **ya pasó** (o se sospecha).
>
> - Lo entre corchetes (`[CONTACTO]`…) falta.
> - Lo marcado con **⚖️** (a quién avisar, en qué plazo y en qué casos) **no está comprobado**
>   contra el texto de la Ley 21.719: es la pregunta 8 de §7 de
>   [`CUMPLIMIENTO-LEY-21719.md`](CUMPLIMIENTO-LEY-21719.md). Hasta comprobarlo, la regla segura es
>   **avisar rápido, no tarde**.
> - Todavía **no se ha ensayado**. Léela en voz alta una vez con el servidor delante.

## Qué cuenta como fuga

Cualquier caso en que alguien **sin permiso** pudo ver, copiar o cambiar datos de personas, o en que
se perdieron. Ejemplos de este sitio, de más a menos grave:

1. **Lo que el sitio oculta a un visitante** quedó a la vista o se llevó: personas que pueden estar
   vivas, páginas retiradas, colecciones privadas, o lo que trae una copia de seguridad.
2. **El correo o el texto** de quien hizo un pedido de retiro o una solicitud de datos.
3. **El secreto del propietario** (`OWNER_TOKEN`), una copia de seguridad o la clave privada de las
   copias cifradas: abren todo lo anterior.
4. Una dirección de envío de la tienda.

No es una fuga: un error de la página, una caída o algo que solo vio quien administra. Si dudas, trátalo
como fuga hasta descartarla.

## Las primeras horas, en orden

**1. Anota la hora y no borres nada.** Escribe cuándo te enteraste, cómo y qué ves. No limpies logs, no
reinicies el servidor ni restaures nada antes de copiar lo que sirva de evidencia.

**2. Contener.** Elige lo que corresponda; no hace falta todo.

- **Secreto del propietario filtrado o dudoso:** cambia `OWNER_TOKEN` en `.env` y recarga PHP-FPM
  ([`DESPLIEGUE.md`](url-routing/docs/DESPLIEGUE.md), paso 10). **Cierra todas las sesiones abiertas**,
  la tuya incluida.
- **Una página muestra lo que no debe:** ocúltala ya. Si es una persona, márcala como viva (como
  propietario); si hay un pedido de retiro, ocúltalo desde el panel `/0/3/`. Si no sabes cuál es o no
  alcanza, deja el sitio fuera de línea (nginx devolviendo una página de mantenimiento) mientras
  averiguas.
- **Alguien entró al servidor:** cambia las claves de acceso (SSH, base de datos) y **no confíes en nada
  de lo que haya dentro**; lo que escribió el atacante puede estar en la base.
- **Una copia de seguridad se perdió:** si estaba cifrada, la fuga es menor, pero **no asumas** que no
  lo es hasta saber con qué clave. Si no lo estaba, trátala como una fuga de todo lo que trae.

**3. Evaluar: qué datos, de quién y por cuánto tiempo.** Responde por escrito:

- ¿Qué categorías: personas posiblemente vivas, correos de quienes escribieron, direcciones de
  envío, solo datos que un visitante ya ve?
- ¿De cuántas personas, aunque sea aproximado?
- ¿Desde cuándo y hasta cuándo estuvo expuesto? Los registros ayudan: el del servidor web
  (`/var/log/nginx/url-routing/access.log`, 14 días en un servidor armado con `vps-setup/`) y el de la
  aplicación (`LOG_FILE`). **El registro de la aplicación no trae quién hizo un pedido de retiro**, a
  propósito: tampoco te lo va a decir aquí.
- ¿Se puede **probar** qué se llevaron y qué no, o hay que suponer lo peor?

**4. Avisar.** **⚖️** Sin comprobar contra la ley.

- **A la Agencia de Protección de Datos Personales:** [CÓMO Y EN QUÉ PLAZO ⚖️].
- **A las personas afectadas:** [EN QUÉ CASOS Y EN QUÉ PLAZO ⚖️]. Díselo claro: qué pasó, qué datos,
  qué hacer, a quién escribir y qué estás haciendo. **No incluyas en el aviso los datos expuestos.**
  Si son quienes escribieron un pedido, tienes su correo en la solicitud (`php bin/solicitudes.php --ver=N`,
  solo en tu terminal); usa un correo que no copie lo que ellos escribieron.
- **Al proveedor del servidor**, si la entrada fue por ahí: [CONTACTO DEL PROVEEDOR].
- **A quien te reportó la falla**, si hubo alguien: gracias y qué hiciste.

**5. Arreglar de verdad.** La causa, no el síntoma. Si fue una falla del código, un PR en borrador con una
prueba que la reproduzca, como cualquier otro cambio ([`CONTRIBUTING.md`](CONTRIBUTING.md)). **Sin publicar
los detalles** de la falla hasta que esté corregida.

**6. Si hay que restaurar una copia**, hazlo **después** de contener y de guardar la evidencia, y luego
corre `php bin/persona.php reaplicar` (y con `--confirmar`): una copia vieja trae de vuelta a quien se
borró. Verifica la copia antes: `php bin/copias.php --verificar=…`
([`MANTENIMIENTO.md`](url-routing/docs/MANTENIMIENTO.md)).

**7. Cerrar y aprender.** Anota qué pasó, qué se hizo y qué cambia para que no se repita.

## Qué anotar (y dónde)

Un registro por incidente, **fuera del repositorio** (aquí no van datos de personas): fecha y hora de
detección, quién lo detectó, qué datos y de cuántas personas, cuándo empezó y cuándo terminó, qué se
contuvo y cuándo, a quién se avisó y cuándo (con el texto del aviso), la causa y qué se arregló. Si alguien
te pregunta mañana qué hiciste, ese registro es la respuesta.

## Antes de que pase (una tarde)

1. Completar los huecos y comprobar los ⚖️ con el texto oficial (pregunta 8 de §7 de
   `CUMPLIMIENTO-LEY-21719.md`).
2. Tener a mano los contactos: proveedor del servidor, la Agencia, quien te ayuda con el servidor.
3. Que las copias lleguen fuera del servidor y se pueda restaurar una: `bin/copias.php` con su cron,
   `--vigilar` y `--verificar` ([`MANTENIMIENTO.md`](url-routing/docs/MANTENIMIENTO.md)). **Una fuga
   sin copia que restaurar es peor.**
4. Ensayar el paso 2: rotar el token en un servidor de prueba y ver que cierra las sesiones.
5. Decidir si se cifra la clave privada de las copias, dónde vive y quién más la puede usar.

# Acceso: qué es privado y quién puede verlo

El proyecto no tiene cuentas de usuario (es un addon de un solo operador), así que hay **una sola identidad con privilegios: el propietario**. Se identifica con un secreto compartido, `OWNER_TOKEN`. Todo lo que es privado exige ser el propietario; el resto es público.

La lógica está en [`Support/AccessPolicy.php`](../Support/AccessPolicy.php) y los controladores la usan a través de tres métodos de `BaseController`: `soloPropietario()`, `pedirPropietario()` y `exigirPropietario()`.

## Qué es privado y cómo responde

| Recurso | Quien no es el propietario recibe |
|---|---|
| Colección con `publica = false`: `/{id}/`, `/{id}/1/`, `/{id}/3/` (GEDCOM) | **404**, idéntico al de un id que no existe |
| Pedidos del POS: `/{código de 16 dígitos}/` y sus acciones | **404**, igual (salvo para la sesión que hizo la compra) |
| Página de edición de una colección: `/{id}/2/` | **404** si la colección es privada; **401** si es pública |
| Área de cuenta, genealogía y POS: `/0/` y sus acciones | **401** (el navegador pide la clave); **404** si no hay `OWNER_TOKEN` |
| Búsqueda de colecciones (`/b/7/`) | las privadas no aparecen, ni buscándolas por su nombre exacto |
| Marca de «vive / falleció» de una persona: `/{id de persona}/5/` | **401** (el navegador pide la clave); **404** si no hay `OWNER_TOKEN`. Guardar exige además el token CSRF del formulario |

**Por qué 404 y no 401 o 403.** Los ids son URLs públicas y casi secuenciales: un 401 o un 403 confirmaría cuáles existen. Es lo que la sección «Recursos privados» de `README_URLS.md` ya prometía y el código no cumplía. Lo único que responde 401 es lo que se sabe que existe: el área de cuenta, la edición de una colección pública y la marca de vida de una persona (las personas son públicas, así que pedir la clave no confirma nada). El área de cuenta es además el punto de entrada del propietario.

Las páginas públicas no piden nada: una colección con `publica = true`, las personas, los lugares, los grupos, las organizaciones, los sucesos, los registros y todo el catálogo del POS.

## Personas que podrían estar vivas

Quien no es el propietario no ve lo que se sabe de las personas que podrían estar vivas. Una persona se **presume viva** cuando:

- no tiene un suceso de defunción **propio**, **y**
- su nacimiento tiene fecha y fue hace menos de **110 años** (`Privacidad::ANIOS_VIVA`).

Una persona sin ninguna fecha **no** se presume viva: nada lo dice, y ocultar a todos los antepasados sin fechas vaciaría el catálogo. Una fecha de nacimiento a exactamente 110 años ya cuenta como antigua.

**Qué sucesos cuentan como suyos.** Un nacimiento o una defunción es de quien figura en él con el rol `principal` (`Privacidad::ROL_PRINCIPAL`). Registrar una defunción (`declarante`), ser madre, padre o padrino en un nacimiento, o ser testigo, es estar presente, no morir ni nacer: quien firmó la defunción de otra persona sigue presumiéndose viva, y en su ficha, en los lugares de nacimiento y en los apellidos solo cuentan su propio nacimiento y su propia defunción. Una defunción registrada con otro rol no da por fallecida a la persona: si nació hace menos de 110 años queda oculta (de más, no de menos); se corrige poniéndole el rol `principal` o `viva = FALSE`. Hay una excepción, a propósito: quien no tiene nacimiento propio se toma por no más joven que su hijo, así que queda oculta mientras el hijo lo esté.

**Corregir la deducción.** La columna `personas.viva` (migración `002_personas_viva.sql`) manda sobre las fechas: `TRUE` = vive, `FALSE` = ya falleció, `NULL` = deducirlo. Sirve para marcar a quien vive pero no tiene fechas, o a quien ya murió sin que conste su defunción. El propietario la cambia en la ficha de la persona, en **Privacidad** (`/{id de persona}/5/`, un enlace que solo ella ve): «Vive», «Falleció» o «Deducirlo de las fechas». Una persona que solo está en colecciones privadas se oculta de todos modos, sea cual sea la marca.

| Dónde aparece | Qué ve un visitante |
|---|---|
| La persona (`/{id}/` y sus acciones) | La página existe, para que un enlace desde un árbol no se rompa, pero solo dice «Persona viva»: sin nombre, sexo, apellido, fechas, lugar, familiares ni sucesos. Sus padres también la identificarían |
| Árboles (colección, ascendencia, descendencia, vínculos) | Aparece como «Persona viva» en su lugar, para que el árbol conserve su forma; va después de los demás y ordenada por id, para que el orden no diga nada de ella |
| Búsqueda | **No aparece**, y no se puede encontrar por su nombre ni por «persona viva»: si se la encontrara aunque solo se viera la máscara, cualquiera podría comprobar si existe alguien con ese nombre |
| Apellidos (grupos) | No cuenta, no sale en la red del apellido y no pesa en los lugares de nacimiento. Tampoco pesa un nacimiento con un participante vivo |
| Lugares, miembros de organizaciones | No aparece. La lista de nacidos en un lugar tampoco incluye los nacimientos con un participante vivo: el lugar es justo lo que la delataría |
| Sucesos | Un suceso con **algún** participante vivo no existe para el visitante (404, ni en búsquedas ni en listas ni en la cronología de nadie), porque su fecha, lugar o descripción lo delatarían. Lo único que sobrevive es **el año**, en la ficha de la persona fallecida a quien pertenece el suceso (su nacimiento o su defunción): `1950`, nunca el día ni el lugar |
| Registros | Un registro que documenta un suceso así tampoco existe: su título y su fuente suelen nombrar a la persona |
| GEDCOM | La persona sale como «Persona viva», sin sexo, fechas ni lugar; la forma del árbol se conserva y todos los punteros siguen resolviendo. De un suceso oculto, el GEDCOM lleva solo el año (`2 DATE 1950`) y ningún `PLAC` |

El propietario lo ve todo y sus consultas son las de siempre.

Cómo está hecho: `Privacidad` entrega **expresiones de tabla** (`personas()`, `sucesos()`, `registros()`) que sustituyen a las tablas en cada consulta, así que una consulta no puede olvidarse de ocultar algo: solo ve la versión ya oculta. Los repositorios reciben el `Privacidad` de la petición y, si nadie se lo pasa, usan el del visitante: olvidarse oculta de más, nunca de menos. `PagesTest::testNoPageShowsAVisitorAnythingAboutLivingPeople` recorre todas las páginas que la base de demostración puede producir y falla si una consulta o página nueva olvida a `Privacidad`.

## Personas de colecciones privadas

Una colección privada oculta **la colección** (su página, su árbol y su GEDCOM). Para que además no se pueda llegar a sus personas por su id, por la búsqueda o por una página de lugar, apellido o suceso, una persona es **privada** cuando:

- está en **al menos una** colección, **y**
- **ninguna** de sus colecciones es pública (`colecciones.publica`).

Basta una colección pública para que la persona se vea: lo que alguien decidió publicar no se oculta porque otra persona lo haya guardado también en un borrador. Una persona que **no está en ninguna** colección no es privada: nada dice que pertenezca a alguien.

Se oculta igual que una persona viva (ver la tabla de arriba: ficha vacía, sin búsqueda, sin lugares ni apellidos, sin sus sucesos ni los registros que la documentan, GEDCOM sin datos), con una diferencia: su nombre se lee **«Persona privada»**, no «Persona viva», porque no se trata de que viva. Los sucesos que comparte con una persona visible desaparecen de la cronología de esta, como pasa con los de una persona viva.

Es una regla de lectura, no un estado: se evalúa en cada consulta. Si la colección se publica, sus personas se ven en la siguiente petición; si la única colección pública de alguien pasa a privada, deja de verse. Lo mismo ocurre con la deducción de «viva»: ninguna de las dos se guarda.

El propietario lo ve todo.

## Configurar

1. Generar el secreto: `openssl rand -hex 32`.
2. Ponerlo en `.env` (o en el entorno del servidor) como `OWNER_TOKEN=...`.

**Falla cerrado.** Sin `OWNER_TOKEN`, o con uno de menos de 20 caracteres, nadie es el propietario y todo lo privado queda cerrado para todos. Olvidar configurarlo nunca abre nada; el log lo avisa con un *warning* cada vez que alguien abre `/0/`, que es el punto de entrada del propietario.

## Entrar como propietario

- **Navegador:** abrir `/0/`. El navegador pide usuario y clave: el usuario da igual y la clave es `OWNER_TOKEN`. Al aceptarla se abre una sesión (con un id de sesión nuevo) y desde ahí se navega por las páginas privadas sin volver a escribirla.
- **Otros clientes (`curl`, scripts):** `Authorization: Bearer <token>`, o `curl -u x:<token> ...`. Cada petición con las credenciales correctas vale por sí sola.

La sesión del propietario caduca a la hora sin actividad y, en cualquier caso, a las 12 horas. Cuenta como actividad cualquier página que abra el propietario, pública o privada (antes solo contaban las privadas, y quien se quedaba leyendo páginas públicas volvía a ser visitante sin aviso). Está ligada al token: **cambiar `OWNER_TOKEN` cierra todas las sesiones abiertas**; quitarlo también. No hay una página de «salir»: cerrar el navegador termina la sesión.

**La hora depende también de quién limpia las sesiones.** La sesión vive en un archivo de PHP, y quien lo borra la cierra antes de tiempo. La app le pide a PHP `session.gc_maxlifetime = 3600` al abrirla, y eso basta donde PHP limpia por su cuenta (la configuración que trae PHP por omisión). En Debian y Ubuntu, que es lo que arma [`vps-setup/`](../../vps-setup/README.md), PHP no limpia (`session.gc_probability = 0`): un cron (`/etc/cron.d/php`) borra los archivos de sesión que llevan más de `session.gc_maxlifetime` **del `php.ini`** sin tocarse, 1440 segundos (24 minutos) si nadie lo cambió, y no mira lo que la app pida en ejecución. Para que valga la hora hay que subirlo ahí (`session.gc_maxlifetime = 3600` en `/etc/php/8.3/fpm/php.ini`) y recargar PHP-FPM. Si no se sube, la sesión se pierde a los 24 minutos sin actividad: falla cerrado, el propietario vuelve a ser visitante hasta que escriba la clave otra vez.

## Despliegue

La guía paso a paso, con las comprobaciones, está en [`DESPLIEGUE.md`](./DESPLIEGUE.md). Lo que importa para el acceso:

- **HTTPS, obligatorio en producción.** El token viaja en la cabecera `Authorization`. Sin HTTPS lo ve cualquiera en la red.
- **Nginx:** pasa la cabecera a PHP-FPM por defecto; no hace falta nada (probado con nginx 1.24 y PHP-FPM 8.3: clave correcta 200, equivocada 401). Ver [`deploy/nginx.conf.example`](../deploy/nginx.conf.example).
- **Apache:** `public/.htaccess` incluye la regla que entrega la cabecera a PHP (CGI/FPM la descartan). **No se probó en un Apache real**: comprobarlo con `curl -u x:<token> https://tudominio/0/` (debe dar 200).
- **Cachés compartidas (CDN, proxy inverso):** las respuestas del propietario, que dependen de quién pregunta, llevan `Cache-Control: private, no-store` y `Vary: Authorization`. La caché no debe quitarlas ni guardar esas respuestas. Lo que se le niega a un visitante es, en cambio, un 404 **sin cabeceras propias**, igual al de una página que no existe: una cabecera que solo tuviera el 404 de lo privado delataría qué ids existen. Si la caché añade o cambia cabeceras, tiene que hacerlo igual en los dos.
- **Fuerza bruta:** un token de 32 bytes aleatorios no es adivinable. El único freno es el limitador global (100 peticiones por minuto y por IP, `RateLimiter`).

## Lo que esto NO cubre

Esto hace privados los **objetos** que ya se presentaban como privados, oculta a las personas que podrían estar vivas y a las que solo figuran en colecciones privadas. No es una garantía de privacidad de los datos de todas las personas. Antes de afirmar en público algo sobre cuidado de datos, hay que tener presente:

- **El catálogo de personas es compartido.** Una persona puede estar en varias colecciones, de varios usuarios. Basta que una sea pública para que cualquiera la vea, y esa persona se ve en todas las páginas aunque también esté en un borrador privado. Quien guarda una persona en su colección privada no puede impedir que otra colección pública la incluya. Una persona que no está en ninguna colección también se ve.
- **La protección de personas vivas es una deducción, no una certeza.** Cubre a quien tiene fecha de nacimiento reciente y ninguna defunción, o la marca `viva`. No cubre a quien vive pero no tiene fechas ni marca, ni a quien nació hace más de 110 años y vive. Tampoco cubre texto libre: si alguien escribe el nombre de una persona viva en la descripción de un suceso que no la tiene como participante, o en el título de un registro sin sucesos, se verá.
- **La persona oculta se reconoce por su ausencia.** Su página existe y dice «Persona viva» o «Persona privada», y sus padres ya muestran que tienen un descendiente oculto. Eso revela la forma del árbol, no a quién pertenece.
- **El coste de decidir quién vive crece con el catálogo, salvo en la búsqueda de personas.** Decidirlo son varias consultas por persona. La búsqueda (`/b/10/…`) lo decide solo para las filas que alcanza (lee las personas en el orden del índice `personas_orden_idx` hasta juntar la página), así que su coste ya no depende del tamaño del catálogo: con 80.000 personas, 11 ms en SQLite y 16 ms en PostgreSQL, donde antes eran 5,4 s y 5,7 s. Las listas de un lugar y la búsqueda de sucesos (en PostgreSQL, según el tamaño de las tablas) siguen calculándolo para todas las filas. Con millones de personas convendrá guardar el resultado en una columna calculada; hoy no hace falta.
- **Un solo propietario y un solo secreto.** No hay cuentas por persona ni roles. Quien tenga el token lo ve todo, incluidas las colecciones y los pedidos de otros usuarios de la base de datos. Las páginas de cuenta actúan siempre sobre la cuenta fija (`DEFAULT_USER_ID`).
- **Un pedido del POS lo ven el propietario y la sesión que lo hizo.** Al confirmar la compra (`/p/3/`), el código de la orden queda en esa sesión; si se pierde la sesión (otro navegador, cookies borradas), quien compró ya no la puede abrir: el enlace solo no basta, a propósito. Como no hay cuentas, la orden queda a nombre de la cuenta fija (`DEFAULT_USER_ID`), así que en `/0/2/` el propietario ve todas las órdenes.

## Pruebas

- `tests/App/Support/AccessPolicyTest.php`: lectura de credenciales (Basic y Bearer), comparación del token, caducidad de la sesión y las dos formas de negar.
- `tests/App/Support/GedcomExporterTest.php`: fecha completa, solo el año y lugar.
- `tests/App/Support/PrivacidadTest.php`: el umbral, que el SQL del propietario sea el de siempre y que un alias inválido se rechace antes de llegar al SQL.
- `tests/App/Repositories/PrivacidadRepositoriesTest.php` (en SQLite y PostgreSQL, con fecha fija): quién se oculta y quién no, el borde exacto de los 110 años, la marca `viva`, el rol de cada participante (firmar una defunción no es morir), el año que deja un suceso oculto y qué ve un visitante en cada repositorio.
- `tests/App/Integration/ResponseHeadersTest.php`: contra el servidor integrado de PHP, porque `PagesTest` no ve cabeceras (en la CLI `header()` no hace nada): que lo oculto responda con el mismo estado, cuerpo y cabeceras que lo que no existe, que las páginas del propietario salgan `private, no-store` y que el área de cuenta pida credenciales.
- `tests/App/Integration/PagesTest.php` también cubre la página de la marca de vida: cerrada a visitantes, formulario y enlace solo para el propietario, y guardar con token CSRF y valores válidos (cada prueba que guarda deja la base como estaba).
- `tests/App/Repositories/BusquedaDePersonasTest.php` (en SQLite y PostgreSQL): que la búsqueda de personas devuelva exactamente las visibles, en su orden y con el nombre real, para cualquier texto, límite y desplazamiento; que dé las mismas filas que la consulta de antes (la que pasaba por `personas()` para todo el catálogo); y, en SQLite, que una página no lea el catálogo entero (se comprobó que falla con la consulta de antes).
- `tests/App/Repositories/PrivadasRepositoriesTest.php` (en SQLite y PostgreSQL): quién es privado (una colección pública basta, ninguna colección no lo es), qué se oculta de él en cada repositorio y que el propietario lo vea todo.
- `tests/App/Integration/PagesTest.php`: cada URL privada, sin token configurado, con token y sin credenciales, con credenciales equivocadas, como propietario y por sesión; que una colección oculta sea idéntica a una inexistente; que rotar o quitar el token cierre las sesiones; y un recorrido exhaustivo de todas las páginas de la demostración como visitante, que no puede mostrar nada de Carlos, de Lucía ni de Manuel, que está solo en una colección privada (se comprobó que falla si se apaga el enmascarado).

## Texto que escriban los usuarios

Hoy la aplicación no tiene formularios de genealogía: los datos entran por
semillas e importaciones. El día que se pueda escribir (descripciones, notas,
nombres de colecciones, importaciones GEDCOM), **todo texto libre tiene que
pasar por `defamatory-content-review` antes de publicarse**, y lo que quede en
`review` no se muestra hasta que alguien lo apruebe. El porqué, y el resto de
lo que falta, está en [`../../PRIVACIDAD-Y-HONRA.md`](../../PRIVACIDAD-Y-HONRA.md).

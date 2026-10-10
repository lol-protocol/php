# Acceso: qué es privado y quién puede verlo

El proyecto no tiene cuentas de usuario (es un addon de un solo operador), así que hay **una sola identidad con privilegios: el propietario**. Se identifica con un secreto compartido, `OWNER_TOKEN`. Todo lo que es privado exige ser el propietario; el resto es público.

La lógica está en [`Support/AccessPolicy.php`](../Support/AccessPolicy.php) y los controladores la usan a través de tres métodos de `BaseController`: `soloPropietario()`, `pedirPropietario()` y `exigirPropietario()`.

## Qué es privado y cómo responde

| Recurso | Quien no es el propietario recibe |
|---|---|
| Colección con `publica = false`: `/{id}/`, `/{id}/1/`, `/{id}/3/` (GEDCOM) | **404**, idéntico al de un id que no existe |
| Pedidos del POS: `/order/{id}/` y sus acciones | **404**, igual |
| Página de edición de una colección: `/{id}/2/` | **404** si la colección es privada; **401** si es pública |
| Área de cuenta, genealogía y POS: `/0/` y sus acciones | **401** (el navegador pide la clave); **404** si no hay `OWNER_TOKEN` |
| Búsqueda de colecciones (`/?t=7`) | las privadas no aparecen, ni buscándolas por su nombre exacto |

**Por qué 404 y no 401 o 403.** Los ids son URLs públicas y casi secuenciales: un 401 o un 403 confirmaría cuáles existen. Es lo que la sección «Recursos privados» de `README_URLS.md` ya prometía y el código no cumplía. Lo único que responde 401 es lo que se sabe que existe: el área de cuenta y la edición de una colección pública. El área de cuenta es además el punto de entrada del propietario.

Las páginas públicas no piden nada: una colección con `publica = true`, las personas, los lugares, los grupos, las organizaciones, los sucesos, los registros y todo el catálogo del POS.

## Personas que podrían estar vivas

Quien no es el propietario no ve lo que se sabe de las personas que podrían estar vivas. Una persona se **presume viva** cuando:

- no tiene ningún suceso de defunción, **y**
- su nacimiento tiene fecha y fue hace menos de **110 años** (`Privacidad::ANIOS_VIVA`).

Una persona sin ninguna fecha **no** se presume viva: nada lo dice, y ocultar a todos los antepasados sin fechas vaciaría el catálogo. Una fecha de nacimiento a exactamente 110 años ya cuenta como antigua.

**Corregir la deducción.** La columna `personas.viva` (migración `002_personas_viva.sql`) manda sobre las fechas: `TRUE` = vive, `FALSE` = ya falleció, `NULL` = deducirlo. Sirve para marcar a quien vive pero no tiene fechas, o a quien ya murió sin que conste su defunción. Hoy se cambia por SQL o al importar datos; la aplicación no tiene pantalla para editarla.

| Dónde aparece | Qué ve un visitante |
|---|---|
| La persona (`/{id}/` y sus acciones) | La página existe, para que un enlace desde un árbol no se rompa, pero solo dice «Persona viva»: sin nombre, sexo, apellido, fechas, lugar, familiares ni sucesos. Sus padres también la identificarían |
| Árboles (colección, ascendencia, descendencia, vínculos) | Aparece como «Persona viva» en su lugar, para que el árbol conserve su forma; va después de los demás y ordenada por id, para que el orden no diga nada de ella |
| Búsqueda | **No aparece**, y no se puede encontrar por su nombre ni por «persona viva»: si se la encontrara aunque solo se viera la máscara, cualquiera podría comprobar si existe alguien con ese nombre |
| Apellidos (grupos) | No cuenta, no sale en la red del apellido y no pesa en los lugares de nacimiento |
| Lugares, miembros de organizaciones | No aparece |
| Sucesos | Un suceso con **algún** participante vivo no existe para el visitante (404, ni en búsquedas ni en listas ni en la cronología de nadie), porque su fecha, lugar o descripción lo delatarían |
| Registros | Un registro que documenta un suceso así tampoco existe: su título y su fuente suelen nombrar a la persona |
| GEDCOM | La persona sale como «Persona viva», sin sexo, fechas ni lugar; la forma del árbol se conserva y todos los punteros siguen resolviendo |

El propietario lo ve todo y sus consultas son las de siempre.

Cómo está hecho: `Privacidad` entrega **expresiones de tabla** (`personas()`, `sucesos()`, `registros()`) que sustituyen a las tablas en cada consulta, así que una consulta no puede olvidarse de ocultar algo: solo ve la versión ya oculta. Los repositorios reciben el `Privacidad` de la petición y, si nadie se lo pasa, usan el del visitante: olvidarse oculta de más, nunca de menos. `PagesTest::testNoPageShowsAVisitorAnythingAboutLivingPeople` recorre todas las páginas que la base de demostración puede producir y falla si una consulta o página nueva olvida a `Privacidad`.

## Configurar

1. Generar el secreto: `openssl rand -hex 32`.
2. Ponerlo en `.env` (o en el entorno del servidor) como `OWNER_TOKEN=...`.

**Falla cerrado.** Sin `OWNER_TOKEN`, o con uno de menos de 20 caracteres, nadie es el propietario y todo lo privado queda cerrado para todos. Olvidar configurarlo nunca abre nada; el log lo avisa con un *warning* cada vez que alguien abre `/0/`, que es el punto de entrada del propietario.

## Entrar como propietario

- **Navegador:** abrir `/0/`. El navegador pide usuario y clave: el usuario da igual y la clave es `OWNER_TOKEN`. Al aceptarla se abre una sesión (con un id de sesión nuevo) y desde ahí se navega por las páginas privadas sin volver a escribirla.
- **Otros clientes (`curl`, scripts):** `Authorization: Bearer <token>`, o `curl -u x:<token> ...`. Cada petición con las credenciales correctas vale por sí sola.

La sesión del propietario caduca a la hora sin actividad y, en cualquier caso, a las 12 horas. Está ligada al token: **cambiar `OWNER_TOKEN` cierra todas las sesiones abiertas**; quitarlo también. No hay una página de «salir»: cerrar el navegador termina la sesión.

## Despliegue

- **HTTPS, obligatorio en producción.** El token viaja en la cabecera `Authorization`. Sin HTTPS lo ve cualquiera en la red.
- **Nginx:** pasa la cabecera a PHP-FPM por defecto; no hace falta nada. Ver [`deploy/nginx.conf.example`](../deploy/nginx.conf.example).
- **Apache:** `public/.htaccess` incluye la regla que entrega la cabecera a PHP (CGI/FPM la descartan). **No se probó en un Apache real**: comprobarlo con `curl -u x:<token> https://tudominio/0/` (debe dar 200).
- **Cachés compartidas (CDN, proxy inverso):** las respuestas que dependen de quién pregunta llevan `Cache-Control: private, no-store` y `Vary: Authorization`. La caché no debe quitarlas ni guardar esas respuestas.
- **Fuerza bruta:** un token de 32 bytes aleatorios no es adivinable. El único freno es el limitador global (100 peticiones por minuto y por IP, `RateLimiter`).

## Lo que esto NO cubre

Esto hace privados los **objetos** que ya se presentaban como privados y oculta a las personas que podrían estar vivas. No es una garantía de privacidad de los datos de todas las personas. Antes de afirmar en público algo sobre cuidado de datos, hay que tener presente:

- **Las personas fallecidas son un catálogo compartido.** Una persona puede estar en varias colecciones y cualquiera puede abrir `/{id de persona}/` (y sus acciones), encontrarla en la búsqueda o verla en las páginas de lugares, grupos, organizaciones y sucesos. Que una colección sea privada oculta **la colección** (su página, su árbol y su GEDCOM), no a las personas fallecidas que contiene. Si hace falta que un árbol privado oculte también a sus personas, es otra decisión de modelo: hoy no ocurre.
- **La protección de personas vivas es una deducción, no una certeza.** Cubre a quien tiene fecha de nacimiento reciente y ninguna defunción, o la marca `viva`. No cubre a quien vive pero no tiene fechas ni marca, ni a quien nació hace más de 110 años y vive. Tampoco cubre texto libre: si alguien escribe el nombre de una persona viva en la descripción de un suceso que no la tiene como participante, o en el título de un registro sin sucesos, se verá.
- **La persona oculta se reconoce por su ausencia.** Su página existe y dice «Persona viva», y sus padres ya muestran que tienen un descendiente oculto. Eso revela la forma del árbol, no a quién pertenece.
- **El coste crece con el catálogo.** Decidir quién vive son varias consultas por persona. Con millones de personas convendrá guardar el resultado en una columna calculada; hoy no hace falta.
- **Un solo propietario y un solo secreto.** No hay cuentas por persona ni roles. Quien tenga el token lo ve todo, incluidas las colecciones y los pedidos de otros usuarios de la base de datos. Las páginas de cuenta actúan siempre sobre la cuenta fija (`DEFAULT_USER_ID`).
- **Los pedidos del POS son solo del propietario.** Las páginas de pago (`/checkout/...`) todavía no crean pedidos. Cuando lo hagan, un pedido tendrá que ligarse a la sesión de quien lo hizo y abrirse también para esa sesión; hoy, ni quien lo hizo lo vería.

## Pruebas

- `tests/App/Support/AccessPolicyTest.php`: lectura de credenciales (Basic y Bearer), comparación del token, caducidad de la sesión y las dos formas de negar.
- `tests/App/Support/PrivacidadTest.php`: el umbral, que el SQL del propietario sea el de siempre y que un alias inválido se rechace antes de llegar al SQL.
- `tests/App/Repositories/PrivacidadRepositoriesTest.php` (en SQLite y PostgreSQL, con fecha fija): quién se oculta y quién no, el borde exacto de los 110 años, la marca `viva`, y qué ve un visitante en cada repositorio.
- `tests/App/Integration/PagesTest.php`: cada URL privada, sin token configurado, con token y sin credenciales, con credenciales equivocadas, como propietario y por sesión; que una colección oculta sea idéntica a una inexistente; que rotar o quitar el token cierre las sesiones; y un recorrido exhaustivo de todas las páginas de la demostración como visitante, que no puede mostrar nada de Carlos ni de Lucía (se comprobó que falla si se apaga el enmascarado).

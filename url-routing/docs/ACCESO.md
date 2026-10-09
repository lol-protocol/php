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

Este cambio hace privados los **objetos** que ya se presentaban como privados. No es una garantía de privacidad de las personas. Antes de afirmar en público algo sobre cuidado de datos, hay que tener presente:

- **Las personas son un catálogo compartido.** Una persona puede estar en varias colecciones y cualquiera puede abrir `/{id de persona}/` (y sus acciones), encontrarla en la búsqueda o verla en las páginas de lugares, grupos, organizaciones y sucesos. Que una colección sea privada oculta **la colección** (su página, su árbol y su GEDCOM), no a las personas que contiene: si una de ellas figura en otro lugar del catálogo, sigue siendo visible.
- **No hay protección de personas vivas.** Nombres, fechas y lugares de personas que viven hoy se muestran igual que los de quienes murieron hace siglos.
- **Un solo propietario y un solo secreto.** No hay cuentas por persona ni roles. Quien tenga el token lo ve todo, incluidas las colecciones y los pedidos de otros usuarios de la base de datos. Las páginas de cuenta actúan siempre sobre la cuenta fija (`DEFAULT_USER_ID`).
- **Los pedidos del POS son solo del propietario.** Las páginas de pago (`/checkout/...`) todavía no crean pedidos. Cuando lo hagan, un pedido tendrá que ligarse a la sesión de quien lo hizo y abrirse también para esa sesión; hoy, ni quien lo hizo lo vería.

## Pruebas

- `tests/App/Support/AccessPolicyTest.php`: lectura de credenciales (Basic y Bearer), comparación del token, caducidad de la sesión y las dos formas de negar.
- `tests/App/Integration/PagesTest.php`: cada URL privada, sin token configurado, con token y sin credenciales, con credenciales equivocadas, como propietario y por sesión; que una colección oculta sea idéntica a una inexistente; que rotar o quitar el token cierre las sesiones.

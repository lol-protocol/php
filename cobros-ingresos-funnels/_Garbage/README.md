# _Garbage

Código sacado de la app a pedido explícito ("omite todo lo que tiene que ver
con login y recuperar contraseña"), movido acá en vez de borrado: si hace
falta reactivarlo, está completo y con su estructura de carpetas intacta.

**No es parte de la app activa.** Nada bajo `src/`, `views/` ni `tests/`
(fuera de esta carpeta) lo referencia, no corre en CI, no lo cubre
`phpstan.neon` (sus `paths` apuntan a `src`/`public`/`database`/`tests`, y esta
carpeta es hermana de esos, no está adentro) ni `phpunit.xml` (sus
`<directory>` apuntan a subcarpetas de `tests/`, no a `_Garbage/tests/`).

## Qué hay

- `src/Auth.php` — login/logout, sesión, `Auth::requerir()` (el guard que
  envolvía cada página protegida en `public/index.php`), bloqueo por fuerza
  bruta.
- `src/Controllers/LoginController.php` — pantalla de login.
- `src/Controllers/UsuarioController.php` — alta, cambio de contraseña y
  revocar/reactivar acceso de usuarios del sistema.
- `src/Repositories/IntentoLoginRepository.php` — contador de intentos
  fallidos por email para el bloqueo temporal.
- `src/Repositories/UsuarioSistemaRepository.php` — CRUD de `usuarios_sistema`.
- `views/login.php`, `views/usuarios/*.php` — sus pantallas.
- `tests/Integration/AuthTest.php`, `IntentoLoginRepositoryTest.php`,
  `UsuarioSistemaRepositoryTest.php` — sus tests.

## Qué se tocó en el resto de la app para poder sacar esto

- `public/index.php`: sin las rutas `login`/`logout`/`usuarios*`, y sin el
  `Auth::requerir()` que exigía sesión iniciada — todas las páginas quedan
  públicas.
- `views/layout.php`: sin el link a "Usuarios" ni el bloque de cuenta/Salir.
- `src/Csrf.php`: antes dependía de que `Auth::iniciar()` arrancara la sesión
  de PHP; ahora arranca la suya propia (misma cookie endurecida) la primera
  vez que se pide un token. La protección CSRF en sí no tiene que ver con
  login (ya lo decía su propio comentario) y sigue activa en cada POST.
- `AuditoriaRepository::auditarComoUsuarioActual()`: ya no lee
  `Auth::usuarioActual()` (no existe más "el usuario logueado"); cada entrada
  nueva queda atribuida a `usuario_id = NULL` ("Sistema" en el listado). Las
  entradas viejas, con su `usuario_id` real, no se tocaron.
- `database/seed.php`: ya no crea filas en `usuarios_sistema`.

## Qué NO se tocó

Las tablas `usuarios_sistema` e `intentos_login` **siguen en el esquema**
(migración `001_esquema_inicial.sql`): sacar código es reversible con mover
estos archivos de vuelta; borrar esas tablas no lo sería tan fácil, porque
`auditoria.usuario_id` referencia `usuarios_sistema(id)` y perdería la
atribución real de auditorías viejas. Quedan vacías de acá en adelante (nada
las escribe), sin romper nada: `AuditoriaRepository::listado()` sigue
haciendo `LEFT JOIN usuarios_sistema` para las filas históricas.

Si en algún momento se decide que esto no vuelve nunca, lo que sigue es una
migración `004_...sql` que dropee ambas tablas (y la columna o el `LEFT JOIN`
en `AuditoriaRepository` si `usuarios_sistema` desaparece del todo).

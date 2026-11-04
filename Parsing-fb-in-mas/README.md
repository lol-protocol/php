# Parsing-fb-in-mas

Analizadores de **páginas de perfil ya guardadas** de Facebook (`fb`), LinkedIn (`in`) y
Twitter/X, y de cualquier sitio que declare una `Person` de schema.org (`más`), para
tener un punto de partida al buscar a una persona en un proyecto de genealogía.
PHP 8.3, sin dependencias.

## Lo que hace y lo que no

- **Lee archivos HTML que ya tienes en disco.** No descarga páginas, no inicia sesión, no
  sigue enlaces ni recorre listas de contactos: `bin/analizar.php` rechaza una URL a propósito.
  Las redes prohíben en sus términos la extracción automática, y los datos de personas
  son justo lo que `PRIVACIDAD-Y-HONRA.md` (raíz) manda cuidar.
- **Devuelve poco:** red, URL, usuario, nombre, titular, lugar, descripción (hasta 500
  caracteres) y fecha de captura. Sin correo, teléfono, fecha de nacimiento, contactos
  ni publicaciones; aunque la página los traiga, no salen (hay pruebas).
- **Todo perfil sale con `posiblemente_vivo` y `revision_humana` en verdadero.** Un perfil
  de red social casi siempre es de alguien vivo: no lo publiques ni lo mezcles con un
  árbol sin que una persona lo revise y sin base legal escrita
  (preguntas 2 y 3 de `PREGUNTAS-PARA-ABOGADO.md`, y `CUMPLIMIENTO-LEY-21719.md`).
- **Cita siempre la fuente:** la URL y la fecha de captura (`--capturado=AAAA-MM-DD`)
  van en cada registro; si faltan, sale un aviso.
- **No lee la estructura de la página**, solo lo que el sitio pone para que otros lo lean:
  etiquetas `<meta>` (Open Graph, Twitter Cards), `<link rel="canonical">` y JSON-LD.
  Eso cambia mucho menos que las clases CSS, pero tiene un límite: si la red pide iniciar
  sesión para ver el perfil, la página guardada no trae nada y el resultado sale sin
  nombre y con un aviso. Facebook y LinkedIn lo hacen con la mayoría de los perfiles
  personales; funciona sobre todo con páginas públicas.

## Uso

```
php bin/analizar.php --capturado=2026-11-04 muestras/perfil1.html perfil2.html
```

Escribe un JSON con un objeto por página; sale con código 1 si alguna no se pudo leer
(los motivos van a stderr). Pruebas: `php pruebas/ejecutar.php`. Las muestras de
`pruebas/muestras/` son inventadas, de personas que no existen.

## Cómo agregar una red

Una clase en `src/` que extienda `Analizador` (`red()`, `dominios()` y `extraer()`),
registrada en `Detector::analizadores()` y en `src/cargar.php`, con su muestra en
`pruebas/muestras/` y sus comprobaciones en `pruebas/ejecutar.php`. Instagram, Mastodon
o cualquier otra que ponga Open Graph se hace igual que `Twitter.php`.

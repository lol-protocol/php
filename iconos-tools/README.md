# Generador y validador de los sets de iconos

Genera y valida los tres sets de iconos SVG 2.5D del repositorio
(`iconos-genealogia/`, `iconos-biblioteca/` e `iconos-pos/`, ver sus propios
README para qué iconos tiene cada uno y cómo usarlos) y la galería compartida
`galeria-iconos.html`, en la raíz del repositorio.

## Archivos

- `genealogia.py` / `biblioteca.py` / `pos.py`: definen cada icono una sola vez
  (forma, color, variantes).
- `lib.py` / `kit.py`: piezas comunes a los sets (primitivas SVG, paleta,
  composición, y `catalog()`, el registro de iconos que usa cada módulo de set).
- `comunes.py`: los iconos que se llaman igual en los dos sets (`ui_profile`,
  `ui_menu`, `action_filter`...), definidos una vez y que los dos sets incluyen,
  para que no se separen.
- `catalogo.py`: lo que comparten el generador y el validador (variantes,
  prefijos que admite cada set y etiqueta de cada prefijo en la galería). Los
  grupos de cada set salen en la galería en el orden de sus prefijos.
- `build.py`: escribe los tres sets y `galeria-iconos.html` a partir de las
  definiciones. Genera y comprueba todo (nombres repetidos, prefijos sin
  etiqueta o que el set no admite, y que `build.SETS` y `catalogo.PREFIXES`
  listen los mismos sets) antes de borrar nada: si falla, los sets quedan
  como estaban.
- `validate.py`: comprueba las reglas de abajo.
- `similares.py`: busca iconos casi indistinguibles a 24 px dentro de un
  mismo set.
- `requirements.txt`: dependencias de `similares.py` (no las necesitan
  `build.py` ni `validate.py`).
- `galeria.template.html`: plantilla que `build.py` rellena para generar
  `galeria-iconos.html`.

## Regenerar y validar

Los SVG de los tres sets son salida generada — nunca se editan a mano. Después
de cambiar `genealogia.py`, `biblioteca.py`, `pos.py`, `lib.py` o `kit.py`:

```bash
python3 iconos-tools/build.py      # escribe los tres sets y galeria-iconos.html
python3 iconos-tools/validate.py   # comprueba las reglas de abajo

pip install -r iconos-tools/requirements.txt   # solo para similares.py
python3 iconos-tools/similares.py  # busca iconos casi indistinguibles a 24 px
```

Hace falta Python 3.10 o superior (la CI usa 3.13).

`validate.py` comprueba que cada SVG esté bien formado, mida 64x64 y no
contenga texto; que las tres variantes (`color/`, `lineas/`, `gris/`) tengan
los mismos archivos y formas; que `gris/` no tenga colores cromáticos y
`lineas/` no tenga rellenos; que los nombres sigan la convención
`<prefijo>_<descripcion>` y estén en la carpeta de su prefijo; que no haya
ids repetidos (tampoco entre variantes) ni iconos idénticos; que cada `url(#id)` apunte a un id definido
y todo id lo use algo; que ningún trazo, círculo ni rectángulo sin girar quede
a menos de 1 unidad del borde del lienzo (cuenta también lo que un arco abulta
entre sus extremos); y que todo prefijo de `catalogo.PREFIXES` tenga etiqueta
en `catalogo.GROUPS`; y que un nombre que esté en los dos sets sea idéntico en
ambos (si no, va en `comunes.py`). En `lineas/`, además, cada clase `ic-<tono>`
debe tener su regla del `<style>` y esa regla debe volver al mismo color que el
atributo `stroke` (si no, un navegador y `resvg` dibujarían colores distintos).
Y comprueba lo que se desfasa fuera de los SVG: que los números de cada
README de set (iconos, archivos y tabla por prefijo) y los de su fila del
README raíz sean los de los iconos que hay, y que `iconos.yml` corra y
compare cada set de `catalogo.PREFIXES`.

`similares.py` dibuja la variante `gris` de cada icono a 24x24 y compara
todos los pares de un mismo set; falla si dos se diferencian en menos de 1,2
(diferencia media por píxel, de 0 a 255; unos tres píxeles totalmente
distintos de los 576). Muestra además los pares más cercanos, aunque no
fallen. Si un par debe quedar por debajo a propósito, se anota con su motivo
en `ACEPTADOS` (hoy está vacío).

## Cómo se dibuja el volumen

Cada sólido es su cara frontal más una pared extruida hacia abajo y a la derecha (`LIGHT` en `lib.py`).
La pared se dibuja con las mismas rectas y arcos de la forma (`exact_sweep`), no con un polígono que
la aproxime: se ve igual de suave a 16 px que a 512 px. Seis detalles:

- El degradado de la cara va de arriba a la izquierda a abajo a la derecha. En
  una forma girada (los rombos) se gira de vuelta (`light_axis`), porque si no
  arrastraba el giro y quedaba iluminada de arriba a abajo; cada giro distinto
  lleva su propio degradado (`grad_<icono>_<tono>_r45`). Los de `gris` terminan en
  `_g`, para que ningún id se repita entre variantes.
- El borde trasero de la pared se adelanta `INSET` (0,3) a lo largo de la extrusión para que quede
  tapado por la cara frontal. Sin eso asoma una línea gris fina en el borde antialiasado de la cara.
  En `lineas` no se aplica: ahí la pared es un trazo y su borde trasero coincide con el de la cara.
- En `gris`, dos volúmenes que se tocan y son de tonos distintos se separan al menos `GRAY_GAP` (20 de 255):
  los tonos de la paleta se reparten bien en color pero siete caen en 11 niveles de gris, y sin esto
  `book_index` o `ui_collection` quedaban como un solo bloque. Cede el volumen de delante
  (`_gray_shifts`); su degradado lleva el desplazamiento en el id (`..._g_p15`).
- En `lineas`, todo trazo normal pesa entre `LINE_MIN` (1,8, el contorno de un volumen) y `LINE_MAX` (2,2): antes
  los enlaces pesaban 2,6 y destacaban más que los objetos que unen. Las barras a propósito (`BAR`: el vínculo
  de sangre, `action_connect`) conservan su grosor. Un enlace doble de color y gris pesa 1,6, no 1,61.
- En `lineas`, el color de cada trazo se puede sobrescribir con `--ic-<tono>` (ver los README de los sets). Cada
  trazo lleva `class="ic-<tono>"` y el color de siempre como atributo; el `<style>` del icono pone
  `stroke: var(--ic-<tono>, <color>)` solo para los tonos que usa. Va dentro de `@supports (--a:0)` porque un
  renderizador sin variables CSS (`resvg`, que usa `similares.py`) dejaría los trazos sin dibujar con una regla `var()`
  que no sabe resolver: con el `@supports` ignora el bloque y usa el atributo, y el dibujo sale igual que antes.
- Las coordenadas llevan dos decimales (`fmt`). Con uno, el redondeo de los extremos de un arco
  de 1,5 de radio se notaba al ampliar.

## Añadir un set

1. Un módulo `<set>.py` con `ICONS, reg, new = catalog()` (ver `pos.py`) y sus iconos.
2. Sus prefijos en `catalogo.PREFIXES` y la etiqueta de cada uno en `catalogo.GROUPS`.
3. El set en `build.SETS`, con el mismo orden y la misma carpeta que en `PREFIXES`.
4. La carpeta en `.github/workflows/iconos.yml`: en los filtros de rutas de `push` y de
   `pull_request`, y en el `git status` que detecta lo que `build.py` cambia.
5. El `README.md` del set, su fila en el README raíz y su carpeta en la tabla de `CLAUDE.md`.

`build.py` y `validate.py` fallan si faltan el 2, el 3, el 4 o los números del 5.

## CI

`.github/workflows/iconos.yml` ejecuta los tres pasos (`build.py`,
`validate.py`, `similares.py`) en cada cambio a `iconos-genealogia/`,
`iconos-biblioteca/`, `iconos-pos/`, `iconos-tools/` o `galeria-iconos.html`, y falla si los
SVG o la galería versionados no coinciden con lo que genera `build.py` (un
archivo editado a mano o un generador cambiado sin regenerar).

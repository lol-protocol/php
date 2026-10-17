# Generador y validador de los sets de iconos

Genera y valida los dos sets de iconos SVG 2.5D del repositorio
(`iconos-genealogia/` y `iconos-biblioteca/`, ver sus propios README para
qué iconos tiene cada uno y cómo usarlos) y la galería compartida
`galeria-iconos.html`, en la raíz del repositorio.

## Archivos

- `genealogia.py` / `biblioteca.py`: definen cada icono una sola vez (forma,
  color, variantes).
- `lib.py` / `kit.py`: piezas comunes a ambos sets (primitivas SVG, paleta,
  composición).
- `build.py`: escribe los dos sets y `galeria-iconos.html` a partir de las
  definiciones.
- `validate.py`: comprueba las reglas de abajo.
- `similares.py`: busca iconos casi indistinguibles a 24 px dentro de un
  mismo set.
- `requirements.txt`: dependencias de `similares.py` (no las necesitan
  `build.py` ni `validate.py`).
- `galeria.template.html`: plantilla que `build.py` rellena para generar
  `galeria-iconos.html`.

## Regenerar y validar

Los SVG de ambos sets son salida generada — nunca se editan a mano. Después
de cambiar `genealogia.py`, `biblioteca.py`, `lib.py` o `kit.py`:

```bash
python3 iconos-tools/build.py      # escribe los dos sets y galeria-iconos.html
python3 iconos-tools/validate.py   # comprueba las reglas de abajo

pip install -r iconos-tools/requirements.txt   # solo para similares.py
python3 iconos-tools/similares.py  # busca iconos casi indistinguibles a 24 px
```

`validate.py` comprueba que cada SVG esté bien formado, mida 64x64 y no
contenga texto; que las tres variantes (`color/`, `lineas/`, `gris/`) tengan
los mismos archivos y formas; que `gris/` no tenga colores cromáticos y
`lineas/` no tenga rellenos; que los nombres sigan la convención
`<prefijo>_<descripcion>` y estén en la carpeta de su prefijo; que no haya
ids repetidos ni iconos idénticos.

`similares.py` dibuja la variante `gris` de cada icono a 24x24 y compara
todos los pares de un mismo set; falla si dos se diferencian en menos de 0,8
(diferencia media por píxel, de 0 a 255; unos dos píxeles totalmente
distintos de los 576). Muestra además los pares más cercanos, aunque no
fallen. Si un par debe quedar por debajo a propósito, se anota con su motivo
en `ACEPTADOS` (hoy está vacío).

## Cómo se dibuja el volumen

Cada sólido es su cara frontal más una pared extruida hacia abajo y a la derecha (`LIGHT` en `lib.py`).
La pared se dibuja con las mismas rectas y arcos de la forma (`exact_sweep`), no con un polígono que
la aproxime: se ve igual de suave a 16 px que a 512 px. Dos detalles:

- El borde trasero de la pared se adelanta `INSET` (0,3) a lo largo de la extrusión para que quede
  tapado por la cara frontal. Sin eso asoma una línea gris fina en el borde antialiasado de la cara.
  En `lineas` no se aplica: ahí la pared es un trazo y su borde trasero coincide con el de la cara.
- Las coordenadas llevan dos decimales (`fmt`). Con uno, el redondeo de los extremos de un arco
  de 1,5 de radio se notaba al ampliar.

## CI

`.github/workflows/iconos.yml` ejecuta los tres pasos (`build.py`,
`validate.py`, `similares.py`) en cada cambio a `iconos-genealogia/`,
`iconos-biblioteca/`, `iconos-tools/` o `galeria-iconos.html`, y falla si los
SVG o la galería versionados no coinciden con lo que genera `build.py` (un
archivo editado a mano o un generador cambiado sin regenerar).

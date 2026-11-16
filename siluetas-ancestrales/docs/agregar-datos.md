# Cómo agregar datos al catálogo

Guía para agregar un tinte, una prenda, un atuendo o una etnia, o para corregir lo que hay. Todo el catálogo son archivos de `src/datos/` escritos con unos ayudantes compactos (`src/datos/ayuda.js`) y vigilados por pruebas: **si te equivocas, una prueba falla y dice dónde**.

El flujo es siempre el mismo:

1. Edita el archivo de datos (los ejemplos de abajo van en un archivo nuevo, para no chocar con otros cambios: `src/datos/atuendos-<tema>.js` y se suma a la lista de `atuendos.js`).
2. `node --test 'pruebas/*.test.js'` y lee el primer error: nombra la prenda, el atuendo o la etnia y qué valor no existe.
3. `node bin/construir.js` regenera `dist/` y `ejemplos/`; commitea lo que cambie.
4. Mira el resultado: `node bin/generar.js --atuendo mi-atuendo -o prueba.svg` y ábrelo.

> **Antes de agregar un pueblo o un traje**, decide con qué respaldo. Si no tienes una fuente, ponle `conf: 'baja'` y una `nota` que diga qué no reproduces. **No inventes una `fuente`**: déjala vacía. La confianza `alta` exige `fuente` (lo comprueba una prueba).

## 1. Un tinte (`src/datos/colores.js`)

```js
T('verde-musgo-oscuro', 'Verde musgo oscuro', '#3b4a2c', 'verde', 'liquen y tanino', 2, 1000, 3000),
//  id (ASCII, minúsculas y guiones)  nombre   hex       familia  origen            costo 1-5  desde  hasta
```

- `desde` y `hasta` son años; sin ellos el tinte existió siempre. Si un traje pide un tinte que no existía en su año, el generador usa el más parecido de su **familia** (`'rojo'`, `'azul'`, `'verde'`…) que sí existía y lo avisa en `persona.avisos`. Por eso la `familia` importa.
- Pruebas: ids únicos, `hex` válido, `desde < hasta`, `costo` de 1 a 5.

## 2. Una prenda (`src/datos/prendas*.js`)

```js
P('chaleco-tejido', 'Chaleco tejido a mano', 'sobre', 'chaqueta', { largo: 'cintura', manga: 'sin', botones: 5 }),
//  id            nombre                     ranura  plantilla  parámetros de la plantilla
```

- **Ranura:** `torso`, `inferior`, `sobre`, `capa`, `faja`, `cuello`, `calzado` o `accesorio` (define el orden en que se dibuja).
- **Plantilla y parámetros:** hay 16 plantillas (`tunica`, `pantalon`, `falda`, `taparrabo`, `capa`, `poncho`, `drapeado`, `chaqueta`, `delantal`, `faja`, `bandolera`, `chal`, `cuello`, `calzado`, `accesorio` y `mono`; un chaleco es una `chaqueta` con `manga: 'sin'`). Cada una lee solo ciertos parámetros y admite ciertos valores. La lista completa está en `src/tipos-de-ropa.js` (`PARAMS` y `TIPOS`). Un parámetro mal escrito (`manga: 'larg0'`) o un valor que no existe falla con «¿quisiste decir «larga»?».
- **Colores:** por defecto cada papel toma una letra de la paleta del atuendo (`A` principal, `B` secundario, `C` ribete, `D` detalle). Para cambiarlo: `P(..., {}, { principal: 'B', ribete: 'D' })`.
- **Colores propios con su probabilidad:** agrega el id de la prenda a un grupo de `src/datos/colores-prendas.js` (`GRUPOS`, con su perfil de colores y porcentajes); si no, tomará el color del atuendo. `node bin/generar.js --colores chaleco-tejido` muestra el catálogo de colores de una prenda.
- Pruebas: ranura y plantilla existen, parámetros válidos, y **toda prenda se usa en algún atuendo** (no puede quedar huérfana).
- Ve cómo queda sobre un niño, un adulto y un anciano abriendo `prendas.html` (o `node bin/generar.js --vestir chaleco-tejido --edad 8`).

## 3. Un atuendo (`src/datos/atuendos*.js`)

```js
A('sierra-hombre', 'Campesino de sierra', 'andes', [1700, 1950], 'm', 'baja',
  'camisa-lino-larga chaleco-tejido pantalon-recto cinturon-cuero sandalias-cuero',
  { A: 'lana-parda umbra', B: 'crudo', C: 'rojo-cochinilla', D: 'cobre' },
  { peinados: 'corto', barbas: 'bigote ninguna', tocados: 'gorro-lana', pieles: 'canela bronce', conf: 'baja', nota: 'Representación genérica de…' }),
```

- **Región** (`REGIONES`, en `atuendos.js`), **época** `[desde, hasta]` en años (negativos = antes de nuestra era), **sexo** `'f'` o `'m'`, **clase** `'baja'`, `'media'` o `'alta'`.
- **Prendas:** ids separados por espacios. Debe haber **una de `calzado`** (aunque sea `descalzo`) y al menos dos prendas.
- **Paleta:** una lista de tintes por letra; el generador elige uno por letra y por persona. Debe existir la letra `A`. Elige tintes que existieran en la época del traje.
- **Extras:** `peinados`, `barbas`, `tocados` (ids de `src/cabeza.js`; `ninguna`/`ninguno` valen), `pieles` (ids de `PIELES`), `conf`, `nota` y `fuente`.
- **El nombre no se repite** dentro del mismo sexo, y el `id` va en ASCII.
- Hay una pareja de atuendos (mujer y hombre) por pueblo: ver abajo.

## 4. Una etnia (`src/datos/etnias*.js`)

```js
E('sierra', 'Pueblo de la sierra', {
  grupo: 'Pueblos andinos', regiones: 'andes', prefijos: 'sierra',
  pieles: 'canela bronce cobre', cabellos: OSC, peinados: 'corto trenzas-dos', barba: 0.1,
  altura: [150, 162],                 // [mujer, hombre] en cm, adultos
  conf: 'baja', nota: 'Qué no se reproduce…',
}),
```

- **`prefijos`** liga la etnia con sus trajes: los atuendos cuyo `id` empieza con alguno de ellos son *propios* de ese pueblo (`sierra-hombre`, `sierra-mujer`). Sin `prefijos`, la etnia usa **cualquier** atuendo de sus `regiones` (más genérico). Cuida que el prefijo no se cuele en el `id` de otro pueblo (por ejemplo `sami` no debe ser prefijo de `samoano-…`).
- `cabellos` es una lista `[id, peso]` (hay repartos hechos en `ayuda.js`: `OSC`, `MORENO`, `CLARO`, `MED`, `MESTIZO`), `corpulencia` otra (por defecto, delgada 1, media 4, robusta 1,5) y `barba` la frecuencia de 0 a 1.
- Pruebas: regiones, pieles, cabellos y peinados existen; la estatura es plausible; la etnia tiene atuendos y **de mujer y de hombre**.
- Lo estimado por etnia son **promedios aproximados de poblaciones, no reglas sobre una persona**. Dilo en la `nota` cuando haga falta.

## 5. Un país en el informe de cobertura (`datos/paises-regiones.json`)

`node bin/cobertura.js` cruza `Listado-paises/priorizacion.csv` con el catálogo. Cada país del CSV debe estar en `datos/paises-regiones.json` con las **regiones** y **etnias** que mejor lo representan (un mapeo aproximado, no un dato demográfico). Si agregas un pueblo, súmalo a los países donde vive y mira cómo cambia el semáforo. Si Listado-paises suma un país, una prueba pide agregarlo aquí.

## Errores frecuentes

| Falla… | Porque… |
|---|---|
| `ids repetidos` o `id con caracteres raros` | el `id` repite otro o lleva mayúsculas, tildes o espacios |
| `<atuendo>: prenda <id>` | la prenda no existe (o está mal escrita) |
| `<atuendo>: sin calzado (aunque sea descalzo)` | falta una prenda de ranura `calzado` |
| `<atuendo>: tinte <id>` | un tinte de la paleta no existe |
| `la plantilla no lee el parámetro «x»` / `no es un valor válido` | parámetro o valor de ropa mal escrito: mira `src/tipos-de-ropa.js` |
| `<etnia>: sin atuendos` o `sin atuendo f`/`m` | los `prefijos` no coinciden con ningún atuendo, o falta el de un sexo |
| `<atuendo> también cae en <otra etnia>` | el prefijo de una etnia es prefijo del `id` de otra |
| `toda prenda … se usa en algún atuendo` | hay una prenda huérfana: úsala en un atuendo |
| `confianza alta sin fuente` | la confianza `alta` exige `fuente` |
| `cada ejemplo … está desactualizado` o `dist/` | falta correr `node bin/construir.js` |

## Lista de verificación

- [ ] `node --test 'pruebas/*.test.js'` pasa.
- [ ] `node bin/construir.js --comprobar` pasa (después de correr `node bin/construir.js` y commitear lo que cambie).
- [ ] Lo nuevo se ve bien (`prendas.html`, `galeria.html` o `node bin/generar.js …`).
- [ ] Lo que no se pudo reproducir está en la `nota`, la confianza es honesta y no hay una `fuente` inventada.

# Contribuir

Esta guía es para quien quiera corregir o ampliar un diccionario de idioma —
en particular, revisar como hablante nativo uno de los 24 diccionarios que
hoy están en nivel `moderate` y que fueron escritos sin ese filtro. También
cubre cómo se verifica automáticamente cualquier cambio.

## Por qué hace falta revisión nativa

Los 24 diccionarios `moderate` (todos menos los 6 `comprehensive`: alemán,
español, francés, inglés, italiano y portugués) se ampliaron con
vocabulario de referencia general, sin que un hablante
nativo de cada idioma confirmara caso por caso que:

- cada término realmente se usa como insulto en ese idioma hoy, y no es
  arcaico, regional o directamente incorrecto;
- la categoría de riesgo (`animal`, `intelectual`, `ordinario`, etc.) es la
  que un hablante nativo asignaría, no una traducción literal de la
  categoría en español;
- la severidad (`low`, `medium`, `high`) refleja cuánto ofende el término
  realmente, no una estimación por analogía con otro idioma;
- ningún término marcado como insulto es también un apellido o nombre
  documentado sin el flag `nameCollision => true` (ver más abajo) — y a la
  inversa, que no falte marcarlo en alguno que sí lo es.

Ese es el trabajo pendiente más importante del proyecto: la cobertura
`comprehensive` de los 6 idiomas revisados no viene de tener más palabras,
sino de haber pasado ese filtro. Sin hablantes nativos, los 24 `moderate`
no pueden subir de nivel de forma responsable, por muchas palabras que se
añadan.

## Cómo revisar un diccionario

1. Abrí `config/languages/<código>.php` (por ejemplo `config/languages/pol.php`
   para polaco). El `código` es ISO 639-3, tres letras — está también en
   `meta.code` dentro del propio archivo.
2. Cada entrada es:
   ```php
   ['word' => 'suka', 'riskType' => 'animal', 'severity' => 'high'],
   ```
   Revisá `word` (¿es realmente así, con la ortografía correcta?),
   `riskType` (debe ser uno de los 11 definidos en
   `config/risk-categories.php`: `animal`, `intelectual`, `discapacidad`,
   `fisico`, `moral`, `genero`, `ordinario`, `burlesco`, `etnico`,
   `religioso`, `fonetico`) y `severity` (`low`, `medium` o `high`).
3. Si el término también es o podría ser un nombre o apellido real,
   agregá `'nameCollision' => true`:
   ```php
   ['word' => 'cerda', 'riskType' => 'animal', 'severity' => 'medium', 'nameCollision' => true],
   ```
   Esto es lo que hace que `decide()` mande el caso a `review` en vez de
   `reject` automático — en una plataforma genealógica, jamás se debe
   borrar un linaje real solo porque su apellido coincide con un insulto.
   Sin este flag, un término legítimo como apellido quedaría bloqueado sin
   posibilidad de excepción.
4. Corregí, quitá o agregá entradas según haga falta. Un término mal
   clasificado (categoría o severidad equivocada) es tan importante de
   corregir como uno que falta.
5. No dupliques una palabra en dos categorías distintas dentro del mismo
   idioma — el índice interno de `WordList` la indexa por texto normalizado,
   así que la segunda aparición pisa silenciosamente a la primera sin dar
   error. `DictionaryIntegrityTest::testNoLanguageHasTheSameWordInTwoCategories()`
   lo detecta, pero es mejor no depender de eso.

## Subir de nivel de cobertura

`meta.coverage` de cada archivo de idioma es la única fuente de verdad — no
se declara en ningún otro lado (ver el aviso al principio de
`config/languages/supported-languages.php` si hace falta el porqué). Los
niveles y sus mínimos de palabras, verificados por
`DictionaryIntegrityTest::testWordCountMatchesDeclaredCoverageLevel()`:

| Nivel | Mínimo de términos |
|---|---|
| `basic` | 60 |
| `moderate` | 120 |
| `comprehensive` | 200 |

El mínimo de palabras es una condición necesaria, no suficiente: subir
`coverage` a `comprehensive` sólo porque el conteo lo permite, sin haber
hecho la revisión nativa descrita arriba, reintroduce exactamente el
problema que esta guía busca evitar. Subí el nivel únicamente cuando el
diccionario completo — no sólo el conteo — pasó esa revisión.

## Verificar los cambios

```bash
composer install
./vendor/bin/phpunit
```

`DictionaryIntegrityTest` corre automáticamente sobre los 30 idiomas y
falla si:

- el conteo de palabras no alcanza el mínimo del `coverage` declarado;
- el catálogo (`supported-languages.php`) vuelve a declarar `coverage`
  (debe vivir sólo en `meta.coverage` de cada archivo);
- una misma palabra aparece en dos categorías del mismo idioma;
- un `riskType` o `severity` no es uno de los valores válidos;
- un idioma listado en `supported-languages.php` no tiene diccionario, o
  viceversa.

Es la red de seguridad que permite aceptar cambios de idiomas que la
mayoría de quienes revisen el proyecto no hablan: si pasa
`DictionaryIntegrityTest`, la estructura es correcta aunque el contenido
lingüístico sólo lo pueda validar un hablante nativo.

## Añadir un idioma nuevo

Ver la sección "Añadir un idioma" en el `README.md` — cubre el archivo de
diccionario, el registro en el catálogo y las afinidades de familia
lingüística.

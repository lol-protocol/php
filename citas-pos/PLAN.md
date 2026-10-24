# Plan

Cada fase va en su propio PR, en borrador, y se fusiona solo cuando la persona lo decide.

## Fases

| Fase | Qué se entrega | Cuándo está lista |
|---|---|---|
| 0. Decisiones | Respuestas a las preguntas de abajo | Están anotadas en este archivo |
| 1. Corpus | De 20 a 30 citas candidatas, cada una con la ficha de [`CRITERIOS.md`](CRITERIOS.md) y su estado de verificación | Cada cita tiene fuente comprobada, o está marcada `sin-verificar` y no se publica |
| 2. Paleta y datos | La paleta de las 17 categorías (con variante para daltonismo) y `datos/citas.json` con un esquema | El JSON valida contra el esquema y no tiene citas sin fuente; la paleta pasa una comprobación de contraste en modo claro y oscuro |
| 3. Traducciones | Traducción de cada original a los idiomas de la oleada, como borrador | Un hablante nativo firma cada idioma antes de usarlo; hay una guía de revisión como la de `marketing/idiomas/GUIA-REVISION.md` |
| 4. Render | Un script sin dependencias que genera las tarjetas y el texto del correo, con `--comprobar` | `--comprobar` pasa sin dejar cambios sin commitear |
| 5. CI | `pruebas-citas-pos.yml` con filtro de rutas, junto con su viñeta en «CI/CD» del README raíz | Verde en un PR de una sola carpeta |

La fase 1 no necesita código, y es la que más valor tiene y más riesgo quita.

## Decisiones abiertas

Sin estas respuestas no se puede cerrar la fase 1. Entre paréntesis, lo que sugiero.

1. **Idiomas.** ¿Se sigue el orden de las oleadas de `CRITERIOS.md`? (Sí: oleada 1 primero.)
2. **Tono.** ¿Sobrio, como el resto de Contrastocolor, o cercano y lúdico? (Sobrio, con las citas como protagonistas.)
3. **Audiencia.** ¿Docentes, lectores, lingüistas, otra? Cambia el formato (hojas imprimibles para docentes, tarjetas para redes). Es la decisión que más influye en la utilidad.
4. **Canal.** ¿Solo redes, o también el sitio de `url-routing/` y el correo?
5. **Lenguaje del script.** ¿PHP sin dependencias, como `marketing/render.php` y `portadas-svg/`, o Python, como `Listado-colores/`? (PHP, porque es el que ya usa marketing.)
6. **Marca.** ¿Las tarjetas llevan «Contrastocolor» o se decide después (`PREGUNTAS-PARA-ABOGADO.md`, pregunta 30)? (Marcador `[Marca]` en `config.json`, como `marketing/`, para cambiarlo en una sola línea.)
7. **Etiquetas.** ¿Manuales, o de un etiquetador externo? Si es externo, ¿cuál? Hasta que se decida, todas son manuales.

## Cómo se mide si sirve

Antes de producir mucho contenido, una prueba pequeña:

- **6 citas, cada una en dos versiones**: coloreada y plana, con la misma tarjeta y el mismo texto.
- Se publican en las mismas condiciones y se comparan guardados, compartidos y clics al sitio.
- **Si la versión coloreada no gana de forma clara, se replantea la estrategia** antes de seguir con la fase 3 en adelante.

Las métricas concretas dependen de la audiencia (decisión 3) y del canal (decisión 4).

## Riesgos

| Riesgo | Qué hacemos |
|---|---|
| Citas mal atribuidas | Fuente primaria obligatoria; `sin-verificar` no se publica. |
| Derechos de autor | Solo dominio público, edición citada, traducciones propias; comprobación en `copyright-laws/` y consulta antes de publicar con la marca. |
| El etiquetador no existe en este repo | Las etiquetas son manuales y se dice; nada afirma precisión del producto. |
| 17 colores difíciles de distinguir | Series por categoría, variante apta para daltonismo, etiqueta visible además del color. |
| Traducciones con errores | Borrador hasta la revisión nativa; un idioma sin revisor no se publica. |
| Audiencia desconocida | Decisión 3, y la prueba de las 6 citas antes de invertir. |
| Dos sesiones haciendo lo mismo | Mirar PR abiertos y ramas antes de cada fase (`CLAUDE.md`). |

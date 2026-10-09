# Marketing — Genealogía

Textos y recursos gráficos genéricos (sin marca definida) para un proyecto de genealogía.

- [`frases.md`](frases.md): lemas, eslóganes, redes sociales, correo y manifiesto. Empieza con una nota «Antes de usar» (las frases que afirman algo sobre la persona o su árbol están marcadas como condicionales).
- [`idiomas/`](idiomas/): traducciones de la parte madura de `frases.md` a 15 idiomas (borrador sin revisión nativa).
- [`logo/`](logo/): símbolo del árbol genealógico, en color y monocromo (`currentColor`).
- [`imagenes/`](imagenes/): banner para redes sociales (1200×630).
- [`iconos/`](iconos/): 13 íconos de trazo, 24×24, que heredan el color con `currentColor`.

| | | | | | |
|---|---|---|---|---|---|
| <img src="iconos/arbol.svg" width="32"> árbol | <img src="iconos/raiz.svg" width="32"> raíz | <img src="iconos/familia.svg" width="32"> familia | <img src="iconos/documento.svg" width="32"> documento | <img src="iconos/foto.svg" width="32"> foto | <img src="iconos/carta.svg" width="32"> carta |
| <img src="iconos/busqueda.svg" width="32"> búsqueda | <img src="iconos/adn.svg" width="32"> ADN | <img src="iconos/casa.svg" width="32"> casa | <img src="iconos/corazon.svg" width="32"> corazón | <img src="iconos/reloj.svg" width="32"> reloj | <img src="iconos/conexion.svg" width="32"> conexión |
| <img src="iconos/calendario.svg" width="32"> calendario | | | | | |

## Nombre de la marca

El nombre de la marca no está escrito en ninguna frase: los textos llevan el marcador `[Marca]` y el nombre se define una sola vez en [`config.json`](config.json).

```json
{
  "marca": "",
  "marca_por_idioma": {}
}
```

- `marca`: el nombre, obligatorio. Mientras esté vacío, el script falla a propósito para que nadie publique el marcador sin sustituir.
- `marca_por_idioma`: opcional. Sirve para escribir la marca de otra forma en un idioma, por ejemplo transliterada: `{"ja": "…", "ar": "…"}`. Las claves son los códigos de `idiomas/` (`es` para `frases.md`); un código desconocido es un error.

```bash
php marketing/render.php            # genera copias con la marca en marketing/dist/ (ignorado por git)
php marketing/render.php --check    # valida config.json y no escribe nada
```

Los archivos de origen nunca se modifican; el script se niega a escribir sobre ellos. El valor se inserta tal cual, así que si luego lo pegas en HTML, escápalo.

`[Nombre]` no lo toca el script: es por destinatario y lo rellena quien envía cada mensaje.

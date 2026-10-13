# Por qué existe esta librería

## Lo que pasó

Hace años, el autor de este repositorio mantenía una plataforma de genealogía
abierta: cualquiera podía escribir sobre las personas de un árbol. Algunos
usuarios la usaron para publicar mensajes contra una persona de Concepción, a
la que aquí llamamos **R.** R. presentó un reclamo ante la **Universidad de
Concepción**, y el decano citó al autor para hablar del tema. A raíz de ese
reclamo, el proyecto del árbol genealógico se cerró.

Según el autor, años después R. resultó condenado por estafa. Pero cuando se
escribieron esos mensajes R. no tenía cargos: ante la ley era inocente, y la
plataforma estaba siendo usada para difamarlo.

## Lo que se aprendió

1. **Difamar no es sólo insultar.** Los mensajes más dañinos no traen
   palabrotas: «Fulano estafó a mi familia», «fue condenado», «no confíen en
   él». Un filtro de palabras gruesas los deja pasar todos. Por eso el chat
   reconoce las **imputaciones de hechos**: delitos, salud y vida privada (ver
   «Acusaciones» en el README).
2. **Que algo resulte cierto después no lo hacía publicable antes.** La
   plataforma no es un tribunal: no puede saber si una acusación es verdad.
   Por eso esas frases nunca se aprueban ni se bloquean solas: van a
   **revisión humana**, que pide una fuente pública (una sentencia firme, la
   prensa) y mira si la persona vive.
3. **Responde quien opera la plataforma, aunque escriba otro.** El reclamo no
   fue contra quienes escribieron, sino contra quien mantenía el sitio. Por
   eso importan tanto el registro de quién publicó qué, la denuncia y el
   retiro rápido como el filtro (ver [`RIESGOS.md`](RIESGOS.md)).
4. **Un nombre propio junto a una acusación es el riesgo mayor.** Por eso este
   documento no da el apellido completo de R.: el repositorio es público, y
   escribirlo aquí junto a «estafa» sería repetir lo que esta librería existe
   para impedir. Si hace falta conservarlo, que sea fuera del repositorio.

## Cómo se refleja en el código

| Lección | Dónde |
|---|---|
| Imputar hechos también es difamar | patrones `acusación de delito`, `dato sensible (salud)`, `vida privada` y `funa o escrache` de `config/chat-topics/spa.php` y `eng.php`; `AccusationPatternsTest` |
| Una acusación la decide una persona | esos patrones son `medium`: van a `review` y nunca a `reject` ni a `approve` |
| Un insulto disfrazado sigue siendo un insulto | `EvasionCleaner` (letras cirílicas, invisibles, ancho completo, emoji), `MaskedTerms` («p*ta») |
| El idioma no es una puerta trasera | `ModerationEndpoint` revisa siempre también en los idiomas de la plataforma |
| Lo que el filtro no ve, lo ve el proceso | [`RIESGOS.md`](RIESGOS.md): revisión de todo texto libre, denuncia, retiro y registro |

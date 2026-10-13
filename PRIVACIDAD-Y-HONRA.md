# Privacidad, honra y honor: por qué importan en este proyecto

Este repositorio existe para construir una plataforma de genealogía, y una
plataforma de genealogía es, antes que nada, **un lugar donde se escribe sobre
personas**: sobre los vivos y sobre los muertos, sobre sus nombres, sus
familias, sus enfermedades, sus pleitos y sus secretos. Este documento explica
por qué la privacidad y la honra no son un requisito más, sino la condición
para que el proyecto pueda existir.

## La lección que ya nos costó un proyecto

Hace años, el autor de este repositorio tuvo que cerrar una plataforma de
genealogía anterior. Algunos usuarios la usaron para publicar mensajes contra
una persona de Concepción. Esa persona reclamó ante la Universidad de
Concepción y el decano citó al autor a conversar. En ese momento la persona no
tenía cargos: ante la ley era inocente, y la plataforma estaba siendo usada para
difamarla. Que años después haya resultado condenada no cambia nada: la
plataforma no tenía cómo saber si lo que se decía era cierto, ni cómo evitar que
se publicara sin que nadie lo revisara. El detalle está en
[`defamatory-content-review/docs/ORIGEN.md`](defamatory-content-review/docs/ORIGEN.md).

De ahí salen tres certezas que guían todo lo demás:

1. **El daño lo hace un texto, no una palabrota.** «Fulano estafó a mi
   familia» destruye una reputación sin una sola grosería.
2. **La plataforma responde por lo que publica, aunque lo escriba otro.** El
   reclamo no fue contra los autores de los mensajes, sino contra quien
   mantenía el sitio.
3. **Lo que hoy parece cierto no da derecho a publicarlo.** Un sitio de
   genealogía no es un tribunal ni un diario.

## Qué protegemos

| Concepto | Qué es | Ejemplo en genealogía |
|---|---|---|
| **Honra** | La reputación de una persona: cómo la ven los demás. | «Era un ladrón», escrito en la ficha de un abuelo. |
| **Honor** | El aprecio que una persona tiene de sí misma y su dignidad, que un insulto hiere aunque nadie más lo crea. | Un apellido real tratado como insulto: «Cerda», «Bastardo». |
| **Vida privada** | Lo que no es de interés público, aunque sea verdad. | Una infidelidad, un hijo fuera del matrimonio, una adopción, un suicidio. |
| **Datos personales** | Todo dato de una persona identificable. | Nombre, dirección y teléfono de una guía telefónica de 1975. |
| **Datos sensibles** | Los que exponen lo más íntimo: salud, vida sexual, origen étnico, creencias. | «Murió de sida», «era esquizofrénica», «la familia era judía». |
| **Honra de la familia y de los muertos** | Difamar a un difunto daña a sus descendientes vivos. | «Su bisabuelo fue un asesino», leído por sus bisnietos. |

## Por qué en genealogía pesa más

- **Vivos y muertos van en el mismo árbol.** Una ficha de 1890 enlaza con la de
  un nieto que vive hoy. Lo que se dice del abuelo alcanza al nieto.
- **Se hereda lo más íntimo.** Enfermedades, filiación, adopciones, hijos no
  reconocidos y causas de muerte son datos sensibles de varias generaciones a la vez.
- **Los registros históricos traen acusaciones.** Expedientes judiciales,
  crónicas policiales y actas tienen condenas y delitos. Pueden ser ciertos y aun
  así dañar a una familia viva, o pueden ser de **otra persona con el mismo nombre**.
- **Los apellidos son linajes.** Un apellido que coincide con un insulto no es un
  insulto: tratarlo como tal humilla a una familia entera.
- **Es colaborativo y permanente.** Cualquiera escribe sobre el antepasado de
  otro, y lo publicado queda indexado por años.

## Cómo se traduce en el proyecto

| Principio | Dónde está hoy |
|---|---|
| **Un apellido nunca es un insulto por sí solo.** | `defamatory-content-review`: `nameCollision` manda a revisión, nunca rechaza un linaje real. |
| **Nadie es acusado sin fuente.** Imputar delitos, enfermedades o vida privada va siempre a revisión humana. | `defamatory-content-review`: patrones de acusación (`AccusationPatternsTest`). |
| **Un insulto disfrazado sigue siendo un insulto.** | `EvasionCleaner`, `MaskedTerms`, letras sueltas y repetidas, signos. |
| **Las personas vivas son privadas por defecto.** | `url-routing`: `AccessPolicy` y `Privacidad`. Sin defunción y nacida hace menos de 110 años, se muestra como «Persona viva» (ver `url-routing/docs/ACCESO.md`). |
| **Un registro que delata a un vivo tampoco se muestra.** | `url-routing`: sucesos y registros con un participante vivo dan 404. |
| **El filtro propone, una persona decide.** | Las decisiones `review` necesitan una cola y alguien que la atienda. |

## Cabos sueltos

Están identificados y hay que resolverlos antes de abrir la plataforma al público:

1. **`url-routing` todavía no pasa ningún texto por el filtro.** Hoy no hace
   falta, porque no tiene formularios: sus datos entran por semillas e
   importaciones. Pero el día que se pueda escribir (descripciones, notas,
   biografías, pies de foto, nombres de colecciones), **todo** texto libre tiene
   que pasar por `ChatLineReviewer` antes de publicarse, y lo que quede en
   `review` no se muestra hasta que alguien lo apruebe. Lo importado (GEDCOM,
   registros) debería pasar por el mismo filtro.
2. **`phone-directory` guarda nombre, dirección y teléfono de guías de hasta
   1975.** Muchas de esas personas viven. Antes de publicar algo de ahí hay que
   aplicarle la misma regla que en `url-routing`: si podría estar viva, no se
   muestra. Además, enlazar ediciones de distintos años («la misma persona en
   1960 y 1975») arma un perfil que ninguna guía tenía por sí sola.
3. **Textos largos.** El endpoint de moderación acepta hasta 2.000 caracteres:
   una biografía larga hay que revisarla por párrafos.
4. **Denuncia, retiro y registro.** Hace falta un botón para pedir el retiro de
   un contenido sin crear cuenta y un plazo de respuesta (por ejemplo, ocultar
   en 24 horas mientras se revisa). También hace falta un registro de quién
   publicó qué y quién lo revisó. Sin registro no hay cómo responder a un reclamo
   como el que cerró el proyecto anterior.
5. **Términos de uso** que prohíban imputar hechos a terceros y publicar sus datos
   sensibles, y que digan qué pasa con quien lo haga.
6. **Acusaciones en otros idiomas.** Los patrones de acusación están en español
   e inglés. Los otros 31 idiomas sólo detectan insultos.

El mapa completo de lo que cubre el filtro y lo que no está en
[`defamatory-content-review/docs/RIESGOS.md`](defamatory-content-review/docs/RIESGOS.md).

## Marco legal chileno (orientativo)

No es asesoría legal: antes de abrir la plataforma conviene revisarlo con un abogado.

- **Constitución, art. 19 N° 4**: asegura el respeto y la protección de la vida
  privada y de la honra de la persona y de su familia, y la protección de sus
  datos personales.
- **Código Penal, arts. 412 a 431**:
  - La **calumnia** es imputar un delito determinado y falso. Las cometidas por
    escrito y con publicidad, como las de un sitio web, tienen penas mayores.
  - La **injuria** es toda expresión que deshonre, desacredite o menosprecie.
  - La calumnia o injuria contra un difunto puede perseguirla su familia.
  - Hay que confirmar con un abogado el alcance exacto de estos artículos y de la
    prueba de la verdad.
- **Ley 19.628**, sobre protección de la vida privada: regula los datos personales
  y define como **sensibles** los de salud, vida sexual, origen racial, creencias
  y opiniones políticas, entre otros.
- **Ley 21.719**: reemplaza el régimen de la 19.628 con más obligaciones y una
  agencia fiscalizadora. Debería regir desde diciembre de 2026; hay que confirmar
  la fecha y lo que exige a una plataforma con fichas de personas vivas.

## En una frase

Cada ficha de este proyecto es la historia de una familia real. Publicamos lo que
honra esa historia y le sirve a quien la investiga, y nada que pueda herir a
alguien que no está ahí para defenderse.

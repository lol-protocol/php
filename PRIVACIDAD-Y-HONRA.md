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
| **Lo íntimo de un tercero no se publica sin revisión**: orientación sexual, filiación, salud, suicidio, religión, origen étnico, opinión política. | `defamatory-content-review`: patrones de datos sensibles (`SensitiveDataPatternsTest`). |
| **Nadie publica el RUT, el teléfono, el correo ni la dirección de otro.** | `defamatory-content-review`: `PersonalData` (tipo `privado`), en cualquier idioma. |
| **Nada que publique la plataforma escapa al filtro.** | `marcalibros-plataforma-pos-brand-identity/app`: el texto de cada marcalibro (se comparte por enlace) pasa por `LabelPolicy`. |
| **Un insulto disfrazado sigue siendo un insulto.** | `EvasionCleaner`, `MaskedTerms`, letras sueltas y repetidas, signos. |
| **Las personas vivas son privadas por defecto.** | `url-routing`: `AccessPolicy` y `Privacidad`. Sin defunción y nacida hace menos de 110 años, se muestra como «Persona viva»; quien solo figura en colecciones privadas, como «Persona privada» (ver `url-routing/docs/ACCESO.md`). |
| **Un registro que delata a un vivo tampoco se muestra.** | `url-routing`: sucesos y registros con un participante vivo dan 404. |
| **Un teléfono de una guía reciente no se publica.** | `phone-directory`: `Privacy`. La base y el enlazador solo devuelven entradas de guías de hace más de 92 años (en 2026, hasta 1933); una guía sin año conocido se oculta (ver `phone-directory/README.md`). |
| **El filtro propone, una persona decide.** | Las decisiones `review` necesitan una cola y alguien que la atienda. |
| **Lo que promete el marketing se compara con lo que hace el producto.** | [`marketing/VERIFICACION.md`](marketing/VERIFICACION.md): frase por frase, qué respalda este repositorio y qué no. |

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
   1975.** Muchas de esas personas viven. Resuelto en la librería: la base y el
   enlazador solo devuelven entradas de guías de hace más de 92 años (quien
   figura en una guía era adulto entonces, y se presume viva a una persona 110
   años desde su nacimiento), y enlazar ediciones tampoco arma el perfil de
   quien podría vivir (`Privacy`). Quien lee la base como propietario, como lo
   hace la línea de comandos, lo ve todo, así que **nada de ahí se publica sin
   pasar por la vista de visitante**. Falta poder marcar a una persona
   fallecida entrada por entrada para mostrar una guía reciente, y decidir qué
   hacer con las razones sociales que llevan un nombre propio (las empresas no
   se filtran).
3. **Textos largos.** Resuelto: el endpoint de moderación acepta hasta 10.000
   caracteres y revisa por párrafos lo que pasa de 2.000 (`LongText`). Un texto
   más largo que eso hay que mandarlo en varias partes.
4. **Denuncia, retiro y registro.** En `url-routing/`, cada página pública de
   genealogía lleva «Pedir el retiro de esta página» (`/r/{id}/`, sin cuenta).
   El propietario decide en `/0/3/` si la oculta mientras revisa, la retira o
   rechaza el pedido, y cada paso queda registrado con fecha y nota (ver
   [`url-routing/docs/ACCESO.md`](url-routing/docs/ACCESO.md)). En
   `sistema-nuevo/`, las notas del panel pasan por el filtro y cada guardado
   queda en un registro (quién, cuándo y qué decidió el filtro). Falta: avisar al
   propietario de un pedido nuevo y vigilar el plazo de respuesta (por ejemplo,
   ocultar en 24 horas mientras se revisa). Una persona retirada ya no se nombra
   en las demás páginas; un apellido, una organización o una colección retirados
   sí siguen nombrados donde los enlaza una persona.
5. **Términos de uso** que prohíban imputar hechos a terceros y publicar sus datos
   sensibles, y que digan qué pasa con quien lo haga. Hay un borrador en
   [`TERMINOS-DE-USO.md`](TERMINOS-DE-USO.md), con lo que falta decidir marcado
   para revisarlo con un abogado.
6. **Acusaciones en otros idiomas.** Español e inglés tienen los patrones
   completos; portugués, francés, italiano y alemán, los básicos (delito,
   condena, salud, vida privada, orientación sexual), sin revisión de un
   hablante nativo. Los otros 27 idiomas sólo detectan insultos.

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
  agencia fiscalizadora. Rige desde el 1 de diciembre de 2026 según las fuentes
  reunidas en `privacy-laws/countries/cl/`; hay que confirmar con el abogado lo
  que exige a una plataforma con fichas de personas vivas.

## En una frase

Cada ficha de este proyecto es la historia de una familia real. Publicamos lo que
honra esa historia y le sirve a quien la investiga, y nada que pueda herir a
alguien que no está ahí para defenderse.

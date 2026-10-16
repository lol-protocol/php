# Mapa de riesgos: qué protege esta librería y qué no

Un filtro automático baja el riesgo, pero no lo elimina. Este documento dice qué
cubre el código, qué flancos se cerraron y cuáles siguen abiertos, y qué tiene
que hacer la plataforma por fuera del código. El porqué está en
[`ORIGEN.md`](ORIGEN.md), y lo que significa para el proyecto completo (incluidos
`url-routing` y `phone-directory`), en
[`../../PRIVACIDAD-Y-HONRA.md`](../../PRIVACIDAD-Y-HONRA.md).

## Qué cubre hoy

| Capa | Qué detecta | Decisión |
|---|---|---|
| Insultos del diccionario (33 idiomas) | «idiota», «hijo de puta», chilenismos («conchetumare», «aweonao») | `reject` o `review` según la gravedad |
| Evasión | letras sueltas («p u t a»), repetidas («puuuta»), leet («1d10t4»), signos pegados («puta!»), asteriscos («p*ta»), letras cirílicas o griegas camufladas, caracteres invisibles, ancho completo, negrita matemática y emoji en medio de la palabra | igual que el término; los asteriscos van a lo sumo a `review` |
| Acusaciones (español e inglés) | delitos («estafó a», «fue condenado», «es narco»), salud («tiene sida»), vida privada («le pone los cuernos»), orientación sexual, filiación («es hijo ilegítimo»), suicidio, funas | siempre `review`: una persona verifica |
| Datos personales (cualquier idioma) | RUT válido, correo, teléfono, dirección («vive en la calle…») | `review` (tipo `privado`) |
| Amenazas y contenido sexual | «te voy a matar», «mándame nudes» | `reject` o `review` |
| Contexto | animales, discapacidad, identidad: sólo si van dirigidos a alguien («eres un cerdo» sí, «comí cerdo» no) | `review`, o `approve` informado |
| Idioma | el endpoint revisa el idioma pedido y además los de la plataforma (`spa` y `eng` por defecto) | la más grave |

## Flancos cerrados en la revisión de octubre de 2026

| Antes se aprobaba | Ahora |
|---|---|
| «рuta» (p cirílica), «pu​ta» (ancho cero), «ｐｕｔａ», «𝐩𝐮𝐭𝐚», «pu🙂ta», «puta️» | se detectan como «puta» (`UnicodeEvasionTest`) |
| «p*ta», «m*erda», «c*brón» | `review` (`MaskedTermsTest`) |
| «Fulano estafó a mi familia», «fue condenado por…», «tiene sida», «es narco» | `review` (`AccusationPatternsTest`) |
| «conchetumare», «ctm», «weón culiao», «aweonao» | marcados; «hola weón» sigue aprobándose (`ChileanVocabularyTest`) |
| Las 7 entradas de más de 3 palabras («concha de tu madre», «son of a bitch») y las 3 con guion («lèche-cul») | se detectan enteras; ahora un test exige que cada entrada se encuentre a sí misma (`DictionarySelfDetectionTest`) |
| Un insulto en español enviado con `"language": "eng"` | el endpoint lo revisa también en español (`ModerationLanguagesTest`) |
| «p~u~t~a», «p|u|t|a», «p - u - t - a», «p|_|ta», «1d!0t4», «€stúpido» | se detectan (`SymbolEvasionTest`) |
| «nazi», «N.A.Z.I», «N.4.Z.|», «KKK», «ACAB», «sieg heil», «14/88»: no estaban en ningún diccionario | `review` o `reject` en los 33 idiomas; «mi abuelo luchó contra los nazis» se aprueba (`HateTermsTest`) |
| «Elba Gіna» (con «і» cirílica) o un espacio invisible en el apellido: la fusión «el vagina» no se veía | se ve (`UnicodeEvasionTest`) |
| «hijodeputa», «putamadre», «hi jo de pu ta», «pu⏎ta», «p⏎u⏎t⏎a» | se detectan (`LayoutEvasionTest`) |
| «pu<b></b>ta», «pu&#116;a», «🅿🆄🆃🅰», «🇵🇺🇹🇦», texto invertido con U+202E, «ɐʇnd» (cabeza abajo), «İBNE» en turco | se detectan (`LayoutEvasionTest`) |
| «eres una puta \xff» (un byte UTF-8 inválido): ninguna expresión regular encontraba nada | `reject` (`MarkEvasionTest`) |
| «p̶u̶t̶a̶», «p̲u̲t̲a̲», zalgo, «ṗụṭạ», «ᴘᴜᴛᴀ» | se detectan (`MarkEvasionTest`) |
| «su RUT es 12.345.678-5», «fono: 9 8765 4321», «vive en la calle Maipú 123» | `review` (`PersonalDataTest`) |
| «era homosexual», «es hijo ilegítimo», «se suicidó», «le pega a su señora» | `review` (`SensitiveDataPatternsTest`) |
| «t e v o y a m a t a r», «i w i l l k i l l y o u» (amenazas deletreadas) | `reject` (`SpacedRunTermsTest`) |
| «p⃝u⃝t⃝a⃝», «putɑ», «idiօta», «ᏢUTA», «pu+a» | se detectan (`MarkEvasionTest`) |
| «los Pérez son unos ladrones», «tiene antecedentes penales», «está en DICOM», «tiene cáncer», «se acuesta con su secretaria» | `review` (`SensitiveDataPatternsTest`) |
| El texto de un marcalibro («Fulano es un estafador») se publicaba por enlace sin revisar | no se guarda (`LabelPolicyTest` en Marcalibros) |
| «la familia era judía», «Fulano es mapuche», «Fulano es comunista» (religión, origen étnico, opinión política) | `review` (`SensitiveDataPatternsTest`) |
| «careverga», «conchatumadre», «mamaguevo», «comemierda», «eres un gonorrea» | marcados; «el forro del abrigo» y «la gonorrea es una infección» se aprueban |

## Flancos abiertos

Ningún filtro de palabras los cierra del todo; el proceso de la sección
siguiente es lo que los cubre.

- **Acusaciones sin las palabras de la lista**: «se quedó con la herencia de sus
  hermanos», «todos saben lo que hizo en 1985», la ironía y las indirectas.
- **Acusaciones en otros idiomas**: los patrones están en español e inglés. Los
  otros 31 idiomas sólo detectan insultos.
- **Personas identificables sin nombre**: «el decano de Ingeniería», «el
  carnicero de la calle Maipú».
- **Imágenes y archivos**: una foto con texto, un PDF o un audio no pasan por el filtro.
- **Evasión fonética y jerga nueva**: «pvta» (v por u), abreviaturas nuevas,
  palabras inventadas. El diccionario hay que mantenerlo vivo (ver `review/`).
- **Una frase entera pegada con el insulto adentro**: «eresunidiota». Buscar
  insultos dentro de palabras largas marcaría «computadora» por «puta».
- **Dos sílabas separadas por un solo espacio**: «pu ta». Unirlas marcaría
  frases corrientes («la mer de Chine» en francés daría «merde»).
- **«puto»** no está en el diccionario: en España es también un intensificador
  («los putos deberes») y espera la revisión nativa de `review/urgente/spa-genero.csv`.
- **Falsos positivos**: «la película trata de un ladrón de bancos» va a revisión
  y «un asesino de la época» se bloquea. Es el precio de no dejar pasar las acusaciones.
  En genealogía, «fue adoptada», «se suicidó en 1920» o «la familia era judía» también irán a revisión:
  son datos sensibles de una familia, y quien revisa decide si se publican.

## Lo que tiene que hacer la plataforma (fuera del código)

1. **Pasar por la revisión todo texto libre**, no sólo el chat: biografías,
   notas, comentarios, pies de foto, nombres de árboles y de grupos.
2. **Cola de revisión con plazo**: lo que el filtro manda a `review` no se publica
   hasta que alguien lo mire. Para una acusación, quien revisa pide fuente
   pública (sentencia firme, prensa) y comprueba si la persona vive.
3. **Botón de denuncia visible y retiro rápido**: quien se sienta afectado debe
   poder pedir el retiro sin crear cuenta. Conviene comprometer un plazo (por
   ejemplo, ocultar en 24 horas mientras se revisa).
4. **Registro**: quién publicó qué, cuándo, qué decidió el filtro y quién revisó.
   Sin eso no hay cómo responder a un reclamo ni identificar al autor real.
5. **Personas vivas privadas por defecto**: sus fichas no se muestran a quien no
   es el propietario del árbol (ver `url-routing/`) y no se indexan en buscadores.
6. **Términos de uso** que prohíban imputar hechos a terceros y publicar sus
   datos sensibles (salud, vida sexual, antecedentes penales), y que digan qué
   pasa con quien lo haga.

## Marco legal chileno (orientativo)

Esto no es asesoría legal: conviene revisarlo con un abogado antes de abrir la plataforma.

- **Constitución, art. 19 N° 4**: protege la honra y la vida privada de las personas.
- **Código Penal, arts. 412 a 431**: la **calumnia** es imputar un delito
  determinado que es falso y que puede perseguirse de oficio; la **injuria**,
  toda expresión que deshonre o menosprecie. La calumnia es justamente el
  tipo de mensaje que motivó este proyecto.
- **Ley 19.628** (protección de la vida privada): los datos sobre salud y vida
  sexual son **datos sensibles**. Publicarlos sobre un tercero sin su
  consentimiento es un riesgo en sí mismo, aunque sean ciertos.
- **Ley 21.719**, que reemplaza a la 19.628 con más obligaciones y sanciones para
  quien trata datos personales, debería regir desde diciembre de 2026: hay que
  confirmar la fecha y lo que exige a una plataforma con fichas de personas vivas.

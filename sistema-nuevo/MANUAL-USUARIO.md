# Manual de usuario — Backoffice de actividad

Guía rápida para usar el panel. Para instalación y arquitectura, ver `README.md`.

## 1. Ingresar

Abrí el panel en el navegador (`http://localhost:8082` si lo corriste local) e
iniciá sesión con el usuario demo:

- **Usuario:** `admin`
- **Contraseña:** `admin123`

Si la contraseña es incorrecta, el mensaje aparece debajo del botón. Una vez
adentro, arriba a la derecha vas a ver el selector de idioma, "Conectado como
admin" y el botón **Cerrar sesión**.

Por seguridad, si no hacés nada durante 29 minutos aparece un aviso ("Sesión
por expirar") con un minuto para elegir **Continuar activo** o **Cerrar sesión
ahora**; si no respondés, se cierra sola.

## 2. Idioma (ES/EN)

Arriba a la derecha, los botones **ES**/**EN** cambian el idioma de toda la
interfaz al instante, sin recargar la página, y se recuerda la próxima vez que
entrás. Lo que **no** cambia con el idioma: nombres de usuario, nombres de país y
rutas/endpoints del backend — son datos, no texto de la interfaz, igual que un
nombre propio no se traduce. Los nombres en japonés, árabe, hebreo u otros
alfabetos se leen bien elijas el idioma que elijas.

## 3. Panel de KPIs

Apenas entrás, arriba de todo aparecen cuatro tarjetas con un resumen de todo
el sistema (no de un usuario en particular): usuarios totales, acciones
totales, gasto acumulado en USD, y el tipo de acción más frecuente. Sirve para
tener una foto general antes de meterte a mirar un usuario puntual.

## 4. Alertas (panel lateral)

Si hay usuarios con acciones cuya IP no coincide con el país que declararon
(posible VPN/proxy/viaje), o con cambios de país físicamente imposibles (dos
acciones en países distintos en menos tiempo del que tomaría viajar entre
ellos), aparece un panel rojo arriba de todo en la barra lateral apenas
entrás, separado por tipo de alerta y con los más afectados primero — no hace
falta elegir un usuario para verlo. Un clic en cualquiera de la lista lo
selecciona y carga su timeline directamente.

El botón **⚙** del panel abre la configuración: podés apagar cada tipo de
alerta por separado, y mover el control de **sensibilidad** (0-100%) — más
alto detecta cambios de país en ventanas de tiempo más amplias (hasta 4
horas), más bajo solo marca los casi instantáneos (media hora o menos).

## 5. Elegir un usuario

Arriba a la izquierda:

- **Usuario**: escribí un nombre o país en el buscador para filtrar la lista, y
  elegí uno del desplegable. Se carga automáticamente el primero de la lista al
  entrar.

## 6. Elegir contra quién comparar (el "universo") y qué tipo de acción ver

El resto de la barra superior define qué se muestra y contra qué grupo de
usuarios se compara cada acción:

- **Comparar contra**: "Todos los países", un preset (OTAN, BRICS, LATAM,
  países islámicos, Zona Euro, Espacio Schengen) o un país específico.
- **Tipo de acción**: "Todos los tipos" o uno solo (login, pago, búsqueda...) —
  útil para revisar, por ejemplo, únicamente los pagos de un usuario.
- **Edad**: rango mínimo/máximo.
- **Género**: todos, masculino, femenino u otro.

El usuario que estás mirando nunca se compara contra sí mismo. Cambiar
cualquiera de estos filtros recalcula todo el timeline al instante y vuelve a
la primera página.

Si volvés seguido a la misma combinación de filtros, el botón **Guardar** (al
lado de "Filtro guardado") la guarda con un nombre a elección; después se
recupera eligiéndola del desplegable, y **Eliminar** la borra cuando ya no la
necesitás.

## 7. Gráfico de evolución temporal

Arriba del flujo de acciones, un gráfico muestra la cantidad de acciones por
día (barras celestes) y el gasto acumulado en USD (línea dorada) del usuario
elegido, respetando el filtro de tipo de acción. Sirve para ver de un vistazo
si la actividad se concentra en algunos días o es pareja en el tiempo.

## 8. Leer el timeline

Cada acción del usuario aparece como una tarjeta, en orden cronológico, con:

- Un ícono grande a la izquierda, según el tipo de acción.
- **Título** y **fecha/hora** (formato `AAAA-MM-DD HH:MM:SS`, azul).
- **Métricas**, cada una con su color fijo (ver la leyenda "Colores por tipo de
  dato" en la barra lateral):
  - ⏱ **duración** (cian): cuánto tardó esa acción puntual.
  - 📁 **ruta/archivo** (naranja): qué página o endpoint del backend atendió la
    acción. En las llamadas a la API también se ve el código HTTP de respuesta.
  - 💰 **monto** (dorado), solo en pagos/reembolsos: en la moneda local del
    usuario, con el equivalente en USD entre paréntesis.
  - 📎 **tamaño de archivo** (violeta), solo en subidas de archivo.
  - 🌐 **IP** (verde, o rojo si no coincide con el país declarado del
    usuario): dirección IP de esa sesión, el país que indica esa IP, la hora
    local en ese país, y el proveedor. El rojo es una señal de posible
    VPN/proxy o viaje — no necesariamente algo malo, pero vale la pena mirarlo
    (y es lo mismo que resume el panel de alertas).
- **Guías de comparación** (badges verde/rojo/gris) debajo: cuánto más
  rápido/lento o más barato/caro estuvo esa acción respecto al promedio del
  universo elegido arriba. ±10% se considera "dentro del promedio". Al pasar
  el mouse por encima, el badge muestra también la **mediana** y el
  **percentil 90** de ese universo — útil cuando el promedio está distorsionado
  por unos pocos valores muy altos o muy bajos.
- 💬 **Comentario** (magenta), solo en reseñas y algunos tickets de soporte.
- 📝 **Nota**: un campo de texto libre al pie de cada tarjeta para dejar una
  observación propia sobre esa acción puntual (por ejemplo, "revisado, es un
  falso positivo"). Se guarda solo, unos segundos después de dejar de
  escribir — no hace falta ningún botón, y queda ahí la próxima vez que
  entrés.

Al pie del flujo, **‹ Anterior** / **Siguiente ›** pasan de página cuando el
usuario tiene muchas acciones (se muestran 20 por página).

## 9. Cerrar sesión

Botón "Cerrar sesión" arriba a la derecha. Te vuelve a la pantalla de login.

## Preguntas frecuentes

**¿Por qué una acción no tiene guía de comparación ("Sin datos de
comparación")?** El universo elegido (país/edad/género) no tiene ninguna otra
acción de ese mismo tipo para comparar, o el servicio de estadísticas no está
disponible en ese momento (se avisa arriba del timeline).

**¿Por qué el monto en USD no es exactamente el monto local dividido por una
tasa "real"?** Las cotizaciones son fijas e ilustrativas (no de mercado en
vivo) — ver `README.md`.

**¿Los datos son reales?** No, son sintéticos, generados con una semilla fija
para que sean siempre los mismos — pensado para probar el sistema, no
información real de usuarios.

**Cambié el idioma a inglés pero algunos textos siguen en español, ¿está
roto?** No — nombres de usuario, de país y las rutas/endpoints del backend son
datos, no textos de interfaz, así que se muestran igual sin importar el
idioma elegido (ver la sección "Idioma" arriba).

**¿Por qué no veo alertas de "cambios de país imposibles" aunque haya viajes
raros en los datos?** Puede estar apagado en la configuración (⚙ del panel de
alertas), o la sensibilidad puede estar muy baja para ese caso puntual —
subila y probá de nuevo.

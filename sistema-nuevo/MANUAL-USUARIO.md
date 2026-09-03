# Manual de usuario — Backoffice de actividad

Guía rápida para usar el panel. Para instalación y arquitectura, ver `README.md`.

## 1. Ingresar

Abrí el panel en el navegador (`http://localhost:8082` si lo corriste local) e
iniciá sesión con el usuario demo:

- **Usuario:** `admin`
- **Contraseña:** `admin123`

Si la contraseña es incorrecta, el mensaje aparece debajo del botón. Una vez
adentro, arriba a la derecha vas a ver "Conectado como admin" y el botón
**Cerrar sesión**.

## 2. Elegir un usuario

Arriba a la izquierda:

- **Usuario**: escribí un nombre o país en el buscador para filtrar la lista, y
  elegí uno del desplegable. Se carga automáticamente el primero de la lista al
  entrar.

## 3. Elegir contra quién comparar (el "universo")

El resto de la barra superior define contra qué grupo de usuarios se compara
cada acción:

- **Comparar contra**: "Todos los países", un preset (OTAN, BRICS, LATAM,
  países islámicos, Zona Euro, Espacio Schengen) o un país específico.
- **Edad**: rango mínimo/máximo.
- **Género**: todos, masculino, femenino u otro.

El usuario que estás mirando nunca se compara contra sí mismo. Cambiar
cualquiera de estos filtros recalcula todo el timeline al instante.

## 4. Leer el timeline

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
    VPN/proxy o viaje — no necesariamente algo malo, pero vale la pena mirarlo.
- **Guías de comparación** (badges verde/rojo/gris) debajo: cuánto más
  rápido/lento o más barato/caro estuvo esa acción respecto al promedio del
  universo elegido arriba. ±10% se considera "dentro del promedio".
- 💬 **Comentario** (magenta), solo en reseñas y algunos tickets de soporte.

## 5. Cerrar sesión

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

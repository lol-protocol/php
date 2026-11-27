# Contrato para quien consume los interruptores

Para una aplicación que no está en este repositorio (la de genealogía y POS, o cualquier otra) y no quiere reimplementar las reglas. Hay dos formas de leer el estado; la primera es la recomendada.

## 1. El archivo de estado efectivo (cualquier lenguaje)

`php bin/estado.php --salida=/ruta/estado-efectivo.json` escribe, de forma atómica, el estado ya calculado. Se corre tras cada cambio (o desde un cron cada minuto) y la aplicación solo lee el JSON:

```json
{
  "version": 1,
  "generado": "2026-11-20T15:00:00Z",
  "interruptores": {
    "gen.vistas": {"activo": true, "al_apagar": "ocultar"},
    "gen.vistas:abanico": {"activo": false, "al_apagar": "ocultar"},
    "gen.pasarela:stripe": {"activo": true, "al_apagar": "no_disponible"}
  }
}
```

- La clave es la **referencia**: el id de una función (`gen.vistas`) o de una de sus opciones (`gen.vistas:abanico`). Los ids están en [`FUNCIONES.md`](FUNCIONES.md).
- `activo` ya es el estado **efectivo**: una opción solo está activa si su función también lo está. La aplicación no tiene que combinarlos.
- `al_apagar` dice qué mostrar si no está activo: `ocultar` (que no se vea nada) o `no_disponible` (mostrar que existe, pero no está disponible por ahora). Una opción usa la política de su función.
- Las dependencias ya están aplicadas por el panel: no se puede dejar encendida una opción cuyo requisito está apagado.

## 2. La clase PHP

Si la aplicación es PHP, puede cargar `src/Interruptores.php` y llamar `activo($ref)` y `alApagar($ref)` (ver el README, «Usarlo desde un proyecto»). Es lo que hacen `sistema-nuevo/`, `citas-pos/` y `portadas-svg/`.

## Reglas para quien consume

1. **Referencia desconocida:** si la aplicación pregunta por una ref que no está en el archivo, es un error de la aplicación (el catálogo cambió). No se debe tratar como «encendido» sin avisar: registrarlo y decidir por función.
2. **Archivo ilegible o viejo:** la elección es por función. Lo que toca dinero o personas (`gen.pasarela`, `gen.pagos`, `gen.privacidad`, búsquedas y estimaciones) debe **cortarse**; lo cosmético (asistentes, estilo de iconos) puede seguir como estaba. `generado` sirve para detectar un archivo que dejó de actualizarse.
3. **No cachear sin límite:** leer el archivo en cada petición es barato (pesa unos pocos KB); si se cachea, que sea por segundos, o un interruptor apagado seguirá encendido para los usuarios.
4. **Lo apagado se aplica en el servidor**, no solo ocultando un botón: una opción apagada también tiene que rechazar la petición directa.
5. **Cómo se ve lo apagado:** `ocultar` no deja rastro; `no_disponible` muestra un aviso, no un error genérico.
6. **Dependencias que importan a la aplicación:** todo lo que busca, deduce, infiere o estima personas depende de `gen.privacidad`; la opción `gen.pagos:tarjeta` depende de `gen.pasarela:stripe`. Si la aplicación ve `gen.privacidad` apagada, no debe ejecutar ninguna de esas funciones aunque su propio interruptor aparezca encendido por un archivo desactualizado.

## Qué no garantiza

El estado efectivo viene de un archivo: no hay notificación ni empuje. Un cambio llega a la aplicación cuando se regenera el archivo y ella lo vuelve a leer. Con varios servidores, el archivo tiene que estar en un lugar compartido o copiarse a cada uno.

## Escenas y cambios programados

Una escena o un cambio programado no son un canal aparte: terminan cambiando interruptores, y el estado efectivo que ve la aplicación es el de siempre. Lo que sí cambia es **cuándo** se regenera `estado-efectivo.json`: si usas `bin/estado.php --salida=…`, hay que volver a correrlo después de aplicar una escena y de cada `bin/programados.php ejecutar` (o dejarlo en el mismo cron). Una escena de emergencia apaga cientos de interruptores de golpe; una aplicación que lee un archivo viejo seguirá mostrándolos encendidos.

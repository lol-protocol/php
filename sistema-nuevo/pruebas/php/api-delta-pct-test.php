<?php

declare(strict_types=1);

require_once __DIR__ . '/../../servidor-php/codigo/api/ayudantes.php';

// api_delta_pct() es el número que termina en cada badge ("+25% más lento"): cuánto se aparta
// una acción del promedio de su universo, en porcentaje DEL PROMEDIO.
assert_igual(50.0, api_delta_pct(1500, 1000.0), 'delta: 1500 contra un promedio de 1000 es +50%');
assert_igual(-50.0, api_delta_pct(500, 1000.0), 'delta: 500 contra un promedio de 1000 es -50%');
assert_igual(0.0, api_delta_pct(1000, 1000.0), 'delta: igual al promedio es 0%');
assert_igual(-100.0, api_delta_pct(0, 80.5), 'delta: un valor 0 está 100% por debajo del promedio');
assert_igual(300.0, api_delta_pct(200, 50), 'delta: no hay tope hacia arriba (200 contra 50 es +300%)');

// Contra el promedio, no contra el valor: si fuera (valor - promedio) / valor, esto daría 50 y -100.
assert_igual(100.0, api_delta_pct(200, 100), 'delta: 200 contra un promedio de 100 es +100% (se divide por el promedio)');
assert_igual(-50.0, api_delta_pct(100, 200), 'delta: 100 contra un promedio de 200 es -50%, no -100%');

assert_verdadero(is_float(api_delta_pct(10, 5)), 'delta: devuelve float aunque entren enteros');

// Sin un promedio útil no hay comparación (null) y nunca una división por cero.
assert_igual(null, api_delta_pct(100, null), 'delta: sin promedio (servicio caído, o ninguna acción del universo trae monto) -> null');
assert_igual(
    null,
    api_delta_pct(100, 0.0),
    'delta: promedio 0 (lo que devuelve el servicio para un universo vacío) -> null, no división por cero'
);
assert_igual(null, api_delta_pct(100, -5.0), 'delta: promedio negativo -> null');

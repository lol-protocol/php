<?php

declare(strict_types=1);

// saneador_marca_temporal: normaliza distintos formatos crudos al canónico Y-m-d H:i:s.
$anclaTexto = '2026-09-03 12:00:00';
$anclaEpoch = gmmktime(12, 0, 0, 9, 3, 2026);

assert_igual($anclaTexto, saneador_marca_temporal('2026-09-03T12:00:00Z'), 'marca_temporal: formato con T/Z');
assert_igual($anclaTexto, saneador_marca_temporal('2026-09-03T12:00:00+00:00'), 'marca_temporal: formato ATOM');
assert_igual($anclaTexto, saneador_marca_temporal($anclaTexto), 'marca_temporal: ya viene en formato canónico');
assert_igual($anclaTexto, saneador_marca_temporal($anclaEpoch), 'marca_temporal: epoch en segundos');
assert_igual($anclaTexto, saneador_marca_temporal($anclaEpoch * 1000), 'marca_temporal: epoch en milisegundos (heurística > 10 mil millones)');
assert_igual(null, saneador_marca_temporal('no es una fecha'), 'marca_temporal: texto no reconocible -> null');
assert_igual(null, saneador_marca_temporal(''), 'marca_temporal: string vacío -> null');
assert_igual(null, saneador_marca_temporal(null), 'marca_temporal: null -> null');

// saneador_duracion_ms: positiva y como máximo 1 hora (3_600_000 ms).
assert_igual(1500, saneador_duracion_ms('1500'), 'duracion_ms: acepta numérico como string');
assert_igual(null, saneador_duracion_ms(-100), 'duracion_ms: negativa -> null');
assert_igual(null, saneador_duracion_ms(0), 'duracion_ms: cero -> null');
assert_igual(null, saneador_duracion_ms(3_600_001), 'duracion_ms: más de 1 hora -> null');
assert_igual(3_600_000, saneador_duracion_ms(3_600_000), 'duracion_ms: exactamente 1 hora es válida');

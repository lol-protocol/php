<?php

declare(strict_types=1);

// saneador_texto: recorta HTML/scripts, colapsa espacios, trunca.
assert_igual('hola mundo', saneador_texto('  hola   mundo  '), 'texto: colapsa espacios y recorta bordes');
assert_igual('alert(1)', saneador_texto('<script>alert(1)</script>'), 'texto: quita etiquetas <script>');
assert_igual('alert(1)', saneador_texto('&lt;script&gt;alert(1)&lt;/script&gt;'), 'texto: decodifica entidades antes de sanear (evita evasión)');
assert_igual(null, saneador_texto('   '), 'texto: solo espacios -> null');
assert_igual(null, saneador_texto(123), 'texto: no-string -> null');
assert_igual('ab', saneador_texto('abcdef', 2), 'texto: trunca al máximo indicado');

// saneador_id_usuario: exactamente "u" + 3 dígitos.
assert_igual('u007', saneador_id_usuario('u007'), 'id_usuario: formato válido');
assert_igual('u007', saneador_id_usuario('  u007  '), 'id_usuario: recorta espacios');
assert_igual(null, saneador_id_usuario('u7'), 'id_usuario: menos de 3 dígitos -> null');
assert_igual(null, saneador_id_usuario('user007'), 'id_usuario: prefijo incorrecto -> null');
assert_igual(null, saneador_id_usuario(7), 'id_usuario: no-string -> null');

// saneador_tipo_accion: solo tipos conocidos, sin distinguir mayúsculas.
assert_igual('login', saneador_tipo_accion('LOGIN'), 'tipo_accion: normaliza a minúsculas');
assert_igual(null, saneador_tipo_accion('vuela'), 'tipo_accion: tipo desconocido -> null');

// saneador_numero: acepta separadores de miles y signo "$", rechaza basura.
assert_igual(1234.56, saneador_numero('$1,234.56'), 'numero: quita "$" y "," de miles');
assert_igual(-5.0, saneador_numero('-5'), 'numero: conserva el signo negativo');
assert_igual(null, saneador_numero('abc'), 'numero: no numérico -> null');
assert_igual(42.0, saneador_numero(42), 'numero: int se convierte a float');

// saneador_moneda: solo códigos de la lista blanca del contexto.
$monedas = ['USD', 'EUR', 'ARS'];
assert_igual('EUR', saneador_moneda('eur', $monedas), 'moneda: normaliza a mayúsculas');
assert_igual(null, saneador_moneda('XXX', $monedas), 'moneda: código fuera de lista -> null');

// saneador_codigo_http: rango 100-599.
assert_igual(404, saneador_codigo_http('404'), 'codigo_http: acepta numérico como string');
assert_igual(null, saneador_codigo_http(999), 'codigo_http: fuera de rango -> null');
assert_igual(null, saneador_codigo_http(0), 'codigo_http: cero -> null');

// saneador_ip: valida formato, tolera ":puerto" colado.
assert_igual('203.45.12.9', saneador_ip('203.45.12.9:54321'), 'ip: quita puerto colado');
assert_igual(null, saneador_ip('no-es-una-ip'), 'ip: formato inválido -> null');
assert_igual('::1', saneador_ip('::1'), 'ip: IPv6 válida');

// saneador_codigo_pais: solo códigos de la lista blanca del contexto.
$paises = ['AR', 'ES', 'US'];
assert_igual('AR', saneador_codigo_pais('ar', $paises), 'codigo_pais: normaliza a mayúsculas');
assert_igual(null, saneador_codigo_pais('ZZ', $paises), 'codigo_pais: código fuera de lista -> null');

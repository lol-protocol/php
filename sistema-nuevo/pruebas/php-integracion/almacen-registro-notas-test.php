<?php

declare(strict_types=1);

/** @var PDO $pdo */
$registro = new AlmacenRegistroNotas($pdo);
$accionId = 'zz' . substr(bin2hex(random_bytes(2)), 0, 4); // no hace falta que exista: el registro no tiene FK

$registro->anotar($accionId, 'admin', 'revisar', ['acusación de delito', 'dato personal (RUT)'], '  estafó a su familia  ');
$registro->anotar($accionId, 'admin', 'borrada', [], '');
$filas = $registro->deAccion($accionId);

assert_igual(2, count($filas), 'registro notas: cada guardado es una fila');
assert_igual('borrada', $filas[0]['decision'], 'registro notas: lo más nuevo primero');
assert_igual('acusación de delito, dato personal (RUT)', $filas[1]['motivos'], 'registro notas: guarda los motivos');
assert_igual(19, (int) $filas[1]['largo'], 'registro notas: el largo es del texto recortado, en caracteres');
assert_igual(hash('sha256', 'estafó a su familia'), $filas[1]['huella'], 'registro notas: la huella es del texto recortado');
$columnas = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'registro_notas'")->fetchAll(PDO::FETCH_COLUMN);
assert_verdadero(!in_array('texto', $columnas, true), 'registro notas: no guarda el texto de la nota');

$pdo->prepare('DELETE FROM registro_notas WHERE accion_id = ?')->execute([$accionId]);

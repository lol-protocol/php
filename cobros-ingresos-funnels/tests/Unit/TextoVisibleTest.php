<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * El texto que ve el usuario (las vistas y los mensajes de los controladores) lleva
 * sus tildes. Se fueron acumulando sin que nadie lo notara: "conversion", "periodo",
 * "pais", "Metodo", "Todavia"... en 22 archivos, con el mismo termino a veces con
 * tilde y a veces sin (periodo / período, valido / válido).
 *
 * Revisa solo texto: el HTML de las vistas y los literales que parecen un mensaje
 * (dos palabras o mas) de las vistas, src/ y los mensajes de database/migrar.php. No
 * mira identificadores, claves ni columnas (los valores guardados como 'organico' o
 * 'Espanol' quedan sin tilde a proposito: son datos), ni una palabra seguida de "("
 * (el metodo Database::transaccion() en un mensaje), ni los datos de ejemplo del
 * seed y del catalogo de paises (database/migraciones/005_catalogo_de_paises_y_monedas.sql:
 * "Belgica", "Ciudad de Mexico", "Suscripcion mensual"), que se escriben sin tildes
 * en todo el conjunto.
 */
final class TextoVisibleTest extends TestCase
{
    /** Palabras que en español siempre llevan tilde. Las ambiguas (que, mas, esta, si...) no estan: se revisan a mano. */
    private const SIN_TILDE = [
        'periodo' => 'período', 'periodos' => 'períodos', 'conversion' => 'conversión', 'emision' => 'emisión',
        'ultimo' => 'último', 'ultimos' => 'últimos', 'ultima' => 'última', 'ultimas' => 'últimas',
        'valido' => 'válido', 'valida' => 'válida', 'validos' => 'válidos', 'invalido' => 'inválido',
        'metodo' => 'método', 'metodos' => 'métodos', 'pais' => 'país', 'paises' => 'países',
        'genero' => 'género', 'generos' => 'géneros', 'dias' => 'días', 'dia' => 'día', 'antiguedad' => 'antigüedad',
        'credito' => 'crédito', 'pagina' => 'página', 'paginas' => 'páginas', 'todavia' => 'todavía',
        'facturacion' => 'facturación', 'segmentacion' => 'segmentación', 'adquisicion' => 'adquisición',
        'unico' => 'único', 'auditoria' => 'auditoría', 'ano' => 'año', 'anos' => 'años',
        'descripcion' => 'descripción', 'accion' => 'acción', 'anulacion' => 'anulación', 'edicion' => 'edición',
        'informacion' => 'información', 'numero' => 'número', 'codigo' => 'código', 'telefono' => 'teléfono',
        'tambien' => 'también', 'aqui' => 'aquí', 'rapido' => 'rápido', 'direccion' => 'dirección',
        'devolucion' => 'devolución', 'transaccion' => 'transacción', 'migracion' => 'migración',
        'suscripcion' => 'suscripción', 'comparacion' => 'comparación', 'historico' => 'histórico',
        'organico' => 'orgánico', 'excepcion' => 'excepción', 'produccion' => 'producción',
    ];

    /** @return list<string> */
    private static function archivos(): array
    {
        $raiz = dirname(__DIR__, 2);
        $archivos = array_merge(
            glob($raiz . '/views/*.php') ?: [],
            glob($raiz . '/views/*/*.php') ?: [],
            glob($raiz . '/src/*.php') ?: [],
            glob($raiz . '/src/Controllers/*.php') ?: [],
            glob($raiz . '/src/Repositories/*.php') ?: [],
            [$raiz . '/database/migrar.php'],
        );
        sort($archivos);

        return $archivos;
    }

    /** @return list<array{int, string}> [linea, texto] de lo que ve el usuario en un archivo */
    private static function textosVisibles(string $archivo): array
    {
        $textos = [];
        foreach (token_get_all((string) file_get_contents($archivo)) as $token) {
            if (!is_array($token)) {
                continue;
            }
            [$id, $contenido, $linea] = $token;
            if ($id === T_INLINE_HTML) {
                preg_match_all('/(?:placeholder|title|aria-label|alt)="([^"]*)"/u', $contenido, $atributos);
                $partes = array_merge([strip_tags($contenido)], $atributos[1]);
            } elseif ($id === T_CONSTANT_ENCAPSED_STRING || $id === T_ENCAPSED_AND_WHITESPACE) {
                $pareceMensaje = preg_match('/[a-záéíóúñ]{3,}\s+[a-záéíóúñ]{2,}/iu', $contenido) === 1;
                $esSql = preg_match('/\b(SELECT|INSERT|UPDATE|DELETE|FROM|WHERE|JOIN|CREATE|ALTER)\b/', $contenido) === 1;
                if (!$pareceMensaje || $esSql) {
                    continue;
                }
                $partes = [$contenido];
            } else {
                continue;
            }
            foreach ($partes as $parte) {
                foreach (preg_split('/\R/u', $parte) ?: [] as $desplazamiento => $renglon) {
                    $textos[] = [$linea + $desplazamiento, $renglon];
                }
            }
        }

        return $textos;
    }

    public function testElTextoQueVeElUsuarioLlevaSusTildes(): void
    {
        $raiz = dirname(__DIR__, 2) . '/';
        $faltan = [];
        foreach (self::archivos() as $archivo) {
            foreach (self::textosVisibles($archivo) as [$linea, $texto]) {
                foreach (self::SIN_TILDE as $sin => $con) {
                    if (preg_match('/(?<![\p{L}_])' . $sin . '(?![\p{L}_(])/iu', $texto) === 1) {
                        $faltan[] = str_replace($raiz, '', $archivo) . ":{$linea}  {$sin} -> {$con}";
                    }
                }
            }
        }

        self::assertSame([], $faltan, "palabras sin tilde en texto visible:\n" . implode("\n", $faltan));
    }

    /** La guarda no sirve si no detecta nada: comprueba que el lector de texto ve las vistas y los mensajes. */
    public function testLaGuardaVeLasVistasYLosMensajes(): void
    {
        $raiz = dirname(__DIR__, 2);

        $vista = array_column(self::textosVisibles($raiz . '/views/auditoria/index.php'), 1);
        self::assertContains('Historial de cambios: ', array_map(static fn (string $t): string => substr($t, 0, 22), $vista));

        $mensajes = implode(' ', array_column(self::textosVisibles($raiz . '/src/Controllers/ClienteController.php'), 1));
        self::assertStringContainsString('Elegí un país válido.', $mensajes);
    }
}

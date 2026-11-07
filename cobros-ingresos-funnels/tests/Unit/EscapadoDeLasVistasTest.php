<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Lo que una vista escribe en el HTML se escapa con e() (src/funciones_de_vista.php) y con nada mas.
 * Eran 122 llamadas a htmlspecialchars() repartidas por las vistas, y una que faltara era un XSS: con
 * una sola funcion, revisar el escapado es buscar las salidas sin e(). Esta guarda impide que vuelva
 * a haber dos formas de hacerlo.
 */
final class EscapadoDeLasVistasTest extends TestCase
{
    /** @return list<string> */
    private static function archivosConHtml(): array
    {
        $raiz = dirname(__DIR__, 2);
        $archivos = array_merge(
            glob($raiz . '/views/*.php') ?: [],
            glob($raiz . '/views/*/*.php') ?: [],
            glob($raiz . '/src/*.php') ?: [],
        );
        sort($archivos);

        return $archivos;
    }

    public function testNingunaVistaNiHelperEscapaConHtmlspecialcharsDirecto(): void
    {
        $llamadas = [];
        foreach (self::archivosConHtml() as $archivo) {
            // Se miran los tokens, no el texto: los comentarios no cuentan (el docblock de e() nombra htmlspecialchars()).
            foreach (token_get_all((string) file_get_contents($archivo)) as $token) {
                if (is_array($token) && $token[0] === T_STRING && strtolower($token[1]) === 'htmlspecialchars') {
                    $clave = basename(dirname($archivo)) . '/' . basename($archivo);
                    $llamadas[$clave] = ($llamadas[$clave] ?? 0) + 1;
                }
            }
        }

        // La unica llamada permitida es la de la propia e().
        self::assertSame(['src/funciones_de_vista.php' => 1], $llamadas, 'usar e() en lugar de htmlspecialchars()');
    }

    public function testEEscapaLoQueSeEscapabaAntes(): void
    {
        $entradas = [
            '<script>alert("x")</script>',
            "O'Brien & \"Hijos\" <b>",
            '&amp; ya escapado',
            'Ñandú, árbol, 日本語',
            '',
            "salto\nde linea",
        ];
        foreach ($entradas as $entrada) {
            self::assertSame(htmlspecialchars($entrada), e($entrada), $entrada);
        }
    }

    public function testEEscapaLasComillasSimplesYDoblesParaQueSirvaEnUnAtributo(): void
    {
        self::assertSame('&quot;&#039;&lt;&gt;&amp;', e("\"'<>&"));
        self::assertSame('<input value="' . e('a" onfocus="x') . '">', '<input value="a&quot; onfocus=&quot;x">');
    }

    public function testENullSaleVacioYLosNumerosSeEscribenComoSiempre(): void
    {
        self::assertSame('', e(null));
        self::assertSame('42', e(42));
        self::assertSame('1.5', e(1.5));
        self::assertSame('0', e(0));
    }

    public function testEReemplazaLosBytesInvalidosEnVezDeDevolverVacio(): void
    {
        // Sin ENT_SUBSTITUTE, htmlspecialchars() devolveria '' para todo el texto si tiene un byte invalido.
        self::assertSame("a\u{FFFD}b", e("a\xffb"));
    }
}

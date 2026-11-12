<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\HostParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HostParserTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('IDIOMAS_ACTIVOS');
        $_SERVER['HTTP_HOST'] = 'tudominio.com';
        $_SERVER['SERVER_PORT'] = '80';
        unset($_SERVER['HTTPS']);
    }

    protected function tearDown(): void
    {
        putenv('IDIOMAS_ACTIVOS');
        unset($_SERVER['HTTP_HOST'], $_SERVER['SERVER_PORT']);
    }

    public function testSinConfiguracionSeSirvenEspanolEIngles(): void
    {
        $this->assertSame(['spa' => 'es', 'eng' => 'en'], HostParser::idiomas());
    }

    public function testLosIdiomasConocidosSonTreintaConCodigosDistintos(): void
    {
        $this->assertCount(30, HostParser::IDIOMAS_CONOCIDOS);
        $this->assertCount(30, array_unique(HostParser::IDIOMAS_CONOCIDOS), 'two languages share an hreflang code');
        foreach (HostParser::IDIOMAS_CONOCIDOS as $subdominio => $hreflang) {
            $this->assertMatchesRegularExpression('/^[a-z]{3}$/', $subdominio);
            $this->assertMatchesRegularExpression('/^[a-z]{2}$/', $hreflang);
        }
    }

    /** @return array<string, array{string, list<string>}> */
    public static function listas(): array
    {
        return [
            'tres idiomas' => ['spa,eng,fra', ['spa', 'eng', 'fra']],
            'espacios y mayúsculas' => [' FRA , Deu ', ['spa', 'fra', 'deu']],
            'el español siempre está, primero' => ['eng,por', ['spa', 'eng', 'por']],
            'sin repetir el español' => ['spa,spa,eng', ['spa', 'eng']],
            'un código que no se conoce no cuenta' => ['eng,xxx,api,es', ['spa', 'eng']],
            'vacío: solo español' => ['', ['spa']],
        ];
    }

    /** @param list<string> $esperados */
    #[DataProvider('listas')]
    public function testIdiomasActivos(string $lista, array $esperados): void
    {
        putenv("IDIOMAS_ACTIVOS={$lista}");

        $this->assertSame($esperados, array_keys(HostParser::idiomas()));
    }

    public function testSoloUnSubdominioActivoEsUnIdioma(): void
    {
        putenv('IDIOMAS_ACTIVOS=spa,fra');

        $this->assertSame('fra', HostParser::getLocale('fra.tudominio.com'));
        $this->assertSame('spa', HostParser::getLocale('eng.tudominio.com'), 'eng is known but not served here');
        $this->assertSame('spa', HostParser::getLocale('deu.tudominio.com'), 'known, not served');
        $this->assertSame('spa', HostParser::getLocale('api.tudominio.com'), 'not a language');
        $this->assertSame('spa', HostParser::getLocale('fra.com'), 'the bare domain is not a subdomain');
    }

    public function testHreflangAnunciaSoloLosIdiomasActivos(): void
    {
        putenv('IDIOMAS_ACTIVOS=spa,eng,fra');

        $this->assertSame([
            'es' => 'http://spa.tudominio.com/6128473105/',
            'en' => 'http://eng.tudominio.com/6128473105/',
            'fr' => 'http://fra.tudominio.com/6128473105/',
            'x-default' => 'http://tudominio.com/6128473105/',
        ], HostParser::alternos('/6128473105/'));
    }

    public function testElAlternoPartedeLaBaseAunqueSeaUnaPaginaEnOtroIdioma(): void
    {
        putenv('IDIOMAS_ACTIVOS=spa,fra');
        $_SERVER['HTTP_HOST'] = 'fra.tudominio.com';

        $this->assertSame('http://spa.tudominio.com/mx/', HostParser::alternos('/mx/')['es']);
        $this->assertSame('http://tudominio.com/mx/', HostParser::alternos('/mx/')['x-default']);
    }

    public function testElIdiomaBaseEsElDelDominioSinSubdominioYElPrimeroDeLosActivos(): void
    {
        putenv('IDIOMAS_ACTIVOS=fra');

        $this->assertSame(HostParser::IDIOMA_BASE, HostParser::getLocale('tudominio.com'));
        $this->assertSame(HostParser::IDIOMA_BASE, array_key_first(HostParser::idiomas()));
        $this->assertArrayHasKey(HostParser::IDIOMA_BASE, HostParser::IDIOMAS_CONOCIDOS);
    }

    public function testSitioIsPosForThePosDomainsAndTheirSubdomainsAndGenealogyForTheRest(): void
    {
        putenv('POS_DOMAINS=tienda.example, otra.test');
        try {
            $this->assertSame('pos', HostParser::sitio('tienda.example'));
            $this->assertSame('pos', HostParser::sitio('eng.tienda.example:8080'), 'a language subdomain and a port');
            $this->assertSame('pos', HostParser::sitio('otra.test'), 'the list is trimmed');
            $this->assertSame('genealogy', HostParser::sitio('conce.com'));
            $this->assertSame('genealogy', HostParser::sitio('notienda.example'), 'a suffix that is not a subdomain');
        } finally {
            putenv('POS_DOMAINS');
        }
        $this->assertSame('pos', HostParser::sitio('contrastocolor.test'), 'the default list');
    }
}

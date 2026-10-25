<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase
{
    private string $archivo;
    private ?Logger $anterior;
    private string|false $logFileAntes;
    private string|false $logLevelAntes;

    protected function setUp(): void
    {
        $this->archivo = sys_get_temp_dir() . '/url-routing-logger-' . getmypid() . '-' . uniqid() . '.log';
        $this->logFileAntes = getenv('LOG_FILE');
        $this->logLevelAntes = getenv('LOG_LEVEL');
        $this->anterior = $this->instancia();
        putenv('LOG_FILE=' . $this->archivo);
    }

    protected function tearDown(): void
    {
        $this->poner($this->anterior);
        $this->restaurar('LOG_FILE', $this->logFileAntes);
        $this->restaurar('LOG_LEVEL', $this->logLevelAntes);
        @unlink($this->archivo);
    }

    private function instancia(): ?Logger
    {
        $propiedad = new \ReflectionProperty(Logger::class, 'instance');

        return $propiedad->getValue();
    }

    private function poner(?Logger $logger): void
    {
        (new \ReflectionProperty(Logger::class, 'instance'))->setValue(null, $logger);
    }

    private function restaurar(string $nombre, string|false $valor): void
    {
        putenv($valor === false ? $nombre : "{$nombre}={$valor}");
    }

    /** A Logger that reads LOG_LEVEL now (it is a singleton: it reads it once, when first built). */
    private function nuevo(?string $nivel): Logger
    {
        putenv($nivel === null ? 'LOG_LEVEL' : "LOG_LEVEL={$nivel}");
        $this->poner(null);

        return Logger::getInstance();
    }

    /** What the Logger wrote, one level name per line, in order. */
    private function niveles(Logger $logger): array
    {
        $logger->flush();
        preg_match_all('/^\[[^\]]+\] \[(\w+)\]/m', (string)@file_get_contents($this->archivo), $m);

        return $m[1];
    }

    private function escribirTodo(Logger $logger): void
    {
        $logger->debug('d');
        $logger->info('i');
        $logger->warning('w');
        $logger->error('e');
        $logger->critical('c');
    }

    /** @return array<string, array{?string, list<string>}> */
    public static function configuraciones(): array
    {
        return [
            'DEBUG lo deja pasar todo' => ['DEBUG', ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL']],
            'INFO descarta DEBUG' => ['INFO', ['INFO', 'WARNING', 'ERROR', 'CRITICAL']],
            'WARNING' => ['WARNING', ['WARNING', 'ERROR', 'CRITICAL']],
            'ERROR' => ['ERROR', ['ERROR', 'CRITICAL']],
            'CRITICAL deja solo lo crítico' => ['CRITICAL', ['CRITICAL']],
            'en minúsculas también vale' => ['error', ['ERROR', 'CRITICAL']],
            'sin LOG_LEVEL es INFO' => [null, ['INFO', 'WARNING', 'ERROR', 'CRITICAL']],
            'vacío es INFO' => ['', ['INFO', 'WARNING', 'ERROR', 'CRITICAL']],
            'un nivel desconocido es INFO, no silencio' => ['VERBOSE', ['INFO', 'WARNING', 'ERROR', 'CRITICAL']],
        ];
    }

    /** @param list<string> $esperados */
    #[DataProvider('configuraciones')]
    public function testLogLevelKeepsTheEntriesAtThatLevelAndAbove(?string $nivel, array $esperados): void
    {
        $logger = $this->nuevo($nivel);

        $this->escribirTodo($logger);

        $this->assertSame($esperados, $this->niveles($logger));
    }

    public function testAnEntryKeepsItsMessageAndContext(): void
    {
        $logger = $this->nuevo('INFO');

        $logger->warning('Algo raro', ['id' => 7]);
        $logger->flush();

        $this->assertMatchesRegularExpression('/\[WARNING\] Algo raro \| \{"id":7\}$/m', (string)file_get_contents($this->archivo));
    }
}

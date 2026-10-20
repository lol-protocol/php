<?php

declare(strict_types=1);

namespace Tests\App\Repositories;

use App\Repositories\Genealogy\PersonaRepository;
use App\Support\Database;
use App\Support\Privacidad;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabases;

/**
 * The persona search (/b/10/...) picks its page on the real table, asking whether each persona may be
 * seen only for the rows it reaches, and masks and describes just those. These tests pin that it still
 * answers exactly what searching through personas() answered, on every engine available, and that on
 * SQLite it no longer looks at the whole catalog to answer.
 *
 * "Today" is 2026-10-09, so a persona born after 1916-10-09 with no death of her own is presumed alive.
 */
class BusquedaDePersonasTest extends TestCase
{
    private const PERSONAS = 60;

    public static function drivers(): array
    {
        return TestDatabases::drivers();
    }

    private static function hoy(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-09');
    }

    /**
     * What is true of persona $i by construction (1..60), written out here and not asked of the code:
     *  - i % 4 == 0: born in 1850, no death: visible;
     *  - i % 4 == 1: born in 1950, no death: presumed alive, hidden;
     *  - i % 4 == 2: born in 1950, with her own death: visible;
     *  - i % 4 == 3: born in 1850, only in a private collection: hidden;
     * and the marks that override the deduction: persona 5 (alive by her birth) is marked dead, persona 8
     * (visible) is marked alive, and persona 7 (private) is also in a public collection, so visible.
     *
     * @return array{nombres: string, apellidos: string, visible: bool}
     */
    private static function persona(int $i): array
    {
        $viva = match (true) {
            $i === 5 => false,
            $i === 8 => true,
            default => $i % 4 === 1,
        };
        $privada = $i % 4 === 3 && $i !== 7;

        return [
            'nombres' => 'Nombre' . str_pad((string)$i, 3, '0', STR_PAD_LEFT),
            'apellidos' => 'Apellido' . str_pad((string)($i % 5 + 1), 2, '0', STR_PAD_LEFT),
            'visible' => !$viva && !$privada,
        ];
    }

    private static function idDe(int $i): int
    {
        return 1_000_000_000 + $i;
    }

    private function catalogo(string $driver): Database
    {
        $db = TestDatabases::fresh($driver, 'genealogy');
        $db->execute("INSERT INTO usuarios (id, email, nombre) VALUES (1, 'u@x.org', 'U')");
        $db->execute("INSERT INTO colecciones (id, nombre, usuario_id, publica) VALUES (1000001, 'Privada', 1, FALSE)");
        $db->execute("INSERT INTO colecciones (id, nombre, usuario_id, publica) VALUES (1000002, 'Publica', 1, TRUE)");

        for ($i = 1; $i <= self::PERSONAS; $i++) {
            $p = self::persona($i);
            $db->execute('INSERT INTO personas (id, nombres, apellidos) VALUES (?, ?, ?)', [self::idDe($i), $p['nombres'], $p['apellidos']]);
            $nacimiento = $i % 4 === 0 || $i % 4 === 3 ? '1850-05-05' : '1950-05-05';
            $db->execute("INSERT INTO sucesos (id, tipo, fecha) VALUES (?, 'nacimiento', ?)", [100_000_000 + $i, $nacimiento]);
            $db->execute("INSERT INTO suceso_participantes (suceso_id, persona_id, rol) VALUES (?, ?, 'principal')", [100_000_000 + $i, self::idDe($i)]);
            if ($i % 4 === 2) {
                $db->execute("INSERT INTO sucesos (id, tipo, fecha) VALUES (?, 'defuncion', '2001-01-01')", [300_000_000 + $i]);
                $db->execute("INSERT INTO suceso_participantes (suceso_id, persona_id, rol) VALUES (?, ?, 'principal')", [300_000_000 + $i, self::idDe($i)]);
            }
            if ($i % 4 === 3) {
                $db->execute('INSERT INTO coleccion_personas (coleccion_id, persona_id) VALUES (1000001, ?)', [self::idDe($i)]);
            }
        }
        $db->execute('INSERT INTO coleccion_personas (coleccion_id, persona_id) VALUES (1000002, ?)', [self::idDe(7)]);
        $db->execute('UPDATE personas SET viva = FALSE WHERE id = ?', [self::idDe(5)]);
        $db->execute('UPDATE personas SET viva = TRUE WHERE id = ?', [self::idDe(8)]);

        return $db;
    }

    /**
     * The ids a search for $texto must give, in order: the visible personas whose name contains it,
     * by apellidos, nombres and id.
     *
     * @return list<int>
     */
    private static function esperados(string $texto): array
    {
        $filas = [];
        for ($i = 1; $i <= self::PERSONAS; $i++) {
            $p = self::persona($i);
            if ($p['visible'] && str_contains(strtolower($p['nombres'] . ' ' . $p['apellidos']), strtolower($texto))) {
                $filas[] = [$p['apellidos'], $p['nombres'], self::idDe($i)];
            }
        }
        usort($filas, static fn(array $a, array $b): int => $a <=> $b);

        return array_column($filas, 2);
    }

    /** @return list<int> */
    private static function ids(array $rows): array
    {
        return array_map(static fn(array $r): int => (int)$r['id'], $rows);
    }

    /** @return array<string, array{string, int, int}> */
    public static function paginas(): array
    {
        $casos = [];
        foreach (['', 'nombre0', 'apellido03', 'NOMBRE01', 'zzz'] as $texto) {
            foreach ([[5, 0], [5, 5], [7, 3], [50, 0], [3, 100], [1, 0]] as [$limite, $desplazamiento]) {
                $casos["'{$texto}' limite {$limite} desde {$desplazamiento}"] = [$texto, $limite, $desplazamiento];
            }
        }

        return $casos;
    }

    #[DataProvider('drivers')]
    public function testElVisitanteVeExactamenteLasPersonasVisiblesYEnSuOrden(string $driver): void
    {
        $db = $this->catalogo($driver);
        $repo = new PersonaRepository($db, Privacidad::publica(self::hoy()));

        foreach (self::paginas() as $nombre => [$texto, $limite, $desplazamiento]) {
            $this->assertSame(
                array_slice(self::esperados($texto), $desplazamiento, $limite),
                self::ids($repo->buscar($texto, $limite, $desplazamiento)),
                "{$driver}: {$nombre}"
            );
        }
    }

    #[DataProvider('drivers')]
    public function testElVisitanteNuncaVeNiElNombreEnmascaradoDeUnaPersonaOculta(string $driver): void
    {
        $repo = new PersonaRepository($this->catalogo($driver), Privacidad::publica(self::hoy()));

        $filas = $repo->buscar('', 100);

        $this->assertNotSame([], $filas);
        foreach ($filas as $fila) {
            $this->assertFalse($fila['oculta']);
            $this->assertNotSame(Privacidad::NOMBRE_OCULTO, $fila['nombres']);
            $this->assertNotSame(Privacidad::NOMBRE_PRIVADO, $fila['nombres']);
            $esperada = self::persona((int)$fila['id'] - 1_000_000_000);
            $this->assertSame($esperada['nombres'], $fila['nombres'], 'the real name, not a masked one');
            $this->assertSame($esperada['apellidos'], $fila['apellidos']);
        }
    }

    #[DataProvider('drivers')]
    public function testElPropietarioVeATodosEnElMismoOrden(string $driver): void
    {
        $repo = new PersonaRepository($this->catalogo($driver), Privacidad::propietario());

        $ids = self::ids($repo->buscar('', 1000));

        $this->assertCount(self::PERSONAS, $ids);
        $this->assertSame(array_slice($ids, 7, 10), self::ids($repo->buscar('', 10, 7)));
    }

    /**
     * The same rows buscar() answered when it went through personas() for the whole catalog: the old
     * query, row by row (with the birth and death dates), against the current one.
     */
    #[DataProvider('drivers')]
    public function testDaLasMismasFilasQueLaConsultaDeAntes(string $driver): void
    {
        $db = $this->catalogo($driver);

        foreach ([Privacidad::publica(self::hoy()), Privacidad::propietario()] as $privacidad) {
            $repo = new PersonaRepository($db, $privacidad);
            foreach (self::paginas() as $nombre => [$texto, $limite, $desplazamiento]) {
                $antes = $db->fetchAll(
                    'SELECT p.id, p.nombres, p.apellidos, p.sexo, p.oculta, '
                        . $privacidad->siVisible($privacidad->sqlEventoDe('p', 'nacimiento', 'fecha')) . ' AS nacimiento, '
                        . $privacidad->siVisible($privacidad->sqlEventoDe('p', 'defuncion', 'fecha')) . " AS defuncion
                       FROM {$privacidad->personas()} p
                      WHERE LOWER(p.nombres || ' ' || p.apellidos) LIKE ? AND NOT p.oculta
                      ORDER BY p.apellidos, p.nombres, p.id LIMIT {$limite} OFFSET {$desplazamiento}",
                    ['%' . strtolower($texto) . '%']
                );
                foreach ($antes as &$fila) {
                    $fila['oculta'] = (bool)$fila['oculta'];
                }
                unset($fila);

                $this->assertSame($antes, $repo->buscar($texto, $limite, $desplazamiento), "{$driver}: {$nombre}");
            }
        }
    }

    /**
     * What this fixes: answering a page meant asking, for every persona of the catalog, whether she may be
     * seen, before sorting and cutting. SQLite calls the TRANSLATE() that ignores accents once per row it
     * looks at, so counting its calls counts the rows a search reads: a page reads about as many as it
     * returns, not the whole table.
     */
    public function testUnaPaginaNoLeeToDoElCatalogo(): void
    {
        $db = TestDatabases::fresh('sqlite', 'genealogy');
        $pdo = $db->pdo();
        $pdo->beginTransaction();
        $persona = $pdo->prepare('INSERT INTO personas (id, nombres, apellidos) VALUES (?, ?, ?)');
        $suceso = $pdo->prepare("INSERT INTO sucesos (id, tipo, fecha) VALUES (?, 'nacimiento', '1850-05-05')");
        $participa = $pdo->prepare("INSERT INTO suceso_participantes (suceso_id, persona_id, rol) VALUES (?, ?, 'principal')");
        $total = 1500;
        for ($i = 1; $i <= $total; $i++) {
            $persona->execute([self::idDe($i), 'Nombre' . str_pad((string)$i, 4, '0', STR_PAD_LEFT), 'Apellido' . str_pad((string)($i % 7), 2, '0', STR_PAD_LEFT)]);
            $suceso->execute([100_000_000 + $i]);
            $participa->execute([100_000_000 + $i, self::idDe($i)]);
        }
        $pdo->commit();

        $llamadas = 0;
        $pdo->sqliteCreateFunction('translate', static function (?string $texto, string $de, string $a) use (&$llamadas): ?string {
            $llamadas++;

            return Database::traducir($texto, $de, $a);
        }, 3);

        foreach ([Privacidad::publica(self::hoy()), Privacidad::propietario()] as $quien => $privacidad) {
            $llamadas = 0;
            $filas = (new PersonaRepository($db, $privacidad))->buscar('', 50);

            $this->assertCount(50, $filas);
            $this->assertLessThan(120, $llamadas, "quien {$quien}: read {$llamadas} rows of {$total} to return 50");
        }
    }
}

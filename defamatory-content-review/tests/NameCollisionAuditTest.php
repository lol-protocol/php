<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Auditoría de `nameCollision` en los diccionarios `moderate`/`basic`: nombres
 * y apellidos reales (o muy plausibles) que coinciden con un término de
 * severidad alta o media. `decide()` nunca debe devolver 'reject' para ellos:
 * en una plataforma genealógica no se borra un linaje por su apellido.
 * Sin revisión de hablante nativo todavía (ver CONTRIBUTING.md).
 */
class NameCollisionAuditTest extends TestCase
{
    private const BY_LANGUAGE = [
        'rus' => ['Козел', 'Хохол', 'Москаль', 'Горбун', 'Гнида', 'Баран', 'Цыган', 'Колдун'],
        'ukr' => ['Горбань', 'Москаль', 'Козел', 'Гнида', 'Циган', 'Баран', 'Заїка', 'Щур'],
        'pol' => ['Garbus', 'Alfons', 'Kutas', 'Murzyn', 'Gnida', 'Dupek', 'Baran', 'Cygan'],
        'ces' => ['Pasák', 'Hrbáč', 'Cikán', 'Rusák', 'Kokot', 'Beran', 'Černoch', 'Šašek'],
        'slk' => ['Kokot', 'Cigán', 'Krivý', 'Baran', 'Žaba'],
        'bul' => ['Турчин'],
        'nld' => ['Os', 'Pot', 'Del', 'Scheel', 'Mank', 'Blind'],
        'swe' => ['Ko', 'Kuk', 'Fan', 'Hora', 'Jude'],
        'dan' => ['So', 'Abe', 'Gal', 'Alfons', 'Pik', 'Luder', 'Ko', 'Orm'],
        'nor' => ['Lam', 'Gal', 'Kuk', 'Ku', 'Orm'],
        'tur' => ['Topal', 'Çolak', 'Kambur', 'Kör', 'Deli', 'Sağır', 'Am', 'Sik', 'Bok', 'Meme', 'Moron', 'Tiran'],
        'ron' => ['Cioară', 'Negru', 'Țigan', 'Prost', 'Bou', 'Vită', 'Porc'],
        'ell' => ['Κουτσός', 'Καμπούρης', 'Μουγγός', 'Τραυλός', 'Κλέφτης', 'Αράπης', 'Γύφτος', 'Τσιγγάνος', 'Εβραίος', 'Τούρκος', 'Αλβανός', 'Νέγρος', 'Νάνος'],
        'hun' => ['Koca', 'Pina', 'Cici', 'Buzi', 'Néma', 'Béna', 'Sánta', 'Ronda', 'Büdös', 'Tolvaj', 'Cigány', 'Zsidó', 'Néger', 'Ördög', 'Pogány'],
        'fin' => ['Peto', 'Mato', 'Sika', 'Akka', 'Varas', 'Pelle', 'Paska', 'Kusi', 'Perse', 'Naida', 'Lutka'],
    ];

    /** @return array<string,array{string,string}> */
    public static function collisions(): array
    {
        $cases = [];
        foreach (self::BY_LANGUAGE as $lang => $names) {
            foreach ($names as $name) {
                $cases["{$lang}: {$name}"] = [$lang, $name];
            }
        }

        return $cases;
    }

    #[DataProvider('collisions')]
    public function testRealNameIsFlaggedButNeverRejected(string $lang, string $name): void
    {
        $reviewer = DefamatoryContentReviewer::create(__DIR__ . '/../config', $lang);
        $result = $reviewer->validateName($name);

        $this->assertFalse($result->isValid(), "'{$name}' ({$lang}) debería seguir detectándose.");
        $this->assertTrue($result->hasNameCollision(), "'{$name}' ({$lang}) debe llevar nameCollision.");
        $this->assertSame('review', $reviewer->decide($result), "'{$name}' ({$lang}) no puede rechazarse en automático.");
    }
}

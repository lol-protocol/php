<?php

namespace DefamatoryContentReview\Review;

/**
 * Convierte los cambios de una ReviewSheet en el texto nuevo de cada archivo
 * de config/, sin escribir nada: `bin/apply-review.php` muestra el plan y sólo
 * escribe con `--apply`. Se niega a aplicar una fila si la planilla es más
 * vieja que la configuración (la entrada ya no está o cambió de severidad), y
 * comprueba que cada archivo resultante siga siendo PHP válido.
 */
final class ReviewApplier
{
    /** @var array<string,string> ruta => texto nuevo */
    public array $files = [];
    /** @var array<int,string> */
    public array $done = [];
    /** @var array<int,string> */
    public array $problems = [];

    public function __construct(ReviewSheet $sheet, private readonly string $configDir, private readonly string $language)
    {
        foreach ($sheet->changes as $change) {
            $this->apply($change);
        }
        foreach ($this->files as $path => $text) {
            $this->checkParses($path, $text);
        }
    }

    /** @param array{action:string,file:string,word:string,value:string,was:string,row:int} $change */
    private function apply(array $change): void
    {
        $path = rtrim($this->configDir, '/') . "/{$change['file']}/{$this->language}.php";
        $text = $this->files[$path] ?? (is_file($path) ? (string) file_get_contents($path) : null);
        $where = "fila {$change['row']} «{$change['word']}» ({$change['file']}/{$this->language}.php)";
        $entry = $text === null ? null : ConfigEditor::entry($text, $change['word']);
        if ($change['was'] !== '' && ($entry === null || !str_contains($entry, "'severity' => '{$change['was']}'"))) {
            $this->problems[] = "{$where}: ya no está con severidad {$change['was']}; la planilla es más vieja que config/, regenérala";
            return;
        }
        $new = $text === null ? null : match ($change['action']) {
            'remove' => ConfigEditor::remove($text, $change['word']),
            'severity' => ConfigEditor::setSeverity($text, $change['word'], $change['value']),
            'flag' => ConfigEditor::addFlag($text, $change['word'], $change['value']),
            'list-add' => ConfigEditor::editList($text, $change['value'], $change['word'], true),
            'list-remove' => ConfigEditor::editList($text, $change['value'], $change['word'], false),
            default => null,
        };
        if ($new === null) {
            $this->problems[] = "{$where}: no se encontró dónde aplicar «{$change['action']}»";
            return;
        }
        $this->files[$path] = $new;
        $this->done[] = "{$where}: " . match ($change['action']) {
            'remove' => 'se quita',
            'severity' => "severidad {$change['value']}",
            'flag' => "marca {$change['value']}",
            'list-add' => "a {$change['value']}",
            default => "fuera de {$change['value']}",
        };
    }

    private function checkParses(string $path, string $text): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'review');
        file_put_contents($tmp, $text);
        try {
            $data = require $tmp;
            if (!is_array($data) || !isset($data['words'])) {
                $this->problems[] = basename(dirname($path)) . '/' . basename($path) . ': el resultado no devuelve un diccionario';
            }
        } catch (\ParseError $e) {
            $this->problems[] = basename(dirname($path)) . '/' . basename($path) . ": el resultado no es PHP válido ({$e->getMessage()})";
        } finally {
            unlink($tmp);
        }
    }
}

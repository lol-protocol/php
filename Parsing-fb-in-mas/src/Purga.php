<?php

declare(strict_types=1);

namespace ParsingFbInMas;

/**
 * Quita de un archivo JSONL ya generado a las personas de una lista de exclusión (ver {@see Exclusiones}).
 * Trabaja línea por línea, sin cargar el archivo en memoria.
 *
 * Una línea que no se puede leer como JSON no se puede comparar con la lista: se conserva tal cual y se
 * cuenta como ilegible, para que quien corre la purga sepa que hay líneas que no pudo revisar.
 */
final class Purga
{
    /**
     * @return array{linea: ?string, parientes: int, ilegible: bool} `linea` es null si la persona se quita
     *         (si no, la línea original, o re-escrita si se le quitaron parientes); `parientes` cuenta los
     *         parientes excluidos que se sacaron de un perfil que se queda
     */
    public static function linea(string $linea, Exclusiones $excluidos): array
    {
        $datos = json_decode($linea, true);
        if (!is_array($datos) || ($datos !== [] && array_is_list($datos))) {
            return ['linea' => $linea, 'parientes' => 0, 'ilegible' => true];
        }
        $perfil = array_filter($datos, 'is_string', ARRAY_FILTER_USE_KEY);
        if ($excluidos->contiene($perfil)) {
            return ['linea' => null, 'parientes' => 0, 'ilegible' => false];
        }

        $parientes = Json::en($perfil, 'parientes');
        $quitados = 0;
        if (is_array($parientes)) {
            $quedan = [];
            foreach ($parientes as $pariente) {
                $id = Json::texto($pariente, 'id');
                $excluido = $id !== null && preg_match('/^Q\d+$/', $id) === 1
                    && $excluidos->contiene(['red' => 'wikidata', 'usuario' => $id, 'url' => 'https://www.wikidata.org/wiki/' . $id]);
                if ($excluido) {
                    $quitados++;
                } else {
                    $quedan[] = $pariente;
                }
            }
            if ($quitados > 0) {
                $perfil['parientes'] = $quedan;
                return ['linea' => (string) json_encode($perfil, Lote::JSON), 'parientes' => $quitados, 'ilegible' => false];
            }
        }
        return ['linea' => $linea, 'parientes' => 0, 'ilegible' => false];
    }
}

<?php

declare(strict_types=1);

namespace ParsingFbInMas;

use RuntimeException;

/**
 * Lee una respuesta JSON de la API pública de WikiTree que ya guardaste (por ejemplo, la de la acción
 * `getProfile`): es más estable que la página HTML. No llama a la API: solo lee el archivo.
 *
 * Los campos que usa (`Name`, `FirstName`, `MiddleName`, `LastNameAtBirth`, `BirthDate`, `DeathDate`,
 * `BirthLocation`, `DeathLocation`, `IsLiving`, `Father`, `Mother`) están escritos de memoria y sin
 * probar contra una respuesta real. Como siempre, fechas, lugares y parientes solo salen si consta una
 * muerte: `IsLiving` en verdadero, o una `DeathDate` desconocida («0000-00-00»), los deja fuera.
 */
final class WikiTreeApi
{
    /**
     * @return list<Perfil>
     * @throws RuntimeException si el texto no es JSON o no trae ningún perfil que se reconozca
     */
    public static function desdeJson(string $json, ?string $capturado = null): array
    {
        $datos = json_decode($json, true);
        if (!is_array($datos)) {
            throw new RuntimeException('La respuesta de WikiTree no es JSON');
        }
        // Un objeto con «profile», una lista de resultados con «profile», o una lista de perfiles sueltos.
        $candidatos = array_is_list($datos) ? $datos : [$datos];
        $perfiles = [];
        foreach ($candidatos as $c) {
            $p = Json::en($c, 'profile') ?? $c;
            if (is_array($p) && Json::texto($p, 'Name') !== null) {
                $perfiles[] = self::perfil($p, $capturado);
            }
        }
        if ($perfiles === []) {
            throw new RuntimeException('La respuesta de WikiTree no trae ningún perfil que se reconozca (falta «Name»)');
        }
        return $perfiles;
    }

    /**
     * @param array<mixed> $p
     */
    private static function perfil(array $p, ?string $capturado): Perfil
    {
        $nombreWikiTree = Json::texto($p, 'Name') ?? '';
        $partes = array_filter([Json::texto($p, 'FirstName'), Json::texto($p, 'MiddleName'), Json::texto($p, 'LastNameAtBirth')],
            static fn (?string $t): bool => $t !== null && trim($t) !== '');
        $nombre = $partes === [] ? null : trim(implode(' ', $partes));

        $vivo = in_array(Json::en($p, 'IsLiving'), [1, '1', true], true);
        $muerte = $vivo ? null : self::fecha(Json::texto($p, 'DeathDate'));

        $parientes = [];
        foreach (['Father' => 'padre', 'Mother' => 'madre'] as $campo => $relacion) {
            $id = Json::en($p, $campo);
            if ((is_int($id) || (is_string($id) && ctype_digit($id))) && (int) $id > 0) {
                $parientes[] = ['relacion' => $relacion, 'nombre' => null, 'id' => (string) $id];
            }
        }

        $avisos = [];
        if ($nombre === null) {
            $avisos[] = 'sin nombre: el perfil es privado o la respuesta no trae FirstName ni LastNameAtBirth';
        }
        if ($capturado === null) {
            $avisos[] = 'sin fecha de captura: anótala al citar la fuente';
        }

        return new Perfil(
            'wikitree',
            'https://www.wikitree.com/wiki/' . rawurlencode($nombreWikiTree),
            $nombreWikiTree,
            $nombre,
            null,
            null,
            null,
            $capturado,
            $avisos,
            self::fecha(Json::texto($p, 'BirthDate')),
            $muerte,
            Json::texto($p, 'BirthLocation'),
            Json::texto($p, 'DeathLocation'),
            $parientes,
        );
    }

    /** «1850-03-02» tal cual; «1850-03-00» da «1850-03»; «1850-00-00» da «1850»; «0000-00-00» o vacío, null. */
    private static function fecha(?string $texto): ?string
    {
        if ($texto === null || preg_match('/^(\d{4})-(\d{2})-(\d{2})/', trim($texto), $m) !== 1 || $m[1] === '0000') {
            return null;
        }
        return match (true) {
            $m[2] !== '00' && $m[3] !== '00' => "$m[1]-$m[2]-$m[3]",
            $m[2] !== '00' => "$m[1]-$m[2]",
            default => $m[1],
        };
    }
}

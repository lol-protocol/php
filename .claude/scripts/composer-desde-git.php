<?php

declare(strict_types=1);

/**
 * Prepara `composer install` en una sesión en la nube cuya red no deja pasar api.github.com ni
 * codeload.github.com (de ahí baja Composer los paquetes), pero sí `git` contra github.com.
 *
 * Por cada paquete del composer.lock que se descarga de GitHub, baja con git solo el commit que el
 * lock fija (`git fetch --depth 1`, sin historial) y deja el zip en la caché de Composer, con el
 * nombre que Composer busca ahí. Después, `composer install` instala desde la caché como si lo
 * hubiera bajado, con el mismo vendor/ de siempre. Los paquetes que ya están en la caché se saltan,
 * así que se puede correr de nuevo sin costo.
 *
 *   php .claude/scripts/composer-desde-git.php url-routing [otra-carpeta…]
 *   (cd url-routing && composer install)
 *
 * No cambia la configuración de Composer ni ningún archivo del proyecto. Si un día la red deja
 * pasar api.github.com, este script sobra.
 */

if ($argc < 2) {
    fwrite(STDERR, "Uso: php {$argv[0]} <carpeta con composer.lock> [...]\n");
    exit(2);
}

$cache = trim((string)shell_exec('composer config --global cache-files-dir 2>/dev/null'));
if ($cache === '') {
    fwrite(STDERR, "No se pudo leer la carpeta de caché de Composer (composer config --global cache-files-dir).\n");
    exit(1);
}

$fallos = 0;
foreach (array_slice($argv, 1) as $carpeta) {
    $lock = rtrim($carpeta, '/') . '/composer.lock';
    $datos = is_file($lock) ? json_decode((string)file_get_contents($lock), true) : null;
    if (!is_array($datos)) {
        fwrite(STDERR, "{$lock}: no existe o no es JSON.\n");
        $fallos++;
        continue;
    }

    foreach (array_merge($datos['packages'] ?? [], $datos['packages-dev'] ?? []) as $paquete) {
        $dist = $paquete['dist'] ?? [];
        $url = (string)($dist['url'] ?? '');
        if (($dist['type'] ?? '') !== 'zip' || preg_match('#^https://(api\.github\.com/repos|codeload\.github\.com)/([^/]+)/([^/]+)/#', $url, $m) !== 1) {
            continue; // path, o fuera de GitHub: Composer lo resuelve solo
        }
        $nombre = (string)$paquete['name'];
        $destino = "{$cache}/{$nombre}/" . sha1($url) . '.zip'; // la clave de FileDownloader::getCacheKey()
        if (is_file($destino)) {
            continue;
        }
        if (($dist['shasum'] ?? '') !== '') {
            fwrite(STDERR, "{$nombre}: el lock fija un shasum para el zip y uno hecho con git no coincidiría; se salta.\n");
            $fallos++;
            continue;
        }

        $repo = (string)($paquete['source']['url'] ?? "https://github.com/{$m[2]}/{$m[3]}.git");
        $commit = (string)($dist['reference'] ?? '');
        if (!bajar($repo, $commit, $destino, basename($nombre))) {
            fwrite(STDERR, "{$nombre}: no se pudo bajar {$commit} de {$repo}.\n");
            $fallos++;
            continue;
        }
        echo "{$nombre} {$paquete['version']}\n";
    }
}

exit($fallos === 0 ? 0 : 1);

/** Hace con git el zip que GitHub serviría para ese commit (git archive respeta export-ignore, como GitHub). */
function bajar(string $repo, string $commit, string $destino, string $prefijo): bool
{
    if (preg_match('/^[0-9a-f]{40}$/', $commit) !== 1) {
        return false;
    }
    $tmp = sys_get_temp_dir() . '/composer-desde-git-' . getmypid() . '-' . bin2hex(random_bytes(4));
    $q = 'escapeshellarg';
    $ok = @mkdir(dirname($destino), 0777, true) || is_dir(dirname($destino));
    $ok = $ok && run('git init -q ' . $q($tmp))
        && run('git -C ' . $q($tmp) . ' fetch -q --depth 1 ' . $q($repo) . ' ' . $q($commit))
        && run('git -C ' . $q($tmp) . ' archive --format=zip --prefix=' . $q($prefijo . '/') . ' -o ' . $q($destino . '.tmp') . ' FETCH_HEAD')
        && rename($destino . '.tmp', $destino);
    @unlink($destino . '.tmp');
    run('rm -rf ' . $q($tmp));

    return $ok;
}

function run(string $comando): bool
{
    exec($comando . ' 2>&1', $salida, $codigo);

    return $codigo === 0;
}

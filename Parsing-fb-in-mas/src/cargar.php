<?php

declare(strict_types=1);

// Sin Composer: el proyecto no tiene dependencias propias.
foreach ([
    'Json', 'Perfil', 'Metadatos', 'Analizador',
    'Facebook', 'LinkedIn', 'Twitter', 'Instagram', 'TikTok', 'YouTube', 'GitHub', 'Pinterest',
    'WikiTree', 'FindAGrave', 'Generico', 'Detector', 'Lote', 'Wikidata', 'Cruce',
] as $clase) {
    require_once __DIR__ . "/$clase.php";
}

// `Cruce` usa el plegado fonético de ../defamatory-content-review (como phone-directory/).
spl_autoload_register(static function (string $clase): void {
    $prefijo = 'DefamatoryContentReview\\';
    if (str_starts_with($clase, $prefijo)) {
        $ruta = __DIR__ . '/../../defamatory-content-review/src/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
        if (is_file($ruta)) {
            require $ruta;
        }
    }
});

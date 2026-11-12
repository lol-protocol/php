<?php

declare(strict_types=1);

/** Pruebas de las licencias de las fuentes incrustadas. Las funciones auxiliares están en ../ayudas.php. */

return [
    'Cada fuente que se incrusta tiene su licencia y su fila en fuentes/ORIGEN.md' => function (): void {
        $raiz = dirname(__DIR__, 2);
        $datos = json_decode((string) file_get_contents("$raiz/datos/fuentes.json"), true, 16, JSON_THROW_ON_ERROR);
        $origen = (string) file_get_contents("$raiz/fuentes/ORIGEN.md");
        $porFamilia = ['serif' => 'source-serif-4', 'sans' => 'inter'];
        verdadero(is_array($datos) && isset($datos['caras']) && is_array($datos['caras']), 'datos/fuentes.json tiene caras');
        $caras = is_array($datos) && is_array($datos['caras'] ?? null) ? $datos['caras'] : [];
        foreach (array_keys($caras) as $familia) {
            $paquete = $porFamilia[$familia] ?? '';
            verdadero($paquete !== '', "la familia «{$familia}» no tiene paquete conocido: añádela a esta prueba y a fuentes/ORIGEN.md");
            $licencia = "$raiz/fuentes/LICENCIA-$paquete.txt";
            verdadero(is_file($licencia), "falta fuentes/LICENCIA-$paquete.txt");
            verdadero(str_contains((string) file_get_contents($licencia), 'SIL Open Font License'), "$paquete: la licencia no es la OFL");
            verdadero(str_contains($origen, "LICENCIA-$paquete.txt"), "fuentes/ORIGEN.md no enlaza la licencia de $paquete");
            verdadero(preg_match('/@fontsource-variable\/' . preg_quote($paquete, '/') . '@\d+\.\d+\.\d+/', $origen) === 1, "fuentes/ORIGEN.md no dice de dónde y qué versión es $paquete");
        }
    },

    'El kerning acerca los pares que lo piden, dentro de una palabra' => function (): void {
        $medida = PortadasSvg\Anchos::de('serif', 'normal', 400);
        verdadero($medida->em('AV') < $medida->em('A') + $medida->em('V') - 0.05, '«AV» se acerca');
        aproximado($medida->em('A') + $medida->em('B') + $medida->ajuste('A', 'B') / 1000, $medida->em('AB'));
        igual($medida->em('A V'), $medida->em('A') + $medida->em(' ') + $medida->em('V'));
        igual(0, $medida->ajuste('', 'A'));
        verdadero($medida->ajuste('A', 'V') < 0, 'el ajuste de «AV» es negativo');
        // Una letra con acento se ajusta como su base, y el griego y el cirílico también tienen kerning.
        igual($medida->ajuste('A', 'V'), $medida->ajuste('Á', 'V'));
        verdadero($medida->em('ÁV') < $medida->em('Á') + $medida->em('V') - 0.05, '«ÁV» se acerca');
        $sans = PortadasSvg\Anchos::de('sans', 'normal', 400);
        verdadero($sans->ajuste('Т', 'о') < 0 || $sans->ajuste('Λ', 'Υ') < 0 || $sans->ajuste('Д', 'А') !== 0, 'el cirílico o el griego tienen algún par ajustado');
        igual(0, $sans->ajuste('A', 'Я'));
    },
];

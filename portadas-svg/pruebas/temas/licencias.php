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
        foreach (array_keys($datos['caras']) as $familia) {
            $paquete = $porFamilia[$familia] ?? null;
            verdadero($paquete !== null, "la familia «{$familia}» no tiene paquete conocido: añádela a esta prueba y a fuentes/ORIGEN.md");
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
    },
];

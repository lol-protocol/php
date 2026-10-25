<?php

// Frases corrientes que el chat NO debe marcar, por idioma, para SafePhrasesTest. Cada vez que se arregla un
// falso positivo, la frase que lo produjo se agrega aquí: así no vuelve al ampliar idiomas o patrones.
// 'safe' = debe aprobarse. 'still' = debe seguir marcándose (el control: sin él, una lista que aprueba todo pasaría).
// No van aquí las que se marcan a propósito: «vivía en la calle…» (dato personal), «una puta suerte» (la palabra
// es el insulto aunque la frase no lo sea) ni «Jean-Cul» (la parte ofensiva de un compuesto).

return [
    'safe' => [
        'spa' => [
            'Nació en Oviedo en 1850 y se casó con Elena Martínez.', 'El forro del abrigo era de seda.',
            'La gonorrea es una infección.', 'Antecedentes de la familia García en Jalisco.',
            'El aborto es un tema que se discute en la ley.', 'El vino es negro y el pan es blanco.',
            'Es turco el café que trajo mi abuelo.', 'Trabajó como panadero y luego como maestro.',
            'Llegó a Guadalajara desde Oviedo en 1892.', 'Fue bautizado en la parroquia de San José.',
            'Tom & Jerry', '¡Vamos 🇨🇱!', 'La concha de la playa estaba rota.', 'El cura del pueblo bendijo la casa.',
            'Heredó una finca y tres caballos.', '<b>hola</b> a todos',
        ],
        'eng' => [
            'He was born in Dublin in 1850 and married Mary Murphy.', 'The class was held at the church.',
            'She worked as a nurse and later as a teacher.', 'The assassin of the story is a character in a novel.',
            'He moved to Boston and opened a shop.', 'A Scunthorpe address on Penistone Road.',
        ],
        'por' => ['Ele matou a saudade visitando a avó.', 'Nasceu em Lisboa em 1850.', 'A cidade de Cuba fica no Alentejo.'],
        'fra' => ['La mer de Chine est très grande.', "J'ai pu te voir hier.", 'Il est né à Rouen en 1850.'],
        'ita' => ['Nacque a Roma nel 1850 e sposò Maria.', 'La cazzuola del muratore era di ferro.'],
        'deu' => ['Er wurde 1850 in Berlin geboren.', 'Die Kirche steht am Marktplatz.'],
        'tur' => ['İstanbul çok güzel.', 'IŞIK', 'Dedem 1850 yılında İzmir’de doğdu.'],
    ],
    'still' => [
        'spa' => ['eres una puta', 'te voy a matar', 'Juan estafó a su familia'],
        'eng' => ['you are a whore', "I'll kill you", 'he stole from his family'],
        'por' => ['voce e uma puta', 'vou te matar'],
        'fra' => ['espèce de connard', 'je vais te tuer'],
    ],
];

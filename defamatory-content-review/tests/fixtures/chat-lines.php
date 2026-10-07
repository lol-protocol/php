<?php

// Líneas de chat para ChatLineDetectionTest. 'flagged': línea => [decisión, tipos de contenido].
// 'clean': líneas que deben aprobarse — algunas mencionan algo de severidad baja («guerra»), que se
// informa pero no se censura — y cuyas palabras tienen otro uso cotidiano («coger el bus», «mate»).

return [
    'spa' => [
        'flagged' => [
            'Eres un mentiroso y un idiota' => ['review', ['difamatorio']],
            'Callate vejestorio' => ['review', ['burlesco']],
            'Mándame nudes ahora' => ['reject', ['sexual']],
            'quiero coger contigo, mándame nudes' => ['reject', ['sexual']],
            'fotos desnudas por whatsapp' => ['review', ['sexual']],
            'masturbándose frente a la cámara' => ['reject', ['sexual']],
            'fóllame' => ['reject', ['sexual']],
            'p u t a madre' => ['reject', ['difamatorio']],
            'vamos a f o l l a r' => ['reject', ['difamatorio', 'sexual']],
            'Hay que bombardear ese pueblo' => ['reject', ['belico']],
            'lanzaron una bomba sobre la ciudad' => ['reject', ['belico']],
            'los terroristas hicieron un atentado' => ['review', ['belico']],
            'te voy a matar' => ['reject', ['belico']],
            'Te voy a matar, hijo de puta' => ['reject', ['belico', 'difamatorio']],
            'ojalá te mueras' => ['reject', ['belico']],
            'muérete' => ['reject', ['belico']],
            'mátalos a todos' => ['reject', ['belico']],
            'hay que matar a esos tipos' => ['reject', ['belico']],
            'mátalos' => ['review', ['belico']],
            'te voy a violar' => ['reject', ['sexual']],
            // Letras repetidas: 3 o más no existen en el idioma y bloquean; 2 pueden ser un apellido y van a revisión.
            'puuuuta' => ['reject', ['difamatorio']],
            'Eres una puuuuta madre' => ['reject', ['difamatorio']],
            'mmmmierda' => ['reject', ['difamatorio']],
            'p u u u t a' => ['reject', ['difamatorio']],
            'puuta' => ['review', ['difamatorio']],
            'vamos a f o l l a r r' => ['review', ['difamatorio', 'sexual']],
            'te voy a mataaaar' => ['reject', ['belico']],
        ],
        'clean' => [
            'Hola, ¿cómo estás?', 'Mañana viene la abuela con las fotos de la boda',
            'vamos a coger el bus de las ocho', 'me cogí un resfriado', 'está de bomba la fiesta',
            'qué bomba de noticia', 'tomamos mate en el patio', 'jaque mate en tres jugadas',
            'toca la viola en la orquesta', 'techo de paja y barro', 'J R R Tolkien escribió libros',
            'mi abuelo luchó en la guerra', 'estudiamos el ejército romano', 'la invasión de Normandía fue en 1944',
            'la bomba atómica terminó la guerra', 'el frío me mata', 'me muero de risa',
            'te voy a llevar al aeropuerto', 'voy a matar el tiempo leyendo', 'hay que matar el tiempo',
            // Palabras con letra doble legítima, algunas cuya lectura sin repetidas es un insulto («calle» → «calé»).
            'vivimos en la calle Mayor', 'qué chollo de precio', 'el morro del cerro', 'perro y gato', 'las llaves',
            'Anna y Hannah', 'acción y reacción', 'el zoo de Madrid', 'coordinar la reunión', 'creer o leer',
        ],
    ],
    'eng' => [
        'flagged' => [
            'send me nudes' => ['reject', ['sexual']],
            'send dick pics' => ['reject', ['difamatorio', 'sexual']],
            'u r a fucking idiot' => ['reject', ['difamatorio', 'sexual']],
            'that was a genocide' => ['reject', ['belico']],
            'they massacred the whole village' => ['reject', ['belico']],
            'they were bombing the city' => ['review', ['belico']],
            'he planted a bomb' => ['reject', ['belico']],
            'I will kill you' => ['reject', ['belico']],
            "i'm gonna kill you" => ['reject', ['belico']],
            'kill yourself' => ['reject', ['belico']],
            'kill them all' => ['reject', ['belico']],
            "I'll rape you" => ['reject', ['sexual']],
            'fuuuck you' => ['reject', ['difamatorio', 'sexual']],
            'you are a biiiitch' => ['reject', ['difamatorio']],
            'fuuck you' => ['review', ['difamatorio', 'sexual']],
        ],
        'clean' => [
            'this pizza is the bomb', 'I bombed the exam yesterday', 'bath bombs make a nice gift',
            'the war ended in 1945', 'my grandfather served in the army', 'we studied the invasion of Normandy',
            'I will see you tomorrow', 'I could kill for a coffee', 'you kill me with that joke',
            "i'm going to kill the lights",
            'it is looser than before', 'Hi Jaap', 'Mr Pratt called', 'class pass kiss boss',
            'coordinate the committee', 'Hannah and Anna', 'a bonny lass',
        ],
    ],
];

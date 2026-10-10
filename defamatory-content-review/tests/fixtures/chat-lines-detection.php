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
    // Falsos positivos medidos con bin/false-positives.php: palabras de uso diario que se plegaban a un
    // insulto («høre» → «hore», «tai» → «täi») o que son insulto sólo a veces («preto», «kanker», «gek»).
    'dan' => [
        'flagged' => [
            'du er en hore' => ['reject', ['difamatorio']],
            'dit røvhul' => ['reject', ['difamatorio']],
        ],
        'clean' => ['Hvornår? Når du vil.', 'Jeg vil gerne høre fra dig', 'Er der nogen hjemme?'],
    ],
    'nor' => [
        'flagged' => [
            'din hore' => ['reject', ['difamatorio']],
        ],
        'clean' => ['Jeg vil høre fra deg', 'Vi må ikke tape kampen'],
    ],
    'swe' => [
        'flagged' => [
            'din hora' => ['review', ['difamatorio']],
        ],
        'clean' => ['Jag vill höra från dig', 'Det är rätt, vi ska rätta det', 'Det var grymt bra!'],
    ],
    'fin' => [
        'flagged' => [
            'senkin täi' => ['review', ['difamatorio']],
            'vittu' => ['reject', ['difamatorio']],
        ],
        'clean' => ['Kahvia tai teetä?'],
    ],
    'vie' => [
        'flagged' => [
            'đồ dái' => ['reject', ['difamatorio']],
            'đồ ngu' => ['review', ['difamatorio']],
        ],
        'clean' => ['Đeo kính vào, tóc dài quá', 'Mọi người khỏe không?'],
    ],
    'ces' => [
        'flagged' => [
            'moč' => ['review', ['difamatorio']],
        ],
        'clean' => ['Moc děkuji, nemá to chybu', 'Mám nové pero'],
    ],
    'slk' => [
        'flagged' => [
            'ty kurva' => ['reject', ['difamatorio']],
        ],
        'clean' => ['Ďakujem moc, nemá čas'],
    ],
    'hun' => [
        'flagged' => [
            'te őrült kurva' => ['reject', ['difamatorio']],
        ],
        'clean' => ['Nagyon örült a hírnek', 'Santa hozott ajándékot, ez nem sértés'],
    ],
    'tur' => [
        'flagged' => [
            'sik' => ['review', ['difamatorio']],
            'orospu' => ['reject', ['difamatorio']],
        ],
        'clean' => ['Sık sık gelirim, çok şık', 'Bize katıl, Çinli arkadaşım da geliyor'],
    ],
    'por' => [
        'flagged' => [
            'seu verme' => ['review', ['difamatorio']],
            'filho da puta' => ['reject', ['difamatorio']],
        ],
        'clean' => ['Vem ver-me amanhã', 'O ônibus está atrasado', 'Comprei um carro preto', 'Vou lavar a louça'],
    ],
    'nld' => [
        'flagged' => [
            'klootzak' => ['reject', ['difamatorio']],
        ],
        'clean' => ['Mijn opa had kanker', 'Het pad naar huis', 'Doe niet zo gek'],
    ],
    'deu' => [
        'flagged' => [
            'du arschloch' => ['reject', ['difamatorio']],
        ],
        'clean' => ['Das ist verrückt', 'Wahnsinnig gut!'],
    ],
    'fra' => [
        'flagged' => [
            'je vais te baiser, salope' => ['reject', ['difamatorio']],
        ],
        'clean' => ['Gros baisers à toute la famille', 'J\'ai raté le train', 'C\'est fou !', 'Il possède une belle demeure'],
    ],
    'ita' => [
        'flagged' => [
            'sei uno stronzo' => ['reject', ['difamatorio']],
        ],
        'clean' => ['È un film orribile', 'Mi mostrò la foto'],
    ],
    'ron' => [
        'flagged' => [
            'ești o curvă' => ['reject', ['difamatorio']],
        ],
        'clean' => ['Am cumpărat o mașină neagră', 'Trebuie să muta mobila'],
    ],
    'ara' => [
        'flagged' => [
            'يا حمار' => ['review', ['difamatorio']],
        ],
        'clean' => ['أمي تحبك'],
    ],
    'tha' => [
        'flagged' => [
            'ไอ้เหี้ย' => ['reject', ['difamatorio']],
            'ไอ้บ้าเอ๊ย' => ['review', ['difamatorio']],    // «บ้า» dirigido a alguien (provisional, ver tha.php)
            'แกมันคนบ้า' => ['review', ['difamatorio']],
        ],
        'clean' => ['หนู', 'บ้าจริง', 'คุณบ้าไปแล้ว'],  // exclamaciones con «บ้า» («maldita sea», «¿estás loco?»)
    ],
    'ind' => [
        'flagged' => [
            'dasar anjing' => ['reject', ['difamatorio']],
        ],
        'clean' => ['Buang sampah pada tempatnya', 'Bau masakan enak', 'Gila, keren banget!'],
    ],
    'tgl' => [
        'flagged' => [],
        'clean' => ['May ahas sa bukid', 'Ang hayop sa zoo'],
    ],
    'heb' => [
        'flagged' => [],
        'clean' => ['מטורף! איזה משחק'],
    ],
    'bul' => [
        'flagged' => [
            'ти си курва' => ['reject', ['difamatorio']],
        ],
        'clean' => ['Гол!'],
    ],
];

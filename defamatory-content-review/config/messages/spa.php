<?php

// Español: origen y respaldo de los demás. Los nombres y descripciones de las categorías están en config/risk-categories.php.
return [
    'explanation.where' => 'diccionario %s, tipo %s, severidad %s',
    'explanation.fusion' => '«%1$s» leído seguido suena como «%2$s» (%3$s)',
    'explanation.variant' => '«%1$s» suena igual que «%2$s» con otra grafía (%3$s)',
    'explanation.match' => '«%1$s» coincide con «%2$s» (%3$s)',
    'explanation.related' => '; idioma emparentado, confianza %.2f',
    'explanation.collision' => '; también es nombre o apellido real documentado, va a revisión humana',
    'explanation.end' => '.',
    'recommendation.reject' => 'Rechazar: contenido gravemente ofensivo (tipos de riesgo: %s).',
    'recommendation.review_collision' => 'Revisión humana: coincide con términos ofensivos (%s) pero también con apellidos documentados.',
    'recommendation.review_phonetic' => 'Revisión humana: nombre y apellido, fusionados, componen un término ofensivo (%s) que ninguno de los dos tiene por separado.',
    'recommendation.review' => 'Revisión humana: términos potencialmente ofensivos (%s).',
    'recommendation.accept_with_flag' => 'Aceptar con marca: términos de bajo riesgo (%s).',
    'recommendation.accept' => 'Aceptar: sin contenido difamatorio detectado.',
];

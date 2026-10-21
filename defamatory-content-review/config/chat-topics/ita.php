<?php

/** Italian chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'ita', 'kind' => 'chat-topics'],
    'targeted' => [
        'types' => ['animal', 'discapacidad'],
        'before' => ['sei', 'sei un', 'sei una', 'siete', 'brutto', 'brutta', 'pezzo di', 'maledetto', 'maledetta', 'figlio di', 'figlia di', 'quel', 'quella', 'sporco', 'sporca', 'razza di'],
        'after' => ['di merda', 'schifoso', 'schifosa', 'che sei'],
    ],
    'everyday' => ['mostrò', 'fucili'],
    'words' => [
        'sexual' => [
            ['word' => 'porno', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'pornografia', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'pompino', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'masturbazione', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'masturbare', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'orgasmo', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'pene', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'vagina', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'tette', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nudo', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'adj'],
            ['word' => 'nudes', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'foto intime', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'sesso', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'sesso orale', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'stupro', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'stuprare', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'verb'],
        ],
        'belico' => [
            ['word' => 'guerra', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'bombardamento', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'missile', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'genocidio', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'massacro', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'massacrare', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'sterminare', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'terrorista', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'attentato', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'invasione', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'esercito', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'fucilare', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'sgozzare', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'pulizia etnica', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'guerra santa', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'jihad', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
        'ambiguous' => [
            ['word' => 'bomba', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'sega', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'scopare', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'verb'],
        ],
    ],
    'patterns' => [
        // Accuse contro una persona e dati sensibili (salute, vita privata, orientamento sessuale):
        // possono essere vere; non bloccano mai, vanno in revisione umana. Vedi docs/RIESGOS.md.
        ['pattern' => '\b(ha|hanno|aveva|avevano)\s+(rubato|truffato|derubato|violentato|stuprato|ucciso|ammazzato|picchiato|estorto|corrotto|abusato\s+di|sottratto)\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'accusa di reato'],
        ['pattern' => '\b(e\s+stat[oa]|fu|era|e)\s+(condannat[oa]|arrestat[oa]|incarcerat[oa]|indagat[oa]|in\s+galera|in\s+carcere|in\s+prigione)\b|\bha\s+(precedenti\s+penali|la\s+fedina\s+penale\s+sporca)\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'accusa di reato'],
        ['pattern' => '\b(e|era|sono|erano)\s+((un|una|uno|dei|delle|tutti|tutte)\s+)?(ladro|ladra|ladri|truffator[ei]|truffatrice|stuprator[ei]|pedofil[oiae]|assassin[oaie]|criminal[ei]|spacciator[ei]|corrott[oaie]|delinquent[ei]|mafios[oaie]|imbroglion[ei])\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'accusa di reato'],
        ['pattern' => '\b(ha|aveva|ha\s+avuto|ha\s+preso)\s+(l\s+|la\s+|il\s+|un\s+)?(aids|hiv|sifilide|gonorrea|herpes|epatite|cancro|tumore|tubercolosi|schizofrenia|depressione)\b|\be\s+(sieropositiv[oa]|schizofrenic[oa]|bipolare)\b|\bha\s+abortito\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'dato sensibile (salute)'],
        ['pattern' => '\b(tradisce|tradiva|ha\s+tradito)\s+(la\s+|il\s+)?(moglie|marito|fidanzata|fidanzato|compagna|compagno)\b|\bha\s+(un|una)\s+amante\b|\be\s+infedele\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'vita privata'],
        ['pattern' => '\b(e|era|sono)\s+(gay|lesbica|lesbiche|omosessuale|omosessuali|bisessuale)\b|\bha\s+fatto\s+coming\s+out\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'dato sensibile (orientamento sessuale)'],
        ['pattern' => '\bti\s+(?:ammazzo|ammazzero|uccido|uccidero|sgozzo|sgozzero|massacro)\b|\bti\s+(?:voglio|vado\s+a|devo)\s+(?:ammazzare|uccidere|sgozzare)\b|\b(?:ammazzarti|ucciderti|sgozzarti)\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'minaccia'],
        ['pattern' => '\bti\s+(?:stupro|stuprero)\b|\bstuprarti\b', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'minaccia sessuale'],
    ],
];

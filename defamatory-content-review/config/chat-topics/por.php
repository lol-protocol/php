<?php

/** Portuguese chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'por', 'kind' => 'chat-topics', 'collapseRepeats' => true],
    'targeted' => [
        'types' => ['animal', 'discapacidad'],
        'before' => ['você é', 'voce e', 'você', 'és', 'seu', 'sua', 'seus', 'suas', 'sois', 'maldito', 'maldita', 'pedaço de', 'filho de', 'filha de', 'é um', 'é uma', 'esse', 'essa', 'este', 'esta'],
        'after' => ['de merda', 'nojento', 'nojenta', 'imundo', 'imunda', 'do caralho'],
    ],
    'everyday' => ['louça', 'ver-me', 'pôr-nos'],
    'words' => [
        'sexual' => [
            ['word' => 'porno', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'pornografia', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'boquete', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'masturbacao', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'masturbar', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'orgasmo', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'penis', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'vagina', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'peitos', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nudes', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'foto intima', 'riskType' => 'sexual', 'severity' => 'high', 'also' => ['fotos intimas']],
            ['word' => 'sexo', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'sexo oral', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'estupro', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'estuprar', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'verb'],
        ],
        'belico' => [
            ['word' => 'guerra', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'bombardeio', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'missil', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'genocidio', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'massacre', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'massacrar', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'exterminar', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'terrorista', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'atentado', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'adj'],
            ['word' => 'invasao', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'exercito', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'fuzilar', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'degolar', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'limpeza etnica', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'guerra santa', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => 'bomba', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'transar', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'verb'],
        ],
    ],
    'patterns' => [
        // Acusações contra uma pessoa e dados sensíveis (saúde, vida privada, orientação sexual):
        // podem ser verdade; nunca bloqueiam, vão para revisão humana. Ver docs/RIESGOS.md.
        ['pattern' => '\b(roubou|rouba|roubava|roubaram|furtou|furtava|estuprou|estupra|abusou|abusa|abusava|assassinou|desviou|desvia|extorquiu|subornou)\s+(a|o|os|as|de|do|da|dos|das|minha|meu|minhas|meus|sua|seu|suas|seus|dinheiro|criancas|menores|clientes|gente)\b|\b(aplicou|deu)\s+(um\s+)?golpe\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'acusação de crime'],
        ['pattern' => '\b(foi|esta|estava|esteve|ficou)\s+(condenad[oa]s?|pres[oa]s?|detid[oa]s?|indiciad[oa]s?|processad[oa]s?)\b|\b(esta|esteve|estava)\s+na\s+(cadeia|prisao)\b|\btem\s+(antecedentes\s+criminais|ficha\s+criminal|passagem\s+pela\s+policia)\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'acusação de crime'],
        ['pattern' => '\b(e|era|sao|eram|foi)\s+((um|uma|uns|umas|todos|todas)\s+)?(ladrao|ladra|ladroes|ladras|golpista|golpistas|estelionatari[oa]s?|traficantes?|estupradore?s?|pedofil[oa]s?|assassin[oa]s?|criminos[oa]s?|bandid[oa]s?|corrupt[oa]s?|vigaristas?|abusadore?s?)\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'acusação de crime'],
        ['pattern' => '\b(tem|tinha|teve|pegou)\s+(aids|hiv|sifilis|gonorreia|herpes|hepatite|cancer|tuberculose|esquizofrenia|depressao)\b|\be\s+(soropositiv[oa]|esquizofrenic[oa]|bipolar)\b|\bfez\s+(um\s+)?aborto\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'dado sensível (saúde)'],
        ['pattern' => '\b(trai|traiu|traia)\s+(a|o)\s+(mulher|marido|esposa|esposo|namorada|namorado)\b|\btem\s+(uma|um)\s+amante\b|\b(e|era|foi)\s+infiel\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'vida privada'],
        ['pattern' => '\b(e|era|sao|eram)\s+(gay|gays|lesbica|lesbicas|homossexual|homossexuais|bissexual|travesti)\b|\bsaiu\s+do\s+armario\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'dado sensível (orientação sexual)'],
        ['pattern' => '\bvou\s+(?:te\s+)?(?:matar|degolar|estripar|esfaquear)\b|\bte\s+(?:mato|degolo|estripo|esfaqueio)\b|\bmatar\s+voce\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'ameaca'],
        ['pattern' => '\bvou\s+te\s+estuprar\b|\bte\s+estupro\b', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'ameaca sexual'],
    ],
];

<?php

/**
 * English chat topics — see spa.php for the format ('forms', 'also', the
 * 'ambiguous' category, 'patterns', meta 'collapseRepeats' and 'legit') and the
 * meaning of severity.
 */
return [
    'meta' => ['code' => 'eng', 'kind' => 'chat-topics', 'collapseRepeats' => true],
    'targeted' => [
        'types' => ['animal', 'discapacidad'],
        'before' => ['you', 'you\'re', 'youre', 'ur', 'u', 'he\'s', 'she\'s', 'he is', 'she is', 'they\'re', 'they are', 'such a', 'what a', 'fucking', 'fuckin', 'stupid', 'dirty', 'filthy', 'fat', 'ugly', 'damn', 'bloody', 'son of a'],
        'after' => ['you are', 'ass'],
    ],
    'legit' => ['annus', 'bonny', 'curr', 'hogg', 'jaap', 'looser', 'pigg', 'pratt'],
    'words' => [
        'sexual' => [
            ['word' => 'porn', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'pornography', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'fuck', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'fucker', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'motherfucker', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'blowjob', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'pussy', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'cock', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'tits', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'boobs', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'naked', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nudes', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'horny', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'masturbate', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'masturbation', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'sex', 'riskType' => 'sexual', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'dick pic', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'suck my dick', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'war', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'missile', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'genocide', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'massacre', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'exterminate', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'terrorist', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'terrorism', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'invasion', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'ethnic cleansing', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'holy war', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
        ],
        'ambiguous' => [
            ['word' => 'bomb', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'verb'],
        ],
    ],
    'patterns' => [
        // Accusations against a person (defamation by stating facts, not by insulting).
        // They may be true (court records, a biography): never block, go to human review.
        ['pattern' => '\b(stole|steals|embezzled|embezzles|defrauded|defrauds|scammed|scams|swindled|swindles|molested|molests|abused|abuses|raped|rapes|murdered|extorted|bribed)\s+(from|his|her|their|my|your|our|kids|children|minors|money|people|clients|(a|an|the)\s+(child|kid|girl|boy|woman|man|minor|company|church|money))\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'accusation of a crime'],
        ['pattern' => '\b(was|got|has\s+been|have\s+been|is|been)\s+(convicted|arrested|jailed|imprisoned|indicted|sentenced|charged\s+with)\b|\b(is|was|been)\s+(in\s+jail|in\s+prison|behind\s+bars)\b|\bhas\s+a\s+(criminal\s+record|rap\s+sheet)\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'accusation of a crime'],
        ['pattern' => '\b(is|was|he\s+is|she\s+is|hes|shes|they\s+are|are)\s+(a\s+|an\s+|all\s+)?(thieves|crooks|criminals|scammers|fraudsters|murderers|rapists|pedophiles|drug\s+dealers|drug\s+dealer|pedophile|paedophile|child\s+molester|molester|rapist|fraudster|scammer|con\s+artist|thief|crook|criminal|murderer|embezzler|pimp)\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'accusation of a crime'],
        ['pattern' => '\b(has|had|got|caught)\s+(aids|hiv|syphilis|gonorrhea|herpes|chlamydia|hepatitis|schizophrenia|cancer|depression|dementia|alzheimers|parkinsons|epilepsy|anorexia|bulimia)\b|\b(had|got)\s+an\s+abortion\b|\bis\s+(schizophrenic|hiv\s+positive)\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'sensitive data (health)'],
        // Sensitive data and private life of a third party, even if true: sexual orientation,
        // parentage, suicide, domestic violence.
        ['pattern' => '\b(is|was|he\s+is|she\s+is|hes|shes|he\s+s|she\s+s)\s+(gay|a\s+lesbian|lesbian|homosexual|bisexual|transgender|a\s+transsexual)\b|\bcame\s+out\s+(as\s+gay|as\s+lesbian|of\s+the\s+closet)\b|\bis\s+in\s+the\s+closet\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'sensitive data (sexual orientation)'],
        // Ethnic origin, religion and political opinion of someone: sensitive data even if true.
        ['pattern' => '\b(is|was|are|were|he\s+is|she\s+is|hes|shes|he\s+s|she\s+s)\s+(a\s+|an\s+)?(jew|jewish|muslim|moslem|mormon|jehovahs?\s+witness|atheist|evangelical|buddhist)\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'sensitive data (religion)'],
        ['pattern' => '\b(is|was|are|were|he\s+is|she\s+is|hes|shes|he\s+s|she\s+s)\s+(a\s+|an\s+)?(gypsy|gypsies|roma|native\s+american|aboriginal|indigenous|mixed\s+race|half\s+black)\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'sensitive data (ethnic origin)'],
        ['pattern' => '\b(is|was|are|were|he\s+is|she\s+is|hes|shes|he\s+s|she\s+s)\s+(a\s+|an\s+)?(communist|socialist|fascist|anarchist|far\s+right|far\s+left|republican|democrat|commie)s?\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'sensitive data (political opinion)'],
        ['pattern' => '\b(is|was)\s+(an\s+|a\s+)?(illegitimate|adopted|bastard\s+child|love\s+child)\b|\b(isnt|is\s+not|wasnt|was\s+not|isn\s+t|wasn\s+t)\s+(his|her|their)\s+(real\s+|biological\s+)?(father|mother|dad|mom|son|daughter)\b|\b(his|her|their)\s+(real|biological)\s+father\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'private life (parentage)'],
        ['pattern' => '\b(committed|attempted)\s+suicide\b|\b(killed|hanged|shot)\s+(himself|herself|themselves)\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'sensitive data (suicide)'],
        ['pattern' => '\b(beats|beat|hits|hit|abuses|abused)\s+(his|her|their)\s+(wife|husband|kids|children|son|daughter|partner|girlfriend|boyfriend|mother|father)\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'accusation of a crime'],
        ['pattern' => '\b(is\s+cheating|cheats|cheated)\s+on\s+(his|her|their)\b|\bhas\s+(a|an)\s+(mistress|affair)\b|\b(sleeps|slept|sleeping)\s+with\s+(his|her|their)\b|\bhas\s+(a\s+)?(bad\s+credit|debts\s+everywhere)\b|\b(doesnt|does\s+not|never)\s+pays?\s+(his|her|their)\s+(debts|bills|child\s+support)\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'private life'],
        ['pattern' => '\b(ll|will|gonna|going\s+to|ima|imma)\s+(kill|murder|shoot|stab|strangle|behead)\s+(you|u|ya|them|everyone)\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'threat'],
        ['pattern' => '\b(ll|will|gonna|going\s+to|ima|imma)\s+rape\s+(you|u|her|them)\b', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'sexual threat'],
        ['pattern' => '\b(kill\s+(your\s*self|urself)|kys|go\s+die|hope\s+you\s+(die|burn|rot))\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'incitement to suicide'],
        ['pattern' => '\bkill\s+(them|em)\s+all\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'incitement to kill'],
        ['pattern' => '\b(plant|planted|planting|drop|dropped|dropping|throw|threw|detonate|detonated)\s+(a\s+|the\s+)?bombs?\b|\bbomb\s+threat\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'bomb'],
        ['pattern' => '\b(bomb|bombs|bombed|bombing)\s+(the|a|an|our|their|this|that)\s+(city|town|village|hospital|school|base|embassy|building|market|church|capital)\b', 'riskType' => 'belico', 'severity' => 'medium', 'label' => 'bombing'],
        ['pattern' => '\b(nuclear|atomic|atom)\s+bombs?\b', 'riskType' => 'belico', 'severity' => 'low', 'label' => 'atomic bomb'],
    ],
];

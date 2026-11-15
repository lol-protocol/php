<?php

/**
 * English BIOGRAPHY topics: what someone says about THEMSELVES («I have cancer», «I'm a Muslim», «I'm gay»).
 * Optional, and NOT part of the normal mode: `config/chat-topics/` is written for sentences about someone
 * else («he has cancer») and does not flag these, on purpose, because in a chat or a note telling something
 * about yourself is not defaming anyone. This mode is for reviewing a text a person wrote about themselves
 * (a profile bio) to know whether it discloses sensitive data before it is stored or published.
 *
 * Same format as the `patterns` of `chat-topics/eng.php`: regular expressions without delimiters, written
 * against the folded text (lowercase, no accents, punctuation as spaces: «I'm» becomes «i m»). They never
 * block: severity `medium`, they go to review. Ask for them with `ChatLineReviewer::create($config, 'eng',
 * 'bio-topics')` or `TextMarks::of($text, $config, ['eng'], 'bio-topics')`.
 *
 * The lists are SHORT: flagging nothing does not mean there is no sensitive data.
 */
return [
    'meta' => ['code' => 'eng', 'kind' => 'bio-topics', 'collapseRepeats' => false],
    'patterns' => [
        ['pattern' => '\bi\s+(have|had|live\s+with|suffer\s+from|was\s+diagnosed\s+with|survived)\s+(a\s+|an\s+)?(aids|hiv|cancer|diabetes|depression|anxiety|epilepsy|schizophrenia|autism|alzheimer(\s*s)?|parkinson(\s*s)?|bipolar|ptsd|anorexia|bulimia|adhd|lupus|hepatitis|tuberculosis)\b|\b(i\s+am|i\s+m)\s+(bipolar|diabetic|epileptic|autistic|schizophrenic|hiv\s+positive)\b|\b(cancer|hiv|aids)\s+(survivor|patient)\b', 'riskType' => 'privado', 'severity' => 'medium', 'label' => 'sensitive data (health)'],
        ['pattern' => '\b(i\s+am|i\s+m)\s+(a\s+|an\s+)?(catholic|evangelical|muslim|jewish|mormon|jehovah\s+s\s+witness|atheist|buddhist|protestant|christian)\b|\bpracticing\s+(catholic|muslim|jew|christian)\b', 'riskType' => 'privado', 'severity' => 'medium', 'label' => 'sensitive data (religion)'],
        ['pattern' => '\b(i\s+am|i\s+m)\s+(a\s+|an\s+)?(communist|socialist|republican|democrat|conservative|liberal|anarchist|fascist)\b|\bunion\s+(member|organizer)\b', 'riskType' => 'privado', 'severity' => 'medium', 'label' => 'sensitive data (political opinion)'],
        ['pattern' => '\b(i\s+am|i\s+m)\s+(a\s+|an\s+)?(mapuche|aymara|indigenous|romani|gypsy|african\s+american|native\s+american)\b|\bof\s+(mapuche|aymara|romani|indigenous|native\s+american)\s+(descent|origin|heritage)\b', 'riskType' => 'privado', 'severity' => 'medium', 'label' => 'sensitive data (ethnic origin)'],
        ['pattern' => '\b(i\s+am|i\s+m)\s+(a\s+|an\s+)?(gay|lesbian|bisexual|homosexual|transgender|pansexual|asexual)\b|\b(i\s+am|i\s+m)\s+a\s+trans\s+(man|woman|person)\b', 'riskType' => 'privado', 'severity' => 'medium', 'label' => 'sensitive data (sexual orientation)'],
    ],
];

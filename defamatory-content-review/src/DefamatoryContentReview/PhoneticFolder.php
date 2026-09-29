<?php

namespace DefamatoryContentReview;

// Nombre anterior a 4.3.0, renombrado sin aviso de ruptura: se conserva como
// alias deprecado para no romper a quien lo use. Usar SpanishPhoneticFolder.
class_alias(SpanishPhoneticFolder::class, __NAMESPACE__ . '\PhoneticFolder');

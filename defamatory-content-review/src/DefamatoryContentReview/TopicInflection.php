<?php

namespace DefamatoryContentReview;

/**
 * Formas regulares de una palabra de las listas de chat (config/chat-topics/).
 * Una implementación por idioma, igual que los folders fonéticos: sin ella,
 * las entradas con `forms` quedan literales.
 */
interface TopicInflection
{
    /**
     * @param string $kind 'noun' (número), 'adj' (número y género) o 'verb' (conjugación)
     * @return array<int,string> la forma base y sus formas regulares, sin repetir
     */
    public function forms(string $lemma, string $kind): array;
}

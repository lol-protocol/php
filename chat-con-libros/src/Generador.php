<?php
declare(strict_types=1);

namespace ChatLibros;

/**
 * Algo que redacta una respuesta en lenguaje natural a partir de los pasajes que encontró
 * la búsqueda. La implementación incluida es GeneradorClaude; en las pruebas se usa una falsa.
 */
interface Generador
{
    /**
     * @param list<array{capitulo:string, texto:string}> $pasajes los pasajes del libro que respaldan la respuesta
     * @param list<array{pregunta:string, respuesta:string}> $historial turnos anteriores, del más viejo al más nuevo
     * @throws GeneradorError si no se pudo obtener la respuesta (clave mala, sin red, límite de uso…)
     */
    public function generar(string $titulo, string $autor, string $pregunta, array $pasajes, array $historial = []): string;
}

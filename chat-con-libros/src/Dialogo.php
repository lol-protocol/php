<?php
declare(strict_types=1);

namespace ChatLibros;

/**
 * Una conversación: decide, para cada pregunta, si responde por reglas, buscando en el libro o
 * (si hay un Generador) redactando con un modelo de lenguaje a partir de los pasajes encontrados.
 *
 * Con IA, el modelo solo se llama cuando la búsqueda encontró pasajes: si el libro no habla de
 * eso, se dice «no encuentro» sin gastar una llamada y sin darle pie a inventar. Si la llamada
 * falla, se muestra la búsqueda normal con un aviso.
 */
final class Dialogo
{
    /** Pasajes que se le dan al modelo como mucho. */
    public const MAX_PASAJES_IA = 4;

    /** Turnos anteriores que se le dan al modelo como mucho. */
    public const MAX_HISTORIAL = 3;

    public function __construct(
        private readonly Conversador $conversador,
        private readonly ?Generador $generador = null,
    ) {
    }

    public function iaActiva(): bool
    {
        return $this->generador !== null;
    }

    public function libro(): Libro
    {
        return $this->conversador->libro();
    }

    /**
     * @param list<array{pregunta:string, respuesta:string}> $historial turnos anteriores, del más viejo al más nuevo
     * @return array{texto:string, modo:string, citas:list<array{capitulo:string, texto:string}>, aviso?:string}
     *         modo: «reglas» (saludo, autor, resumen…), «busqueda» (pasajes citados) o «ia»
     */
    public function responder(string $pregunta, array $historial = []): array
    {
        $regla = $this->conversador->intencion($pregunta);
        if ($regla !== null) {
            return ['texto' => $regla, 'modo' => 'reglas', 'citas' => []];
        }
        if ($this->generador === null) {
            return ['texto' => $this->conversador->responder($pregunta), 'modo' => 'busqueda', 'citas' => []];
        }

        $historial = array_slice($historial, -self::MAX_HISTORIAL);
        $pasajes = $this->conversador->pasajesPara(self::consulta($pregunta, $historial), self::MAX_PASAJES_IA);
        if ($pasajes === []) {
            return ['texto' => $this->conversador->noEncuentro(), 'modo' => 'busqueda', 'citas' => []];
        }

        $citas = array_map(
            static fn (array $p): array => ['capitulo' => $p['capitulo'], 'texto' => Conversador::extracto($p['texto'], 500)],
            $pasajes,
        );
        try {
            $libro = $this->conversador->libro();
            $texto = $this->generador->generar($libro->titulo, $libro->autor, $pregunta, $citas, $historial);
        } catch (GeneradorError $e) {
            return [
                'texto' => $this->conversador->responder($pregunta),
                'modo' => 'busqueda',
                'citas' => [],
                'aviso' => 'No pude consultar a Claude: ' . $e->getMessage() . ' Te muestro lo que dice el libro.',
            ];
        }
        return ['texto' => $texto, 'modo' => 'ia', 'citas' => $citas];
    }

    /**
     * Qué buscar en el libro: una pregunta corta («¿y la hermana?») se completa con la anterior,
     * porque sola no alcanza para encontrar nada.
     *
     * @param list<array{pregunta:string, respuesta:string}> $historial
     */
    private static function consulta(string $pregunta, array $historial): string
    {
        if ($historial !== [] && count(Normaliza::terminos($pregunta)) < 3) {
            return $historial[array_key_last($historial)]['pregunta'] . ' ' . $pregunta;
        }
        return $pregunta;
    }
}

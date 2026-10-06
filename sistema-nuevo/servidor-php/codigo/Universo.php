<?php

declare(strict_types=1);

/**
 * El universo de comparación: contra quién se compara una acción. Los países, el rango de edad y el género de los
 * usuarios cuyas acciones forman el promedio, y el usuario que se está mirando, que queda afuera de su propia
 * comparación. Antes viajaba como cinco argumentos sueltos desde /api/timeline hasta el pedido al servicio de estadísticas.
 */
final class Universo
{
    /** @param string[]|null $paises null = sin filtro de país (todos los países) */
    public function __construct(
        public readonly ?array $paises,
        public readonly int $edadMin,
        public readonly int $edadMax,
        public readonly string $genero,
        public readonly ?string $excluirUsuario
    ) {
    }

    /**
     * Lo que el servicio de estadísticas (servicio-estadisticas-java, GET /stats) necesita saber del universo. El tipo de
     * acción no va: es de cada pedido. "countries" y "exclude" solo viajan si hay filtro de país o usuario que excluir.
     *
     * @return array<string,int|string>
     */
    public function aQuery(): array
    {
        $query = ['age_min' => $this->edadMin, 'age_max' => $this->edadMax, 'gender' => $this->genero];
        if ($this->paises !== null) {
            $query['countries'] = implode(',', $this->paises);
        }
        if ($this->excluirUsuario !== null) {
            $query['exclude'] = $this->excluirUsuario;
        }
        return $query;
    }
}

<?php

declare(strict_types=1);

namespace ParsingFbInMas;

/**
 * Lo que el cruce recuerda de la fuente B: por cada persona fallecida, solo lo que hace falta para
 * compararla (palabras plegadas del nombre, año, lugar, parientes con nombre) y una referencia corta. No
 * guarda el perfil entero, así que un archivo grande ocupa una fracción de lo que ocuparía en memoria.
 * Se llena con {@see Cruce::indexar()}.
 */
final class IndiceCruce
{
    /** @var list<array{palabras: list<string>, primero: ?string, anio: ?int, lugares: list<string>, parientes: list<string>, ref: array<string, mixed>}> */
    public array $fichas = [];

    /**
     * @var array<string, list<int>> nombre de pila plegado => posiciones en `$fichas`. Se indexa solo por el
     *      primer nombre porque el cruce exige que el de B esté en el nombre de A: así un apellido muy común
     *      no arrastra miles de comparaciones que luego se descartan.
     */
    public array $porPrimero = [];

    /** Cuántos perfiles se omitieron por poder estar vivos. */
    public int $omitidos = 0;
}

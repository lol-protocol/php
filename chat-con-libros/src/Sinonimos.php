<?php
declare(strict_types=1);

namespace ChatLibros;

/**
 * Grupos de sinónimos para ampliar la pregunta: quien pregunta por «el mensaje» encuentra
 * «la carta» aunque el libro nunca diga «mensaje».
 *
 * Formato (datos/sinonimos.tsv): una línea por grupo, palabras separadas por comas; las
 * líneas que empiezan con «#» son comentarios. Las palabras pasan por Normaliza::terminos,
 * así que valen con o sin acento y en singular o plural.
 *
 * Las palabras que agrega son las de los demás miembros del grupo, después de las que
 * escribió la persona, y pesan igual que ellas. Java hace lo mismo (Sinonimos.java).
 */
final class Sinonimos
{
    /** @var array<string,list<string>> raíz → raíces de los demás miembros de sus grupos */
    private array $companeros = [];

    public static function desdeTexto(string $texto): self
    {
        $sinonimos = new self();
        $sinonimos->agregarTexto($texto);
        return $sinonimos;
    }

    /** @param string|list<string> $rutas uno o varios archivos; los que no existan se saltan */
    public static function desdeArchivos(string|array $rutas): self
    {
        $sinonimos = new self();
        foreach ((array) $rutas as $ruta) {
            if (is_file($ruta)) {
                $sinonimos->agregarTexto((string) file_get_contents($ruta));
            }
        }
        return $sinonimos;
    }

    public function agregarTexto(string $texto): void
    {
        foreach (preg_split('/\r\n|\n|\r/', $texto) ?: [] as $linea) {
            $linea = trim($linea);
            if ($linea === '' || str_starts_with($linea, '#')) {
                continue;
            }
            $grupo = [];
            foreach (explode(',', $linea) as $palabra) {
                foreach (Normaliza::terminos($palabra) as $t) {
                    if (!in_array($t, $grupo, true)) {
                        $grupo[] = $t;
                    }
                }
            }
            foreach ($grupo as $t) {
                foreach ($grupo as $otro) {
                    if ($otro !== $t && !in_array($otro, $this->companeros[$t] ?? [], true)) {
                        $this->companeros[$t][] = $otro;
                    }
                }
            }
        }
    }

    /**
     * Los mismos términos más los de sus grupos, sin repetir los que ya estaban.
     *
     * @param list<string> $terminos
     * @return list<string>
     */
    public function expandir(array $terminos): array
    {
        $resultado = $terminos;
        $presentes = array_fill_keys($terminos, true);
        foreach ($terminos as $t) {
            foreach ($this->companeros[$t] ?? [] as $otro) {
                if (!isset($presentes[$otro])) {
                    $presentes[$otro] = true;
                    $resultado[] = $otro;
                }
            }
        }
        return $resultado;
    }
}

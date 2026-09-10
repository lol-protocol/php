<?php

declare(strict_types=1);

class AlmacenConfiguracion
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function obtener(string $clave): ?string
    {
        $stmt = $this->pdo->prepare('SELECT valor FROM configuracion_alertas WHERE clave = ?');
        $stmt->execute([$clave]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['valor'] : null;
    }

    public function guardar(string $clave, string $valor): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO configuracion_alertas (clave, valor) VALUES (?, ?)
             ON CONFLICT (clave) DO UPDATE SET valor = EXCLUDED.valor'
        );
        $stmt->execute([$clave, $valor]);
    }

    public function obtenerTodos(): array
    {
        $stmt = $this->pdo->query('SELECT clave, valor FROM configuracion_alertas ORDER BY clave');
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function esAlertaHabilitada(string $tipo): bool
    {
        $clave = 'alerta_' . $tipo;
        $valor = $this->obtener($clave);
        return $valor === 'true' || $valor === null; // Por defecto true si no existe
    }

    public function obtenerUmbral(): int
    {
        $valor = $this->obtener('umbral_sensibilidad');
        // OJO: NO usar "$valor ? ... : 50" -- el string "0" es falsy en PHP,
        // así que una sensibilidad guardada en 0 terminaría leyéndose como 50.
        return $valor !== null ? (int)$valor : 50;
    }
}

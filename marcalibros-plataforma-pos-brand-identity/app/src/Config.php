<?php

namespace MarcalibrosApp;

/** Ajustes de la aplicación. Se leen de variables de entorno; todas tienen un valor por defecto razonable. */
final class Config
{
    public function __construct(
        /** Carpeta del proyecto: contiene catalog/, images/ y svg/. */
        public readonly string $projectRoot,
        /** Dónde se guardan los marcalibros creados. Debe quedar fuera de public/. */
        public readonly string $storageDirectory,
        /** Días que se conserva un marcalibro creado. */
        public readonly int $retentionDays = 30,
        /** Intentos de crear un marcalibro por hora y por dirección IP. */
        public readonly int $createsPerHour = 10,
        /** Tope de marcalibros guardados a la vez, para no llenar el disco. */
        public readonly int $maxStored = 5000,
        /** Usar la última dirección de X-Forwarded-For. Solo detrás de un proxy propio que la ponga. */
        public readonly bool $trustForwardedFor = false,
        /**
         * Dirección pública del sitio, sin barra final ("https://marcalibros.example.com"). Hace falta
         * para que las vistas previas al compartir un enlace lleven imagen: esas etiquetas exigen
         * direcciones absolutas, y no se arman con la cabecera Host, que la puede escribir cualquiera.
         */
        public readonly ?string $publicUrl = null,
    ) {
        if ($retentionDays < 1 || $createsPerHour < 1 || $maxStored < 1) {
            throw new \InvalidArgumentException('Los días de conservación, los intentos por hora y el tope deben ser 1 o más.');
        }
        if ($publicUrl !== null && preg_match('#^https?://[a-z0-9.-]+(:[0-9]{1,5})?\z#i', $publicUrl) !== 1) {
            throw new \InvalidArgumentException('MARCALIBROS_PUBLIC_URL debe ser solo el esquema y el dominio, como https://marcalibros.example.com.');
        }
    }

    /** @param array<string, string|false> $env */
    public static function fromEnvironment(string $appDirectory, array $env): self
    {
        $int = static function (string $name, int $default) use ($env): int {
            $value = $env[$name] ?? false;
            if ($value === false || $value === '') {
                return $default;
            }
            if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                throw new \InvalidArgumentException("$name debe ser un número entero.");
            }
            return (int) $value;
        };

        $storage = $env['MARCALIBROS_STORAGE'] ?? false;
        $root = $env['MARCALIBROS_PROJECT_ROOT'] ?? false;

        return new self(
            projectRoot: rtrim(is_string($root) && $root !== '' ? $root : dirname($appDirectory), '/'),
            storageDirectory: rtrim(is_string($storage) && $storage !== '' ? $storage : $appDirectory . '/storage', '/'),
            retentionDays: $int('MARCALIBROS_RETENTION_DAYS', 30),
            createsPerHour: $int('MARCALIBROS_CREATES_PER_HOUR', 10),
            maxStored: $int('MARCALIBROS_MAX_STORED', 5000),
            trustForwardedFor: ($env['MARCALIBROS_TRUST_FORWARDED_FOR'] ?? false) === '1',
            publicUrl: is_string($env['MARCALIBROS_PUBLIC_URL'] ?? false) && $env['MARCALIBROS_PUBLIC_URL'] !== ''
                ? rtrim($env['MARCALIBROS_PUBLIC_URL'], '/')
                : null,
        );
    }
}

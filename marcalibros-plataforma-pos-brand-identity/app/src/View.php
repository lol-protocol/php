<?php

namespace MarcalibrosApp;

/** Genera las páginas: una plantilla de contenido dentro de la plantilla general. */
final class View
{
    /** Nombres de la paleta de la marca (brand-tokens.md), para mostrar los colores de cada marcalibro. */
    private const COLOR_NAMES = [
        '#1E2A38' => 'Tinta',
        '#F4EEE1' => 'Papel',
        '#C98B3A' => 'Ocre',
        '#3E5C4A' => 'Verde',
        '#7A9CB0' => 'Niebla',
    ];

    private const MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
        'septiembre', 'octubre', 'noviembre', 'diciembre'];

    /** @param array<string, mixed> $shared Datos que ven todas las plantillas. */
    public function __construct(private readonly string $templateDirectory, private readonly array $shared = [])
    {
    }

    /**
     * @param array<string, mixed> $data
     * @param array{private?: bool, scripts?: list<string>, section?: string, description?: string, share?: array<string, string|int>|null} $options
     *        private: página que no debe indexarse; scripts: archivos de public/assets/; section: pestaña activa del menú;
     *        share: datos de la vista previa al compartir el enlace (Open Graph).
     */
    public function page(string $template, array $data, string $title, array $options = []): string
    {
        $content = $this->render($template, $data);
        return $this->render('layout', $options + ['content' => $content, 'title' => $title, 'private' => false, 'scripts' => [], 'section' => '', 'description' => '', 'share' => null]);
    }

    /** @param array<string, mixed> $data */
    private function render(string $template, array $data): string
    {
        $file = "$this->templateDirectory/$template.php";
        $render = function () use ($file, $data): string {
            extract($data + $this->shared, EXTR_SKIP);
            ob_start();
            try {
                require $file;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }
            return (string) ob_get_clean();
        };
        return $render->call($this);
    }

    /** Escapa texto para HTML, también dentro de atributos. */
    public function e(?string $text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function colorName(string $hex): string
    {
        return self::COLOR_NAMES[strtoupper($hex)] ?? $hex;
    }

    /** Clase CSS de un color de la paleta (la política de seguridad no permite estilos en línea). */
    public function colorClass(string $hex): string
    {
        $name = self::COLOR_NAMES[strtoupper($hex)] ?? null;
        return $name === null ? 'muestra--otro' : 'muestra--' . strtolower($name);
    }

    /** Dirección de un archivo de public/assets/, con la fecha de modificación para que el navegador no use una copia vieja. */
    public function asset(string $name): string
    {
        $mtime = @filemtime(dirname($this->templateDirectory) . '/public/assets/' . $name);
        return '/assets/' . $name . ($mtime === false ? '' : '?v=' . $mtime);
    }

    /** "12 de noviembre de 2026" */
    public function date(int $timestamp): string
    {
        $date = new \DateTimeImmutable('@' . $timestamp);
        return $date->format('j') . ' de ' . self::MONTHS[(int) $date->format('n') - 1] . ' de ' . $date->format('Y');
    }

    /** @param array<string, string|int|null> $params */
    public function url(string $path, array $params = []): string
    {
        $params = array_filter($params, static fn ($v): bool => $v !== null && $v !== '');
        return $path . ($params === [] ? '' : '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
    }
}

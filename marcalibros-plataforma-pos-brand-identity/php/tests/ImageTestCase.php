<?php

namespace Tests;

use Marcalibros\BookmarkRejectedException;
use Marcalibros\RejectionReason;
use PHPUnit\Framework\TestCase;

/** Base de las pruebas con imágenes: carpeta temporal propia y ayudantes para crear archivos. */
abstract class ImageTestCase extends TestCase
{
    protected string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/marcalibros-test-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->dir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = "$dir/$item";
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /** Imagen de un solo color. */
    protected function solid(int $width, int $height, string $hex): \GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, $this->color($image, $hex));
        return $image;
    }

    /** Imagen partida en dos mitades: arriba y abajo, o izquierda y derecha. */
    protected function halves(int $width, int $height, string $first, string $second, bool $sideBySide): \GdImage
    {
        $image = $this->solid($width, $height, $first);
        $fill = $this->color($image, $second);
        if ($sideBySide) {
            imagefilledrectangle($image, intdiv($width, 2), 0, $width - 1, $height - 1, $fill);
        } else {
            imagefilledrectangle($image, 0, intdiv($height, 2), $width - 1, $height - 1, $fill);
        }
        return $image;
    }

    private function color(\GdImage $image, string $hex): int
    {
        $color = imagecolorallocate(
            $image,
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2))
        );
        self::assertNotFalse($color);
        return $color;
    }

    /** Codifica la imagen como 'jpeg', 'png', 'webp' o 'gif'. */
    protected function encode(\GdImage $image, string $format): string
    {
        ob_start();
        match ($format) {
            'jpeg' => imagejpeg($image, null, 90),
            'png' => imagepng($image),
            'webp' => imagewebp($image, null, 90),
            'gif' => imagegif($image),
        };
        return (string) ob_get_clean();
    }

    /** Guarda los bytes en la carpeta temporal y devuelve la ruta. */
    protected function put(string $name, string $bytes): string
    {
        $path = "$this->dir/$name";
        file_put_contents($path, $bytes);
        return $path;
    }

    /** Inserta un segmento (por ejemplo EXIF o un comentario) en un JPEG, tras el encabezado. */
    protected function withSegment(string $jpeg, string $marker, string $payload): string
    {
        $offset = 2;
        if (substr($jpeg, 2, 2) === "\xFF\xE0") {
            $offset = 4 + unpack('n', substr($jpeg, 4, 2))[1];
        }
        return substr($jpeg, 0, $offset) . $marker . pack('n', strlen($payload) + 2) . $payload . substr($jpeg, $offset);
    }

    /** Agrega al JPEG un EXIF que solo contiene la orientación de la cámara. */
    protected function withOrientation(string $jpeg, int $orientation): string
    {
        $tiff = 'MM' . pack('n', 42) . pack('N', 8) . pack('n', 1)
            . pack('nnN', 0x0112, 3, 1) . pack('nn', $orientation, 0) . pack('N', 0);
        return $this->withSegment($jpeg, "\xFF\xE1", "Exif\0\0" . $tiff);
    }

    /** @return array{int, int, int} rojo, verde y azul del píxel */
    protected function pixel(string $imageBytes, int $x, int $y): array
    {
        $image = imagecreatefromstring($imageBytes);
        self::assertInstanceOf(\GdImage::class, $image);
        $rgb = imagecolorat($image, $x, $y);
        return [($rgb >> 16) & 255, ($rgb >> 8) & 255, $rgb & 255];
    }

    /** @param array{int, int, int} $actual */
    protected function assertColor(string $hex, array $actual, int $tolerance = 14): void
    {
        $expected = [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
        foreach ($expected as $i => $channel) {
            $this->assertEqualsWithDelta($channel, $actual[$i], $tolerance, "Canal $i: se esperaba #$hex, salió " . implode(',', $actual));
        }
    }

    /** Comprueba que la función lance BookmarkRejectedException con ese motivo. */
    protected function assertRejected(RejectionReason $expected, callable $action): void
    {
        try {
            $action();
        } catch (BookmarkRejectedException $e) {
            $this->assertSame($expected, $e->reason, 'Motivo distinto del esperado.');
            return;
        }
        $this->fail('Se esperaba que se rechazara con ' . $expected->value . ', pero no se lanzó ninguna excepción.');
    }
}

<?php

namespace Marcalibros;

/**
 * Convierte una imagen ya inspeccionada en las dos versiones JPEG de un
 * marcalibro: 1400x200 y 2800x400 (proporción 7:1).
 *
 * Como la imagen se decodifica y se vuelve a codificar desde cero, la salida
 * no arrastra metadatos del original (EXIF, GPS, miniaturas, comentarios) ni
 * datos escondidos al final del archivo. La orientación del EXIF se aplica
 * antes de descartarlo, para que las fotos del celular no queden de lado.
 */
final class ImageNormalizer
{
    public const RATIO = 7;

    /** Tamaños de salida: densidad normal y pantallas de alta densidad. */
    public const SIZES = ['1x' => [1400, 200], '2x' => [2800, 400]];

    /** Relleno de las zonas transparentes: el color "papel" de la marca (#F4EEE1). */
    private const BACKGROUND = [244, 238, 225];

    public function __construct(private readonly Limits $limits = new Limits())
    {
    }

    /**
     * @return array<string, string> Bytes JPEG de cada tamaño, con las claves de SIZES.
     */
    public function render(ImageInfo $info, CropSettings $crop): array
    {
        $this->assertMemory($info);

        $image = $this->orient($this->flatten($this->decode($info), $info), $info);
        [$x, $y, $width, $height] = $this->cropBox(imagesx($image), imagesy($image), $crop);
        if ($width < $this->limits->minCropWidth) {
            throw new BookmarkRejectedException(RejectionReason::TooSmall);
        }

        $out = [];
        foreach (self::SIZES as $key => [$targetWidth, $targetHeight]) {
            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
            if ($canvas === false
                || !imagecopyresampled($canvas, $image, 0, 0, $x, $y, $targetWidth, $targetHeight, $width, $height)
            ) {
                throw new BookmarkRejectedException(RejectionReason::Undecodable);
            }
            imageinterlace($canvas, true);

            ob_start();
            $written = imagejpeg($canvas, null, $this->limits->jpegQuality);
            $bytes = ob_get_clean();
            if (!$written || !is_string($bytes) || $bytes === '') {
                throw new BookmarkRejectedException(RejectionReason::Undecodable);
            }
            $out[$key] = $bytes;
        }

        return $out;
    }

    /**
     * Calcula la zona de la imagen que se recorta: x, y, ancho y alto en píxeles.
     *
     * @return array{int, int, int, int}
     */
    public function cropBox(int $sourceWidth, int $sourceHeight, CropSettings $crop): array
    {
        if ($sourceWidth >= self::RATIO * $sourceHeight) {
            $baseHeight = (float) $sourceHeight;
            $baseWidth = self::RATIO * $baseHeight;
        } else {
            $baseWidth = (float) $sourceWidth;
            $baseHeight = $baseWidth / self::RATIO;
        }

        $width = max(1, min($sourceWidth, (int) round($baseWidth / $crop->zoom)));
        $height = max(1, min($sourceHeight, (int) round($width / self::RATIO)));
        $x = (int) round(($sourceWidth - $width) * $crop->offsetX);
        $y = (int) round(($sourceHeight - $height) * $crop->offsetY);

        return [$x, $y, $width, $height];
    }

    /** Falla si procesar la imagen podría pasarse de la memoria disponible. */
    private function assertMemory(ImageInfo $info): void
    {
        $budget = $this->limits->memoryBudget ?? $this->availableMemory();
        if ($budget === null) {
            return;
        }
        // 4 bytes por píxel y hasta dos copias a la vez, más un margen fijo.
        $needed = $info->width * $info->height * 4 * 2 + 4_194_304;
        if ($needed > $budget) {
            throw new BookmarkRejectedException(RejectionReason::TooManyPixels);
        }
    }

    private function availableMemory(): ?int
    {
        $limit = ini_get('memory_limit');
        if ($limit === false || $limit === '' || $limit === '-1') {
            return null;
        }
        return max(0, $this->toBytes($limit) - memory_get_usage(true));
    }

    private function toBytes(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;
        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function decode(ImageInfo $info): \GdImage
    {
        $image = match ($info->mime) {
            'image/jpeg' => @imagecreatefromjpeg($info->path),
            'image/png' => @imagecreatefrompng($info->path),
            'image/webp' => @imagecreatefromwebp($info->path),
            default => false,
        };
        if (!$image instanceof \GdImage) {
            throw new BookmarkRejectedException(RejectionReason::Undecodable);
        }
        return $image;
    }

    /** Pone las zonas transparentes sobre el color papel, para que no salgan negras en el JPEG. */
    private function flatten(\GdImage $image, ImageInfo $info): \GdImage
    {
        if ($info->mime === 'image/jpeg') {
            return $image;
        }

        $canvas = imagecreatetruecolor(imagesx($image), imagesy($image));
        if ($canvas === false) {
            throw new BookmarkRejectedException(RejectionReason::Undecodable);
        }
        $background = imagecolorallocate($canvas, ...self::BACKGROUND);
        if ($background === false || !imagefill($canvas, 0, 0, $background)) {
            throw new BookmarkRejectedException(RejectionReason::Undecodable);
        }
        imagealphablending($canvas, true);
        if (!imagecopy($canvas, $image, 0, 0, 0, 0, imagesx($image), imagesy($image))) {
            throw new BookmarkRejectedException(RejectionReason::Undecodable);
        }
        return $canvas;
    }

    /** Aplica la orientación que guardó la cámara en el EXIF (solo JPEG y si la extensión está). */
    private function orient(\GdImage $image, ImageInfo $info): \GdImage
    {
        if ($info->mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($info->path, 'IFD0');
        $orientation = is_array($exif) && isset($exif['Orientation']) ? (int) $exif['Orientation'] : 1;

        switch ($orientation) {
            case 2:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                return $image;
            case 3:
                return $this->rotate($image, 180);
            case 4:
                imageflip($image, IMG_FLIP_VERTICAL);
                return $image;
            case 5:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                return $this->rotate($image, 90);
            case 6:
                return $this->rotate($image, -90);
            case 7:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                return $this->rotate($image, -90);
            case 8:
                return $this->rotate($image, 90);
            default:
                return $image;
        }
    }

    /** Gira la imagen; un ángulo positivo gira en sentido contrario a las agujas del reloj. */
    private function rotate(\GdImage $image, int $degrees): \GdImage
    {
        $rotated = imagerotate($image, $degrees, 0);
        if ($rotated === false) {
            throw new BookmarkRejectedException(RejectionReason::Undecodable);
        }
        return $rotated;
    }
}

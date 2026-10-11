<?php

namespace Tests;

use Marcalibros\CropSettings;
use Marcalibros\ImageInspector;
use Marcalibros\ImageNormalizer;
use Marcalibros\Limits;
use Marcalibros\RejectionReason;
use PHPUnit\Framework\Attributes\DataProvider;

final class ImageNormalizerTest extends ImageTestCase
{
    /** @return array<string, mixed> bytes JPEG por tamaño */
    private function render(string $bytes, ?CropSettings $crop = null, ?Limits $limits = null): array
    {
        $limits ??= new Limits();
        $info = (new ImageInspector($limits))->inspect($this->put('entrada', $bytes));
        return (new ImageNormalizer($limits))->render($info, $crop ?? new CropSettings());
    }

    /** @return array<string, array{string}> */
    public static function formats(): array
    {
        return ['jpeg' => ['jpeg'], 'png' => ['png'], 'webp' => ['webp']];
    }

    #[DataProvider('formats')]
    public function testProducesBothSizesAsJpegForEveryFormat(string $format): void
    {
        $out = $this->render($this->encode($this->solid(2000, 1200, '336699'), $format));

        $this->assertSame(['1x', '2x'], array_keys($out));
        $small = getimagesizefromstring($out['1x']);
        $large = getimagesizefromstring($out['2x']);
        $this->assertSame([1400, 200, IMAGETYPE_JPEG], [$small[0], $small[1], $small[2]]);
        $this->assertSame([2800, 400, IMAGETYPE_JPEG], [$large[0], $large[1], $large[2]]);
    }

    public function testOutputKeepsNoMetadataNorDataAfterTheImage(): void
    {
        $jpeg = $this->encode($this->solid(2000, 1200, '336699'), 'jpeg');
        $jpeg = $this->withOrientation($jpeg, 1);
        $jpeg = $this->withSegment($jpeg, "\xFF\xFE", 'ubicacion-secreta-33S-70W');
        $jpeg .= 'DATOS-ESCONDIDOS-AL-FINAL';

        $out = $this->render($jpeg);

        foreach ($out as $bytes) {
            $this->assertStringNotContainsString("Exif\0\0", $bytes);
            $this->assertStringNotContainsString('ubicacion-secreta', $bytes);
            $this->assertStringNotContainsString('DATOS-ESCONDIDOS', $bytes);
        }
    }

    public function testTheOrientationHelperWritesAnExifThatPhpCanRead(): void
    {
        if (!function_exists('exif_read_data')) {
            $this->markTestSkipped('Falta la extensión exif.');
        }
        $path = $this->put('o.jpg', $this->withOrientation($this->encode($this->solid(100, 100, 'ff0000'), 'jpeg'), 6));

        $exif = exif_read_data($path, 'IFD0');

        $this->assertSame(6, $exif['Orientation'] ?? null);
    }

    /** @return array<string, array{int, bool, string}> orientación, mitades lado a lado, color que debe quedar arriba */
    public static function orientations(): array
    {
        return [
            'sin giro: arriba rojo' => [1, false, 'ff0000'],
            '180 grados: arriba pasa a ser azul' => [3, false, '0000ff'],
            '90 horario: la izquierda roja pasa arriba' => [6, true, 'ff0000'],
            '90 antihorario: la derecha azul pasa arriba' => [8, true, '0000ff'],
        ];
    }

    #[DataProvider('orientations')]
    public function testAppliesTheCameraOrientation(int $orientation, bool $sideBySide, string $expectedTop): void
    {
        if (!function_exists('exif_read_data')) {
            $this->markTestSkipped('Falta la extensión exif.');
        }
        $jpeg = $this->encode($this->halves(1000, 1000, 'ff0000', '0000ff', $sideBySide), 'jpeg');

        // offsetY 0: se recorta la franja de arriba de la imagen ya girada.
        $out = $this->render($this->withOrientation($jpeg, $orientation), new CropSettings(1.0, 0.5, 0.0));

        $this->assertColor($expectedTop, $this->pixel($out['1x'], 700, 100));
    }

    public function testDefaultCropIsCenteredAndKeepsTheFullHeightOfAWideImage(): void
    {
        // 3000 x 300: izquierda roja, derecha azul. La franja central de 2100 x 300 cae entre x 450 y 2550.
        $out = $this->render($this->encode($this->halves(3000, 300, 'ff0000', '0000ff', true), 'png'));

        $this->assertColor('ff0000', $this->pixel($out['1x'], 100, 100));
        $this->assertColor('0000ff', $this->pixel($out['1x'], 1300, 100));
    }

    public function testZoomAndOffsetChooseTheRegion(): void
    {
        $png = $this->encode($this->halves(3000, 300, 'ff0000', '0000ff', true), 'png');

        $left = $this->render($png, new CropSettings(2.0, 0.0, 0.5));
        $right = $this->render($png, new CropSettings(2.0, 1.0, 0.5));

        $this->assertColor('ff0000', $this->pixel($left['1x'], 700, 100));
        $this->assertColor('ff0000', $this->pixel($left['1x'], 1390, 100));
        $this->assertColor('0000ff', $this->pixel($right['1x'], 700, 100));
        $this->assertColor('0000ff', $this->pixel($right['1x'], 10, 100));
    }

    /** @return array<string, array{int, int}> */
    public static function tooSmall(): array
    {
        return [
            'ancha pero baja' => [600, 100],
            'vertical angosta' => [300, 2000],
        ];
    }

    #[DataProvider('tooSmall')]
    public function testRejectsImagesTooSmallForTheCrop(int $width, int $height): void
    {
        $jpeg = $this->encode($this->solid($width, $height, '336699'), 'jpeg');

        $this->assertRejected(RejectionReason::TooSmall, fn () => $this->render($jpeg));
    }

    public function testZoomCanMakeTheCropTooSmall(): void
    {
        $jpeg = $this->encode($this->solid(1400, 400, '336699'), 'jpeg');

        $this->assertRejected(RejectionReason::TooSmall, fn () => $this->render($jpeg, new CropSettings(3.0)));
    }

    public function testTransparentPixelsBecomeThePaperColor(): void
    {
        $image = imagecreatetruecolor(1000, 300);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));

        $out = $this->render($this->encode($image, 'png'));

        $this->assertColor('F4EEE1', $this->pixel($out['1x'], 700, 100), 6);
    }

    public function testStopsBeforeUsingMoreMemoryThanTheBudgetAllows(): void
    {
        $jpeg = $this->encode($this->solid(800, 200, '336699'), 'jpeg');

        $this->assertRejected(
            RejectionReason::TooManyPixels,
            fn () => $this->render($jpeg, null, new Limits(memoryBudget: 500_000))
        );
    }

    public function testRejectsACorruptImageThatPassesTheHeaderCheck(): void
    {
        $png = $this->encode($this->solid(1000, 300, '336699'), 'png');
        $truncated = substr($png, 0, (int) (strlen($png) / 2));

        $this->assertRejected(RejectionReason::Undecodable, fn () => $this->render($truncated));
    }

    /** @return array<string, array{int, int, float}> */
    public static function cropShapes(): array
    {
        return [
            'muy ancha' => [3000, 300, 1.0],
            'cuadrada' => [1000, 1000, 1.0],
            'vertical' => [800, 2000, 1.0],
            'con zoom' => [2000, 1200, 2.5],
            'justo 7:1' => [1400, 200, 1.0],
        ];
    }

    #[DataProvider('cropShapes')]
    public function testCropBoxIsSevenToOneAndInsideTheImage(int $width, int $height, float $zoom): void
    {
        foreach ([[0.0, 0.0], [0.5, 0.5], [1.0, 1.0]] as [$offsetX, $offsetY]) {
            [$x, $y, $w, $h] = (new ImageNormalizer())->cropBox($width, $height, new CropSettings($zoom, $offsetX, $offsetY));

            $this->assertGreaterThanOrEqual(0, $x);
            $this->assertGreaterThanOrEqual(0, $y);
            $this->assertLessThanOrEqual($width, $x + $w);
            $this->assertLessThanOrEqual($height, $y + $h);
            // El alto se redondea a un píxel entero, así que la proporción puede variar hasta 3.5 / alto.
            $this->assertEqualsWithDelta(7.0, $w / $h, 3.5 / $h + 0.01);
        }
    }
}

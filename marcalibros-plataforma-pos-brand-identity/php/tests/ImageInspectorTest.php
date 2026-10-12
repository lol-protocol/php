<?php

namespace Tests;

use Marcalibros\ImageInspector;
use Marcalibros\Limits;
use Marcalibros\RejectionReason;
use PHPUnit\Framework\Attributes\DataProvider;

final class ImageInspectorTest extends ImageTestCase
{
    /** @return array<string, array{string, string}> */
    public static function acceptedFormats(): array
    {
        return [
            'jpeg' => ['jpeg', 'image/jpeg'],
            'png' => ['png', 'image/png'],
            'webp' => ['webp', 'image/webp'],
        ];
    }

    #[DataProvider('acceptedFormats')]
    public function testAcceptsJpegPngAndWebp(string $format, string $mime): void
    {
        $path = $this->put("foto.$format", $this->encode($this->solid(800, 400, '336699'), $format));

        $info = (new ImageInspector())->inspect($path);

        $this->assertSame($mime, $info->mime);
        $this->assertSame([800, 400], [$info->width, $info->height]);
    }

    public function testDecidesByContentAndIgnoresTheExtension(): void
    {
        $path = $this->put('parece-jpg.jpg', $this->encode($this->solid(300, 300, '336699'), 'png'));

        $this->assertSame('image/png', (new ImageInspector())->inspect($path)->mime);
    }

    public function testRejectsSvgEvenIfItIsNamedAsAPng(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.cookie)</script></svg>';
        $path = $this->put('dibujo.png', $svg);

        $this->assertRejected(RejectionReason::UnsupportedType, fn () => (new ImageInspector())->inspect($path));
    }

    public function testRejectsPhpCodeNamedAsAnImage(): void
    {
        $path = $this->put('foto.jpg', '<?php system($_GET["c"]);');

        $this->assertRejected(RejectionReason::UnsupportedType, fn () => (new ImageInspector())->inspect($path));
    }

    public function testRejectsGif(): void
    {
        $path = $this->put('animacion.gif', $this->encode($this->solid(50, 50, 'ff0000'), 'gif'));

        $this->assertRejected(RejectionReason::UnsupportedType, fn () => (new ImageInspector())->inspect($path));
    }

    public function testRejectsAFileThatOnlyStartsLikeAJpeg(): void
    {
        $path = $this->put('roto.jpg', "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00" . str_repeat("\x01", 300));

        $this->assertRejected(RejectionReason::NotAnImage, fn () => (new ImageInspector())->inspect($path));
    }

    public function testRejectsAnEmptyFile(): void
    {
        $path = $this->put('vacio.jpg', '');

        $this->assertRejected(RejectionReason::EmptyFile, fn () => (new ImageInspector())->inspect($path));
    }

    public function testRejectsAFileLargerThanTheLimit(): void
    {
        $path = $this->put('grande.jpg', $this->encode($this->solid(400, 400, '336699'), 'jpeg'));

        $this->assertRejected(
            RejectionReason::TooLarge,
            fn () => (new ImageInspector(new Limits(maxBytes: 100)))->inspect($path)
        );
    }

    public function testRejectsMoreThanTheMaximumPixelsEvenIfTheFileIsSmall(): void
    {
        // Un PNG de un solo color pesa muy poco, pero al decodificarlo ocupa memoria por cada píxel.
        $path = $this->put('bomba.png', $this->encode($this->solid(2000, 2000, 'ffffff'), 'png'));

        $this->assertLessThan(100_000, filesize($path));
        $this->assertRejected(
            RejectionReason::TooManyPixels,
            fn () => (new ImageInspector(new Limits(maxPixels: 1_000_000)))->inspect($path)
        );
    }

    public function testRejectsAMissingFileAndADirectory(): void
    {
        $this->assertRejected(RejectionReason::Unreadable, fn () => (new ImageInspector())->inspect("$this->dir/no-existe.jpg"));
        $this->assertRejected(RejectionReason::Unreadable, fn () => (new ImageInspector())->inspect($this->dir));
    }
}

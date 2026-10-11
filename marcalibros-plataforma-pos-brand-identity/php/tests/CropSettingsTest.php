<?php

namespace Tests;

use Marcalibros\CropSettings;
use Marcalibros\RejectionReason;
use PHPUnit\Framework\Attributes\DataProvider;

final class CropSettingsTest extends ImageTestCase
{
    public function testDefaultsAreAnUnzoomedCenteredCrop(): void
    {
        $crop = new CropSettings();

        $this->assertSame([1.0, 0.5, 0.5], [$crop->zoom, $crop->offsetX, $crop->offsetY]);
    }

    /** @return array<string, array{float, float, float}> */
    public static function invalid(): array
    {
        return [
            'zoom menor que 1' => [0.5, 0.5, 0.5],
            'zoom pasado del máximo' => [CropSettings::MAX_ZOOM + 0.1, 0.5, 0.5],
            'zoom no es un número' => [NAN, 0.5, 0.5],
            'zoom infinito' => [INF, 0.5, 0.5],
            'x negativo' => [1.0, -0.1, 0.5],
            'x mayor que 1' => [1.0, 1.1, 0.5],
            'y negativo' => [1.0, 0.5, -0.1],
            'y mayor que 1' => [1.0, 0.5, 1.1],
            'y no es un número' => [1.0, 0.5, NAN],
        ];
    }

    #[DataProvider('invalid')]
    public function testRejectsValuesOutOfRange(float $zoom, float $x, float $y): void
    {
        $this->assertRejected(RejectionReason::InvalidCrop, fn () => new CropSettings($zoom, $x, $y));
    }

    public function testFromArrayAcceptsNumbersWrittenAsText(): void
    {
        $crop = CropSettings::fromArray(['zoom' => '2.5', 'x' => '0', 'y' => 1]);

        $this->assertSame([2.5, 0.0, 1.0], [$crop->zoom, $crop->offsetX, $crop->offsetY]);
    }

    public function testFromArrayUsesDefaultsForMissingOrEmptyValuesAndIgnoresOtherKeys(): void
    {
        $crop = CropSettings::fromArray(['zoom' => '', 'y' => null, 'otra' => 'cosa']);

        $this->assertSame([1.0, 0.5, 0.5], [$crop->zoom, $crop->offsetX, $crop->offsetY]);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function badInput(): array
    {
        return [
            'texto' => [['zoom' => 'mucho']],
            'arreglo' => [['x' => [1]]],
            'booleano' => [['y' => true]],
            'fuera de rango' => [['zoom' => '9']],
        ];
    }

    /** @param array<string, mixed> $input */
    #[DataProvider('badInput')]
    public function testFromArrayRejectsAnythingThatIsNotANumberInRange(array $input): void
    {
        $this->assertRejected(RejectionReason::InvalidCrop, fn () => CropSettings::fromArray($input));
    }
}

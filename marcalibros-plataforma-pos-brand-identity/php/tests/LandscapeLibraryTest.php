<?php

namespace Tests;

use Marcalibros\LandscapeLibrary;
use Marcalibros\RejectionReason;
use PHPUnit\Framework\Attributes\DataProvider;

final class LandscapeLibraryTest extends ImageTestCase
{
    private string $images;

    protected function setUp(): void
    {
        parent::setUp();
        $this->images = dirname(__DIR__, 2) . '/images';
    }

    public function testListsTheLandscapesOfTheBrand(): void
    {
        $names = (new LandscapeLibrary($this->images))->names();

        $this->assertNotEmpty($names);
        $this->assertContains('paisaje-bosque-1920x1080.jpg', $names);
        foreach ($names as $name) {
            $this->assertMatchesRegularExpression('/^paisaje-[a-z]+-\d+x\d+\.jpg$/', $name);
        }
    }

    public function testReturnsThePathOfAnExistingImage(): void
    {
        $path = (new LandscapeLibrary($this->images))->path('paisaje-costa-1200x630.jpg');

        $this->assertFileExists($path);
    }

    /** @return array<string, array{string}> */
    public static function forbiddenNames(): array
    {
        return [
            'sube de carpeta' => ['../images/paisaje-bosque-1920x1080.jpg'],
            'ruta absoluta' => ['/etc/passwd'],
            'barra al final' => ['paisaje-bosque-1920x1080.jpg/'],
            'otra mayúscula' => ['PAISAJE-BOSQUE-1920X1080.JPG'],
            'byte nulo' => ["paisaje-bosque-1920x1080.jpg\0.png"],
            'no existe' => ['paisaje-desierto-1920x1080.jpg'],
            'vacío' => [''],
        ];
    }

    #[DataProvider('forbiddenNames')]
    public function testRejectsAnyNameThatIsNotInTheList(string $name): void
    {
        $this->assertRejected(RejectionReason::UnknownLibraryImage, fn () => (new LandscapeLibrary($this->images))->path($name));
    }

    public function testIgnoresFilesThatDoNotFollowTheNamingRule(): void
    {
        foreach (['paisaje-ok-10x10.jpg', 'paisaje-Mal-10x10.jpg', 'otro.jpg', 'paisaje-x-1.png', 'paisaje-a-b.jpg'] as $name) {
            $this->put($name, 'x');
        }

        $this->assertSame(['paisaje-ok-10x10.jpg'], (new LandscapeLibrary($this->dir))->names());
    }
}

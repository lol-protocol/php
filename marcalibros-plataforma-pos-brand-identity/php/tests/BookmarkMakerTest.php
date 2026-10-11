<?php

namespace Tests;

use Marcalibros\Bookmark;
use Marcalibros\BookmarkMaker;
use Marcalibros\CropSettings;
use Marcalibros\LandscapeLibrary;
use Marcalibros\RejectionReason;

final class BookmarkMakerTest extends ImageTestCase
{
    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = "$this->dir/guardados"; // no existe todavía: BookmarkMaker debe crearla
    }

    private function upload(?string $bytes = null): string
    {
        return $this->put('subida.jpg', $bytes ?? $this->encode($this->solid(2400, 1600, '336699'), 'jpeg'));
    }

    /** @return list<string> */
    private function storedFiles(): array
    {
        return is_dir($this->storage) ? array_values(array_diff(scandir($this->storage) ?: [], ['.', '..'])) : [];
    }

    public function testCreatesBothSizesFromAnUpload(): void
    {
        $bookmark = (new BookmarkMaker($this->storage))->fromUpload($this->upload());

        $this->assertMatchesRegularExpression('/^mp-[0-9a-f]{16}$/', $bookmark->id);
        $this->assertSame(Bookmark::SOURCE_UPLOAD, $bookmark->source);
        $this->assertSame([1400, 200], [$bookmark->width, $bookmark->height]);
        $this->assertSame(["$bookmark->id.jpg", "$bookmark->id@2x.jpg"], array_values($bookmark->files));

        $small = getimagesize("$this->storage/{$bookmark->files['1x']}");
        $large = getimagesize("$this->storage/{$bookmark->files['2x']}");
        $this->assertSame([1400, 200, IMAGETYPE_JPEG], [$small[0], $small[1], $small[2]]);
        $this->assertSame([2800, 400, IMAGETYPE_JPEG], [$large[0], $large[1], $large[2]]);
    }

    public function testLeavesNoTemporaryFilesBehind(): void
    {
        (new BookmarkMaker($this->storage))->fromUpload($this->upload());

        $this->assertCount(2, $this->storedFiles());
        foreach ($this->storedFiles() as $name) {
            $this->assertStringStartsNotWith('.tmp-', $name);
        }
    }

    public function testCleansTheLabelAndExposesTheBookmarkAsAnArray(): void
    {
        $bookmark = (new BookmarkMaker($this->storage))->fromUpload($this->upload(), new CropSettings(), "  Mi\nlibro  ");

        $this->assertSame('Mi libro', $bookmark->label);
        $this->assertSame(['id', 'source', 'label', 'files', 'width', 'height'], array_keys($bookmark->toArray()));
    }

    public function testEveryBookmarkGetsItsOwnId(): void
    {
        $maker = new BookmarkMaker($this->storage);

        $first = $maker->fromUpload($this->upload());
        $second = $maker->fromUpload($this->upload());

        $this->assertNotSame($first->id, $second->id);
        $this->assertCount(4, $this->storedFiles());
    }

    public function testUsesTheCropChosenByTheUser(): void
    {
        $png = $this->encode($this->halves(3000, 300, 'ff0000', '0000ff', true), 'png');

        $bookmark = (new BookmarkMaker($this->storage))->fromUpload($this->upload($png), new CropSettings(2.0, 1.0, 0.5));

        $this->assertColor('0000ff', $this->pixel((string) file_get_contents("$this->storage/{$bookmark->files['1x']}"), 700, 100));
    }

    public function testCreatesFromAnImageOfTheLibrary(): void
    {
        $library = new LandscapeLibrary(dirname(__DIR__, 2) . '/images');

        $bookmark = (new BookmarkMaker($this->storage, $library))->fromLibraryImage('paisaje-costa-1920x1080.jpg');

        $this->assertSame(Bookmark::SOURCE_LIBRARY, $bookmark->source);
        $this->assertCount(2, $this->storedFiles());
    }

    public function testRejectsAnImageThatIsNotInTheLibraryWithoutWritingAnything(): void
    {
        $maker = new BookmarkMaker($this->storage, new LandscapeLibrary(dirname(__DIR__, 2) . '/images'));

        $this->assertRejected(RejectionReason::UnknownLibraryImage, fn () => $maker->fromLibraryImage('../../etc/passwd'));
        $this->assertSame([], $this->storedFiles());
    }

    public function testNeedsALibraryToUseLibraryImages(): void
    {
        $this->expectException(\LogicException::class);

        (new BookmarkMaker($this->storage))->fromLibraryImage('paisaje-costa-1920x1080.jpg');
    }

    public function testADangerousUploadLeavesNothingStored(): void
    {
        $svg = $this->upload('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->assertRejected(RejectionReason::UnsupportedType, fn () => (new BookmarkMaker($this->storage))->fromUpload($svg));
        $this->assertSame([], $this->storedFiles());
    }

    public function testAnInvalidLabelIsRejectedBeforeProcessingTheImage(): void
    {
        $this->assertRejected(
            RejectionReason::InvalidLabel,
            fn () => (new BookmarkMaker($this->storage))->fromUpload($this->upload(), new CropSettings(), "\xC3\x28")
        );
        $this->assertSame([], $this->storedFiles());
    }

    public function testReportsAStorageFailureInsteadOfFailingSilently(): void
    {
        $notADirectory = $this->put('archivo.txt', 'x');

        $this->assertRejected(
            RejectionReason::StorageFailed,
            fn () => (new BookmarkMaker("$notADirectory/dentro"))->fromUpload($this->upload())
        );
    }
}

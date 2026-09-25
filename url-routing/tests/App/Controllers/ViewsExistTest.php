<?php

declare(strict_types=1);

namespace Tests\App\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * Every view name a controller renders must have a file under views/ —
 * otherwise the route resolves correctly but the page is "View not found".
 */
class ViewsExistTest extends TestCase
{
    private const APP_DIR = __DIR__ . '/../../..';

    public function testEveryReferencedViewHasAFile(): void
    {
        $controllers = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::APP_DIR . '/Controllers', \FilesystemIterator::SKIP_DOTS)
        );

        $referenced = [];
        foreach ($controllers as $file) {
            preg_match_all("~'((?:genealogy|pos)/[a-z_]+/[a-z_]+)'~", (string)file_get_contents($file->getPathname()), $m);
            foreach ($m[1] as $view) {
                $referenced[$view] = true;
            }
        }

        $this->assertNotEmpty($referenced, 'No view references found — has the naming scheme changed?');

        $missing = array_values(array_filter(
            array_keys($referenced),
            static fn(string $view) => !is_file(self::APP_DIR . "/views/{$view}.php")
        ));

        $this->assertSame([], $missing, 'Views referenced by controllers but missing on disk');
    }
}

<?php

namespace PhoneDirectory\Parser;

final class LineReader
{
    /**
     * Yield a file's lines one at a time so memory stays flat regardless of file size.
     *
     * @return \Generator<int, string>
     */
    public static function read(string $filePath): \Generator
    {
        $handle = @fopen($filePath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Could not read file: {$filePath}");
        }

        try {
            while (($line = fgets($handle)) !== false) {
                yield $line;
            }
        } finally {
            fclose($handle);
        }
    }
}

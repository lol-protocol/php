<?php

namespace PhoneDirectory;

use PhoneDirectory\Entity\PhoneDirectoryEntry;

final class RecordLink
{
    /** @param string[] $evidence */
    public function __construct(
        public readonly PhoneDirectoryEntry $earlier,
        public readonly PhoneDirectoryEntry $later,
        public readonly float $score,
        public readonly array $evidence
    ) {
    }
}

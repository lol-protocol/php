<?php

namespace PhoneDirectory\Parser;

/**
 * What one line of a block says, worked out once: its phone, its street (with or without the record's name
 * already read) and, for a phone line, the name beside the phone. BlockReader decides from these alone, so
 * no line is matched against the same patterns twice.
 */
final class LineFacts
{
    private function __construct(
        public readonly string $line,
        public readonly ?string $phone,
        /** A street by its street word or a house number first (StreetRules::strong). */
        public readonly ?string $strongStreet,
        /** A street that only counts once the name is read (StreetRules::afterName). */
        public readonly ?string $afterNameStreet,
        /** For a phone line, what could be a name beside the phone ("SMITH, John ........ 555-123-4567"). */
        public readonly ?string $nameBesidePhone,
    ) {
    }

    /** $phoneRegex: PhonePattern::regexFor($language), passed in so it is built once per directory. */
    public static function of(string $line, string $language, string $phoneRegex): self
    {
        $phone = PhonePattern::find($line, $phoneRegex);

        return new self(
            $line,
            $phone,
            StreetRules::strong($line, $language),
            StreetRules::afterName($line, $language),
            $phone === null ? null : PhonePattern::nameBesidePhone(PhonePattern::repairOcrDigits($line), $phone),
        );
    }

    /** The street this line gives, depending on whether the record's name has been read. */
    public function street(bool $afterName): ?string
    {
        return $this->strongStreet ?? ($afterName ? $this->afterNameStreet : null);
    }

    /** The name this line can give: a line of text, or what a phone line has beside its phone. */
    public function name(bool $afterName): ?string
    {
        if ($this->street($afterName) !== null) {
            return null;
        }

        return $this->phone === null ? $this->line : $this->nameBesidePhone;
    }
}

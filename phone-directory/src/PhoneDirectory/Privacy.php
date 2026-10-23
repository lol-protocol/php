<?php

namespace PhoneDirectory;

use PhoneDirectory\Entity\PhoneDirectoryEntry;

/**
 * What a reader may see of the people in a directory who may still be alive.
 *
 * A directory entry carries a name, an address and a phone number, and no birth date. What it does
 * tell is when the directory was published, and a subscriber was an adult then: nobody in a
 * directory of year Y is taken to be younger than MINIMUM_SUBSCRIBER_AGE in Y, so the youngest they
 * can be is born in Y - 18. That person is presumed alive until LIVING_YEARS (110) years after birth,
 * the same line `url-routing`'s Privacidad draws, so an entry is old enough to show only when
 * Y - 18 + 110 is already in the past. In 2026 that is every directory up to 1933: the whole
 * 1934-1975 half of the catalog is hidden from a visitor.
 *
 * One thing lifts that: a person recorded as dead (PhoneDirectoryEntry::getDeceasedYear()) is no longer
 * someone who "may be alive", whatever year the directory came out. Only the owner of the database can
 * record a death, and has to say where it is documented (PhoneDirectoryPDODatabase::markDeceased()).
 *
 * An entry whose directory has no known year (no source, an id outside the catalog that embeds no
 * year) is hidden too. `url-routing` goes the other way for people with no dates, because hiding every
 * undated ancestor would hollow out a catalog of the dead; here the data is contact details, and a
 * mistake publishes a living person's phone number.
 *
 * Two views, as in `url-routing`:
 *  - forVisitors(): what may be published. It is the default of every class that returns entries, so
 *    forgetting to say who is asking hides too much, never too little.
 *  - forOwner(): everything, for whoever holds the database (the command-line tool, a local analysis).
 *
 * Only natural persons are covered. A JuridicalEntity is a business, not a person.
 */
final class Privacy
{
    /** Someone born less than this many years ago may still be alive. */
    public const LIVING_YEARS = 110;

    /** A directory lists adult subscribers: nobody in one is taken to be younger than this when it came out. */
    public const MINIMUM_SUBSCRIBER_AGE = 18;

    /**
     * @param int|null $latestPublishableYear the latest directory year whose entries may be shown; null
     *        means nothing is hidden (the owner). One field, so "hides" and "has a limit" cannot disagree.
     */
    private function __construct(
        private readonly ?int $latestPublishableYear,
        private readonly PhoneDirectoryCatalog $catalog,
    ) {
    }

    /**
     * What a visitor sees. $today exists so tests do not depend on the date they run on.
     */
    public static function forVisitors(?\DateTimeImmutable $today = null, ?PhoneDirectoryCatalog $catalog = null): self
    {
        $today ??= new \DateTimeImmutable('today');

        // Y - 18 + 110 < this year, by whole years: a directory from the last day of Y could list
        // someone who turned 18 that day, so a year that merely equals the limit is not old enough.
        $latest = (int) $today->format('Y') - (self::LIVING_YEARS - self::MINIMUM_SUBSCRIBER_AGE) - 1;

        return new self($latest, $catalog ?? new PhoneDirectoryCatalog());
    }

    /** What the owner of the database sees: everything. */
    public static function forOwner(): self
    {
        return new self(null, new PhoneDirectoryCatalog());
    }

    public function hidesLiving(): bool
    {
        return $this->latestPublishableYear !== null;
    }

    /** The latest directory year whose entries may be shown; null when nothing is hidden. */
    public function latestPublishableYear(): ?int
    {
        return $this->latestPublishableYear;
    }

    /** Whether the person in this entry may still be alive, and so must not be shown. */
    public function mayBeAlive(PhoneDirectoryEntry $entry): bool
    {
        $latest = $this->latestPublishableYear;
        if ($latest === null || $entry->getDeceasedYear() !== null) {
            return false;
        }

        $year = $this->catalog->yearOfDirectory($entry->getSourceDirectoryId());

        return $year === null || $year > $latest;
    }

    public function allows(PhoneDirectoryEntry $entry): bool
    {
        return !$this->mayBeAlive($entry);
    }

    /**
     * The entries that may be shown, in their order.
     *
     * @param iterable<PhoneDirectoryEntry> $entries
     * @return list<PhoneDirectoryEntry>
     */
    public function filter(iterable $entries): array
    {
        $shown = [];
        foreach ($entries as $entry) {
            if ($this->allows($entry)) {
                $shown[] = $entry;
            }
        }

        return $shown;
    }
}

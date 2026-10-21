<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\TestCase;
use PhoneDirectory\Entity\JuridicalEntity;
use PhoneDirectory\Entity\PhoneDirectoryEntry;
use PhoneDirectory\Manager\PhoneDirectoryManagerV2;
use PhoneDirectory\PhoneDirectoryPDODatabase;
use PhoneDirectory\Privacy;

class PhoneDirectoryManagerV2Test extends TestCase
{
    private PhoneDirectoryManagerV2 $manager;

    /** A manager whose natural-person database reads everything stored (the default one hides what may be a living person). */
    private PhoneDirectoryManagerV2 $owner;

    protected function setUp(): void
    {
        $this->manager = new PhoneDirectoryManagerV2();
        $this->manager->getNaturalDatabase()->createTable();
        $this->manager->getJuridicalDatabase()->createTable();

        $this->owner = self::ownerManager();
    }

    protected function tearDown(): void
    {
        $this->owner->disconnect();
    }

    private static function ownerManager(): PhoneDirectoryManagerV2
    {
        return new PhoneDirectoryManagerV2(
            naturalDatabase: new PhoneDirectoryPDODatabase('sqlite::memory:', privacy: Privacy::forOwner())
        );
    }

    public function testStatisticsWithoutJuridicalEntities(): void
    {
        $this->manager->addNaturalPerson(new PhoneDirectoryEntry('SMITH, John', 'US', '123 Main Street'));

        $stats = $this->manager->getStatistics();

        $this->assertSame(1, $stats['total']);
        $this->assertSame(1, $stats['natural_people']);
        $this->assertNull($stats['ratio_natural_to_juridical']);
    }

    public function testStatisticsOnEmptyDirectory(): void
    {
        $stats = $this->manager->getStatistics();

        $this->assertSame(0, $stats['total']);
        $this->assertNull($stats['ratio_natural_to_juridical']);
    }

    public function testAddNaturalPersonWorksWithoutCallingProcessFileFirst(): void
    {
        // Only processFile() used to create both tables; every other method failed with "no such
        // table" the first time it was called on a fresh manager.
        $manager = new PhoneDirectoryManagerV2();

        $id = $manager->addNaturalPerson(new PhoneDirectoryEntry('SMITH, John', 'US', '123 Main Street'));

        $this->assertGreaterThan(0, $id);
        $this->assertSame(1, $manager->getTotalCount());
    }

    public function testFindNaturalPeopleByNameWorksWithoutCallingProcessFileFirst(): void
    {
        $this->assertSame([], self::ownerManager()->findNaturalPeopleByName('Smith'));
    }

    public function testAddJuridicalEntityWorksWithoutCallingProcessFileFirst(): void
    {
        $manager = new PhoneDirectoryManagerV2();

        $id = $manager->addJuridicalEntity(new JuridicalEntity(businessName: 'Farmacia Central', street: 'Calle Luna 3'));

        $this->assertGreaterThan(0, $id);
    }

    public function testProcessFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phone_');
        $content = <<<TXT
        ANDERSON, John
        123 Main Street
        555-123-4567

        BAKER, Sarah
        456 Oak Avenue
        555-234-5678

        GARCIA, María
        789 Maple Road
        TXT;

        file_put_contents($tempFile, $content);

        try {
            $result = $this->owner->processFile($tempFile);
        } finally {
            unlink($tempFile);
        }

        $this->assertSame(3, $result['totalParsed']);
        $this->assertGreaterThan(0, $result['totalInserted']);
        $this->assertSame($result['naturalInserted'] + $result['juridicalInserted'], $result['totalInserted']);
        $this->assertSame($result['naturalInserted'], $this->owner->getTotalNaturalPeopleCount());
    }

    public function testGetNaturalPerson(): void
    {
        $id = $this->owner->addNaturalPerson(new PhoneDirectoryEntry('JOHNSON, Mary', 'US', '456 Oak Avenue'));

        $found = $this->owner->getNaturalPerson($id);

        $this->assertNotNull($found);
        $this->assertEquals('Johnson, Mary', $found->getFormattedName());
    }

    public function testFindNaturalPeopleByName(): void
    {
        $this->owner->addNaturalPerson(new PhoneDirectoryEntry('WILLIAMS, Robert', 'US', '321 Pine Drive'));
        $this->owner->addNaturalPerson(new PhoneDirectoryEntry('WILLIAMS, Patricia', 'US', '654 Elm Lane'));

        $this->assertCount(2, $this->owner->findNaturalPeopleByName('WILLIAMS'));
    }

    public function testFindNaturalPeopleByStreet(): void
    {
        $this->owner->addNaturalPerson(new PhoneDirectoryEntry('MILLER, Betty', 'US', '246 Spruce Lane'));
        $this->owner->addNaturalPerson(new PhoneDirectoryEntry('WILSON, Charles', 'US', '246 Spruce Lane'));
        $this->owner->addNaturalPerson(new PhoneDirectoryEntry('MOORE, Dorothy', 'US', '467 Birch Avenue'));

        $this->assertCount(2, $this->owner->findNaturalPeopleByStreet('246 Spruce Lane'));
    }

    public function testFindNaturalPersonByPhone(): void
    {
        $this->owner->addNaturalPerson(new PhoneDirectoryEntry(
            fullName: 'TAYLOR, George',
            countryCode: 'US',
            street: '654 Walnut Road',
            phoneNumber: '555-567-8901'
        ));

        $found = $this->owner->findNaturalPersonByPhone('555-567-8901');

        $this->assertNotNull($found);
        $this->assertEquals('Taylor, George', $found->getFormattedName());
    }

    public function testGetAllNaturalPeople(): void
    {
        $this->owner->addNaturalPerson(new PhoneDirectoryEntry('DAVIS, Jennifer', 'US', '987 Chestnut Avenue'));
        $this->owner->addNaturalPerson(new PhoneDirectoryEntry('THOMPSON, Edward', 'US', '246 Hickory Street'));
        $this->owner->addNaturalPerson(new PhoneDirectoryEntry('JACKSON, Susan', 'US', '890 Poplar Lane'));

        $this->assertCount(3, $this->owner->getAllNaturalPeople());
    }

    public function testSearchNaturalPeopleByCriteria(): void
    {
        $this->owner->addNaturalPerson(new PhoneDirectoryEntry('WHITE, Donald', 'US', '123 Sycamore Street', '555-789-0123'));
        $this->owner->addNaturalPerson(new PhoneDirectoryEntry('HARRIS, Christine', 'US', '456 Laurel Avenue', '555-890-1234'));

        $this->assertCount(1, $this->owner->searchNaturalPeople(['name' => 'WHITE']));
    }

    public function testSearchRejectsTheOtherKindOfEntitysCriteria(): void
    {
        // 'businessName' is a juridical criterion: it used to be dropped and every person came back.
        $this->owner->addNaturalPerson(new PhoneDirectoryEntry('WHITE, Donald', 'US', '123 Sycamore Street'));

        $this->expectException(\InvalidArgumentException::class);
        $this->owner->searchNaturalPeople(['businessName' => 'White']);
    }

    public function testUpdateNaturalPerson(): void
    {
        $id = $this->owner->addNaturalPerson(new PhoneDirectoryEntry('MARTIN, Michael', 'US', '789 Magnolia Road'));

        $updated = new PhoneDirectoryEntry(
            fullName: 'MARTIN, Michael S.',
            countryCode: 'US',
            street: '789 Magnolia Road, Apt. 10',
            id: $id
        );

        $this->assertTrue($this->owner->updateNaturalPerson($updated));
        $this->assertSame('MARTIN, Michael S.', $this->owner->getNaturalPerson($id)?->getRawName());
    }

    public function testDeleteNaturalPerson(): void
    {
        $id = $this->owner->addNaturalPerson(new PhoneDirectoryEntry('THOMAS, Richard', 'US', '567 Locust Boulevard'));

        $this->assertNotNull($this->owner->getNaturalPerson($id));

        $this->assertTrue($this->owner->deleteNaturalPerson($id));
        $this->assertNull($this->owner->getNaturalPerson($id));
    }

    public function testTotalCount(): void
    {
        $this->assertSame(0, $this->owner->getTotalCount());

        $this->owner->addNaturalPerson(new PhoneDirectoryEntry('ANDERSON, John', 'US', '123 Main Street'));
        $this->assertSame(1, $this->owner->getTotalCount());

        $this->owner->addNaturalPerson(new PhoneDirectoryEntry('BAKER, Sarah', 'US', '456 Oak Avenue'));
        $this->assertSame(2, $this->owner->getTotalCount());
        $this->assertSame(2, $this->owner->getTotalNaturalPeopleCount());
    }
}

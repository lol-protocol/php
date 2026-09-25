<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\TestCase;
use PhoneDirectory\Entity\JuridicalEntity;
use PhoneDirectory\Entity\PhoneDirectoryEntry;
use PhoneDirectory\Manager\PhoneDirectoryManagerV2;

class PhoneDirectoryManagerV2Test extends TestCase
{
    private PhoneDirectoryManagerV2 $manager;

    protected function setUp(): void
    {
        $this->manager = new PhoneDirectoryManagerV2();
        $this->manager->getNaturalDatabase()->createTable();
        $this->manager->getJuridicalDatabase()->createTable();
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
    }

    public function testAddJuridicalEntityWorksWithoutCallingProcessFileFirst(): void
    {
        $manager = new PhoneDirectoryManagerV2();

        $id = $manager->addJuridicalEntity(new JuridicalEntity(businessName: 'Farmacia Central', street: 'Calle Luna 3'));

        $this->assertGreaterThan(0, $id);
    }
}

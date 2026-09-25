<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use PHPUnit\Framework\TestCase;

class TermExplanationTest extends TestCase
{
    private DefamatoryContentReviewer $reviewer;

    protected function setUp(): void
    {
        $this->reviewer = DefamatoryContentReviewer::create(__DIR__ . '/../config', 'spa');
    }

    public function testFusionExplainsWhichNameAndWhichEntry(): void
    {
        [$explanation] = $this->reviewer->validateFullName('Elba', 'Gina')->getExplanations();

        $this->assertStringContainsString('«Elba Gina»', $explanation);
        $this->assertStringContainsString('«vagina»', $explanation);
        $this->assertStringContainsString('diccionario spa', $explanation);
    }

    public function testVariantExplainsTheDictionarySpelling(): void
    {
        [$explanation] = $this->reviewer->validateFullName('Paco', 'Cojes')->getExplanations();

        $this->assertStringContainsString('«Cojes» suena igual que «coges»', $explanation);
    }

    public function testNameCollisionIsCalledOut(): void
    {
        [$explanation] = $this->reviewer->validateFullName('Juan', 'Cerda')->getExplanations();

        $this->assertStringContainsString('apellido real documentado', $explanation);
    }

    public function testCleanNameHasNoExplanationsAndReportCarriesThem(): void
    {
        $this->assertSame([], $this->reviewer->validateFullName('María', 'García')->getExplanations());

        $result = $this->reviewer->validateFullName('Elba', 'Gina');
        $this->assertSame($result->getExplanations(), $result->toArray()['explanations']);
    }
}

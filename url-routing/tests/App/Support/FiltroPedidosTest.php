<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\FiltroPedidos;
use PHPUnit\Framework\TestCase;

/** The marks the defamation filter leaves on the text of a pedido de retiro, in Spanish and in English. */
class FiltroPedidosTest extends TestCase
{
    protected function setUp(): void
    {
        if (!is_file(dirname(__DIR__, 4) . '/defamatory-content-review/src/Chat/ChatLineReviewer.php')) {
            $this->markTestSkipped('defamatory-content-review is not next to url-routing');
        }
    }

    public function testMarksAnAccusationAndAnInsultInEitherLanguage(): void
    {
        $this->assertSame('insulto, acusación de delito', FiltroPedidos::marcas('Ese hombre es un ladrón y una estafa.'));
        $this->assertSame('insulto, accusation of a crime', FiltroPedidos::marcas('He is a thief and a fraud.'));
    }

    public function testATextWithNothingToMarkLeavesNothing(): void
    {
        $this->assertNull(FiltroPedidos::marcas('Quiero que retiren la foto de mi abuela.'));
    }

    public function testReusingTheReviewersAcrossCallsDoesNotCarryOneTextIntoTheNext(): void
    {
        $this->assertNotNull(FiltroPedidos::marcas('Es un ladrón.'));
        $this->assertNull(FiltroPedidos::marcas('Hola.'), 'the reviewers are built once per process: no state may stay in them');
        $this->assertSame(FiltroPedidos::marcas('Es un ladrón.'), FiltroPedidos::marcas('Es un ladrón.'));
    }
}

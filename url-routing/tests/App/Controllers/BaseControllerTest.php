<?php

declare(strict_types=1);

namespace Tests\App\Controllers;

use App\Controllers\BaseController;
use PHPUnit\Framework\TestCase;

/** Exposes BaseController's protected helpers for direct testing. */
final class TestableBaseController extends BaseController
{
    public function callValidateId(mixed $id): string|null
    {
        return $this->validateId($id);
    }

    public function callHandleBadRequest(string $message = 'Bad Request'): string
    {
        return $this->handleBadRequest($message);
    }
}

class BaseControllerTest extends TestCase
{
    private TestableBaseController $controller;

    protected function setUp(): void
    {
        $this->controller = new TestableBaseController();
    }

    public function testValidateIdAcceptsNumericString(): void
    {
        $this->assertSame('123', $this->controller->callValidateId('123'));
    }

    public function testValidateIdRejectsNonNumeric(): void
    {
        $this->assertNull($this->controller->callValidateId('abc'));
        $this->assertNull($this->controller->callValidateId(null));
    }

    public function testHandleBadRequestEscapesMessage(): void
    {
        $html = $this->controller->callHandleBadRequest('<i>bad</i>');

        $this->assertStringContainsString('400', $html);
        $this->assertStringNotContainsString('<i>', $html);
    }
}

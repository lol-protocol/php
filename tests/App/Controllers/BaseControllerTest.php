<?php

declare(strict_types=1);

namespace Tests\App\Controllers;

use App\Controllers\BaseController;
use PHPUnit\Framework\TestCase;

/** Exposes BaseController's protected helpers for direct testing. */
final class TestableBaseController extends BaseController
{
    public function callRequireAuth(): bool
    {
        return $this->requireAuth();
    }

    public function callGetCurrentUserId(): int|null
    {
        return $this->getCurrentUserId();
    }

    public function callValidateResourceOwnership(int|string|null $resourceOwnerId): bool
    {
        return $this->validateResourceOwnership($resourceOwnerId);
    }

    public function callValidateId(mixed $id): string|null
    {
        return $this->validateId($id);
    }

    public function callHandleUnauthorized(string $message = 'Unauthorized'): string
    {
        return $this->handleUnauthorized($message);
    }

    public function callHandleForbidden(string $message = 'Forbidden'): string
    {
        return $this->handleForbidden($message);
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
        $_SESSION = [];
        $this->controller = new TestableBaseController();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testRequireAuthFailsWithoutSession(): void
    {
        $this->assertFalse($this->controller->callRequireAuth());
    }

    public function testRequireAuthSucceedsWithSession(): void
    {
        $_SESSION['user_id'] = 42;

        $this->assertTrue($this->controller->callRequireAuth());
    }

    public function testGetCurrentUserIdReturnsNullWhenLoggedOut(): void
    {
        $this->assertNull($this->controller->callGetCurrentUserId());
    }

    public function testGetCurrentUserIdReturnsSessionValue(): void
    {
        $_SESSION['user_id'] = 7;

        $this->assertSame(7, $this->controller->callGetCurrentUserId());
    }

    public function testValidateResourceOwnershipAcceptsMatchingOwner(): void
    {
        $_SESSION['user_id'] = 5;

        $this->assertTrue($this->controller->callValidateResourceOwnership(5));
    }

    public function testValidateResourceOwnershipRejectsMismatchedOwner(): void
    {
        $_SESSION['user_id'] = 5;

        $this->assertFalse($this->controller->callValidateResourceOwnership(6));
    }

    public function testValidateResourceOwnershipRejectsWhenLoggedOut(): void
    {
        $this->assertFalse($this->controller->callValidateResourceOwnership(5));
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

    public function testHandleUnauthorizedEscapesMessage(): void
    {
        $html = $this->controller->callHandleUnauthorized('<script>x</script>');

        $this->assertStringContainsString('401', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testHandleForbiddenEscapesMessage(): void
    {
        $html = $this->controller->callHandleForbidden('<b>no</b>');

        $this->assertStringContainsString('403', $html);
        $this->assertStringNotContainsString('<b>', $html);
    }

    public function testHandleBadRequestEscapesMessage(): void
    {
        $html = $this->controller->callHandleBadRequest('<i>bad</i>');

        $this->assertStringContainsString('400', $html);
        $this->assertStringNotContainsString('<i>', $html);
    }
}

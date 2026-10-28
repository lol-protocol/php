<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\SessionManager;
use PHPUnit\Framework\TestCase;

class SessionManagerTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testSetCsrfTokenGeneratesAndPersistsToken(): void
    {
        $manager = SessionManager::getInstance();

        $token = $manager->setCsrfToken();

        $this->assertNotEmpty($token);
        $this->assertSame($token, $_SESSION['csrf_token']);
        // Calling it again must not rotate the token mid-session.
        $this->assertSame($token, $manager->setCsrfToken());
    }

    public function testGetCsrfTokenReturnsNullWhenUnset(): void
    {
        $this->assertNull(SessionManager::getInstance()->getCsrfToken());
    }

    public function testValidateCsrfTokenAcceptsMatchingToken(): void
    {
        $manager = SessionManager::getInstance();
        $token = $manager->setCsrfToken();

        $this->assertTrue($manager->validateCsrfToken($token));
    }

    public function testValidateCsrfTokenRejectsWrongToken(): void
    {
        $manager = SessionManager::getInstance();
        $manager->setCsrfToken();

        $this->assertFalse($manager->validateCsrfToken('not-the-token'));
    }

    public function testValidateCsrfTokenRejectsWhenNoTokenStored(): void
    {
        $this->assertFalse(SessionManager::getInstance()->validateCsrfToken('anything'));
    }

    public function testIsValidReturnsTrueWithinTimeout(): void
    {
        $_SESSION['_session_started'] = time();

        $this->assertTrue(SessionManager::getInstance()->isValid());
    }

    public function testGetInstanceReturnsSameInstance(): void
    {
        $this->assertSame(SessionManager::getInstance(), SessionManager::getInstance());
    }

    public function testResumeDoesNothingForARequestWithoutTheSessionCookie(): void
    {
        $original = $_COOKIE;
        $_COOKIE = [];
        try {
            SessionManager::getInstance()->resume();

            $this->assertSame(PHP_SESSION_NONE, session_status(), 'no cookie, no session');
        } finally {
            $_COOKIE = $original;
        }
    }

    /**
     * The unit tests fill $_SESSION by hand. If ensureStarted() opened a real session there, it would replace what
     * they set up and leave a file behind on every run.
     */
    public function testEnsureStartedDoesNotOpenASessionOnTheBareCommandLine(): void
    {
        $this->assertSame('cli', PHP_SAPI);
        $this->assertArrayNotHasKey('REQUEST_METHOD', $_SERVER);
        $_SESSION = ['puesto_a_mano' => 1];

        SessionManager::getInstance()->ensureStarted();

        $this->assertSame(PHP_SESSION_NONE, session_status());
        $this->assertSame(['puesto_a_mano' => 1], $_SESSION, 'what the test put there is untouched');
    }
}

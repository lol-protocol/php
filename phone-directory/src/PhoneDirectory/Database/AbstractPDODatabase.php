<?php

namespace PhoneDirectory\Database;

use DefamatoryContentReview\AccentFolding;
use PhoneDirectory\Exception\InvalidEncodingException;
use PhoneDirectory\SqlDialect;
use PhoneDirectory\TextFolding;

abstract class AbstractPDODatabase
{
    use SearchableDatabase;

    protected ?\PDO $pdo = null;
    protected ?SqlDialect $dialect = null;
    protected string $dsn;
    protected ?string $username;
    protected ?string $password;

    public function __construct(string $dsn = 'sqlite::memory:', ?string $username = null, ?string $password = null)
    {
        $this->dsn = $dsn;
        $this->username = $username;
        $this->password = $password;
    }

    public function connect(): void
    {
        if ($this->pdo !== null) {
            return;
        }

        $this->pdo = SqlDialect::connect($this->dsn, $this->username, $this->password);
        $this->dialect = new SqlDialect($this->pdo);
    }

    public function disconnect(): void
    {
        $this->pdo = null;
        $this->dialect = null;
    }

    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }

    /**
     * @throws InvalidEncodingException If the text is not valid UTF-8
     */
    protected function fold(string $text): string
    {
        return TextFolding::fold($text);
    }
}

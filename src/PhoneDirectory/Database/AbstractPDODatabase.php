<?php

namespace PhoneDirectory\Database;

use DefamatoryContentReview\AccentFolding;

abstract class AbstractPDODatabase
{
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

    protected function fold(string $text): string
    {
        $lower = mb_strtolower($text, 'UTF-8');

        if (!mb_check_encoding($lower, 'UTF-8')) {
            throw new \RuntimeException('Invalid UTF-8 encoding in text after mb_strtolower');
        }

        $folded = AccentFolding::fold($lower);

        if (!mb_check_encoding($folded, 'UTF-8')) {
            throw new \RuntimeException('Invalid UTF-8 encoding in text after accent folding');
        }

        return $folded;
    }
}

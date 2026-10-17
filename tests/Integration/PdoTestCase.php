<?php

namespace Ttpryg\InventoryEngine\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

abstract class PdoTestCase extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = $this->createPdo();
        $this->resetSchema($this->pdo);
    }

    protected function createPdo(): PDO
    {
        $dsn = getenv('TEST_DB_DSN');

        if (is_string($dsn) && $dsn !== '') {
            $pdo = new PDO($dsn, getenv('TEST_DB_USER') ?: null, getenv('TEST_DB_PASS') ?: null);
        } else {
            $pdo = new PDO('sqlite::memory:');
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    private function resetSchema(PDO $pdo): void
    {
        $isMysql = in_array($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), ['mysql', 'mariadb'], true);

        if ($isMysql) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        }

        foreach (['stock_movements', 'stock_reservations', 'inventory_stocks', 'inventory_locations'] as $table) {
            $pdo->exec("DROP TABLE IF EXISTS {$table}");
        }

        if ($isMysql) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        $schemaFile = $isMysql ? 'schema.mysql.sql' : 'schema.sqlite.sql';
        $schema = file_get_contents(__DIR__.'/../../database/'.$schemaFile);

        if ($schema === false) {
            self::fail("Cannot read schema file: {$schemaFile}");
        }

        foreach ($this->splitSqlStatements($schema) as $statement) {
            $pdo->exec($statement);
        }
    }

    protected function rowCount(string $sql): int
    {
        $statement = $this->pdo->prepare($sql);

        if ($statement === false) {
            self::fail("Cannot prepare statement: {$sql}");
        }

        $statement->execute();
        $value = $statement->fetchColumn();

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @return list<string>
     */
    private function splitSqlStatements(string $sql): array
    {
        $lines = [];
        foreach (explode("\n", $sql) as $line) {
            if (str_starts_with(ltrim($line), '--')) {
                continue;
            }
            $lines[] = $line;
        }

        $statements = [];
        foreach (explode(';', implode("\n", $lines)) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $statements[] = $statement;
            }
        }

        return $statements;
    }
}

<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PDO;
use PDOException;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Si PHPUnit usa MySQL (phpunit.xml), crea la BD de pruebas si no existe.
     * Evita el error "Unknown database 'inventarios_testing'" en entornos Laragon.
     */
    public function createApplication()
    {
        $this->ensureMysqlTestingDatabaseExists();

        return parent::createApplication();
    }

    private function ensureMysqlTestingDatabaseExists(): void
    {
        if ($this->testingEnv('DB_CONNECTION') !== 'mysql') {
            return;
        }

        $database = $this->testingEnv('DB_DATABASE');
        if ($database === '' || $database === ':memory:') {
            return;
        }

        $host = $this->testingEnv('DB_HOST', '127.0.0.1');
        $port = $this->testingEnv('DB_PORT', '3306');
        $username = $this->testingEnv('DB_USERNAME', 'root');
        $password = $this->testingEnv('DB_PASSWORD', '');

        $safeDb = str_replace('`', '``', $database);
        $dsn = sprintf('mysql:host=%s;port=%s', $host, $port);

        try {
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec(
                "CREATE DATABASE IF NOT EXISTS `{$safeDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
        } catch (PDOException $e) {
            throw new RuntimeException(
                "No se pudo crear o acceder a la base de pruebas '{$database}'. "
                .'Compruebe que MySQL está en marcha y las credenciales en phpunit.xml (o cree la BD a mano: docs/PRUEBAS.md). '
                .'Detalle: '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    private function testingEnv(string $key, string $default = ''): string
    {
        // Priorizar getenv(): en Windows/CLI a veces phpunit.xml no rellena $_ENV (variables_order).
        $value = getenv($key);
        if ($value !== false) {
            return (string) $value;
        }
        if (array_key_exists($key, $_ENV)) {
            return (string) $_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER)) {
            return (string) $_SERVER[$key];
        }

        return $default;
    }
}

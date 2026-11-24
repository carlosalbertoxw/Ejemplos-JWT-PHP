<?php

declare(strict_types=1);

use Api\Data\Database;

/**
 * Pruebas de la creación y versión del esquema SQLite.
 */
final class DatabaseTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/jwt-db-test-' . bin2hex(random_bytes(6)) . '.sqlite';
    }

    public function testCreaElEsquemaUnaSolaVezYNoVuelveASembrar(): void
    {
        try {
            $first = (new Database($this->file))->pdo();
            $this->assertSame(1, (int) $first->query('PRAGMA user_version')->fetchColumn());
            $this->assertSame(2, (int) $first->query('SELECT COUNT(*) FROM users')->fetchColumn());

            // Una segunda conexión (otra petición) no repite la siembra.
            $second = (new Database($this->file))->pdo();
            $this->assertSame(2, (int) $second->query('SELECT COUNT(*) FROM users')->fetchColumn());
            $this->assertSame(4, (int) $second->query('SELECT COUNT(*) FROM products')->fetchColumn());
        } finally {
            $this->cleanUp();
        }
    }

    public function testUnaBaseAnteriorSinVersionSeMigraSinResembrar(): void
    {
        try {
            // Base creada por una versión anterior: tablas y datos, pero
            // user_version = 0 y la columna obsoleta refresh_tokens.created_at.
            $legacy = new \PDO('sqlite:' . $this->file);
            $legacy->exec(<<<SQL
                CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL UNIQUE,
                                    password_hash TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'User');
                CREATE TABLE refresh_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, token_hash TEXT NOT NULL UNIQUE,
                                             user_id INTEGER NOT NULL, expires_at INTEGER NOT NULL,
                                             revoked INTEGER NOT NULL DEFAULT 0, created_at INTEGER NOT NULL);
                INSERT INTO users (username, password_hash, role) VALUES ('solo', 'x', 'User');
            SQL);
            $legacy = null;

            $pdo = (new Database($this->file))->pdo();

            $this->assertSame(1, (int) $pdo->query('PRAGMA user_version')->fetchColumn());
            $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
            $columns = array_column($pdo->query('PRAGMA table_info(refresh_tokens)')->fetchAll(), 'name');
            $this->assertFalse(in_array('created_at', $columns, true));
        } finally {
            $this->cleanUp();
        }
    }

    private function cleanUp(): void
    {
        foreach ([$this->file, $this->file . '-wal', $this->file . '-shm'] as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }
}

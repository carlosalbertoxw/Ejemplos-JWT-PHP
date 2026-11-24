<?php

namespace Api\Data;

use Api\Core\PasswordHasher;

/**
 * Conexión PDO a SQLite. La primera vez crea el esquema y siembra los
 * datos de ejemplo (usuarios y productos).
 *
 * Cambiar a otra base de datos solo requiere modificar esta capa:
 * el resto de la aplicación trabaja contra los repositorios.
 */
final class Database
{
    /**
     * Versión del esquema, guardada en PRAGMA user_version. Cada petición
     * solo lee este número; el esquema se crea o migra únicamente cuando la
     * base tiene una versión menor. Un cambio de esquema futuro sube este
     * valor y agrega su paso en initialize().
     */
    private const SCHEMA_VERSION = 1;

    private \PDO $pdo;

    public function __construct(string $dbPath)
    {
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $this->pdo = new \PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        // Estos dos PRAGMA son por conexión (cada petición abre la suya).
        // busy_timeout hace que una conexión espere, en vez de fallar con
        // "database is locked", cuando otra está escribiendo.
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA busy_timeout = 5000');

        if ($this->schemaVersion() < self::SCHEMA_VERSION) {
            $this->initialize();
        }
    }

    public function pdo(): \PDO
    {
        return $this->pdo;
    }

    private function scalar(string $sql): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    private function schemaVersion(): int
    {
        return (int) $this->scalar('PRAGMA user_version');
    }

    /**
     * Crea o migra el esquema y siembra los datos en una sola transacción.
     *
     * BEGIN IMMEDIATE toma el bloqueo de escritura antes de releer la
     * versión: si dos primeras peticiones llegan a la vez, la segunda espera
     * y, al entrar, ve que ya no hay nada que hacer (sin sembrar dos veces).
     */
    private function initialize(): void
    {
        // WAL permite lecturas concurrentes con una escritura (Apache atiende
        // en paralelo). Es persistente en el archivo, así que basta fijarlo
        // una vez, y no se puede cambiar dentro de una transacción.
        $this->pdo->exec('PRAGMA journal_mode = WAL');

        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            if ($this->schemaVersion() < self::SCHEMA_VERSION) {
                // IF NOT EXISTS: también sirve para bases creadas por versiones
                // anteriores, que no tenían user_version.
                $this->createSchema();
                $this->migrate();
                $hasUsers = (int) $this->scalar('SELECT COUNT(*) FROM users') > 0;
                if (!$hasUsers) {
                    $this->seed();
                }
                $this->pdo->exec('PRAGMA user_version = ' . self::SCHEMA_VERSION);
            }
            $this->pdo->exec('COMMIT');
        } catch (\Throwable $e) {
            $this->pdo->exec('ROLLBACK');
            throw $e;
        }
    }

    private function createSchema(): void
    {
        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS users (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                username      TEXT    NOT NULL UNIQUE,
                password_hash TEXT    NOT NULL,
                role          TEXT    NOT NULL DEFAULT 'User'
            );

            CREATE TABLE IF NOT EXISTS refresh_tokens (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                token_hash TEXT    NOT NULL UNIQUE,
                user_id    INTEGER NOT NULL REFERENCES users(id),
                expires_at INTEGER NOT NULL,
                revoked    INTEGER NOT NULL DEFAULT 0
            );

            CREATE TABLE IF NOT EXISTS products (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                name        TEXT    NOT NULL,
                description TEXT    NOT NULL DEFAULT '',
                price       REAL    NOT NULL
            );

            -- Intentos fallidos de login/refresh (rate limiting).
            CREATE TABLE IF NOT EXISTS rate_limit_attempts (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                attempt_key  TEXT    NOT NULL,
                attempted_at INTEGER NOT NULL
            );

            CREATE INDEX IF NOT EXISTS idx_rate_limit_key_time
                ON rate_limit_attempts (attempt_key, attempted_at);
        SQL);
    }

    /**
     * Ajusta bases creadas por versiones anteriores. La columna
     * refresh_tokens.created_at nunca se leía y era NOT NULL sin valor por
     * defecto: si siguiera existiendo, los INSERT actuales fallarían.
     */
    private function migrate(): void
    {
        $stmt = $this->pdo->prepare('PRAGMA table_info(refresh_tokens)');
        $stmt->execute();
        $columns = $stmt->fetchAll();
        if (in_array('created_at', array_column($columns, 'name'), true)) {
            $this->pdo->exec('ALTER TABLE refresh_tokens DROP COLUMN created_at');
        }
    }

    private function seed(): void
    {
        $hasher = new PasswordHasher();

        $insertUser = $this->pdo->prepare(
            'INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)',
        );
        $insertUser->execute(['admin', $hasher->hash('Admin123!'), 'Admin']);
        $insertUser->execute(['user', $hasher->hash('User123!'), 'User']);

        $insertProduct = $this->pdo->prepare(
            'INSERT INTO products (name, description, price) VALUES (?, ?, ?)',
        );
        $insertProduct->execute(['Teclado mecánico', 'Switches rojos, retroiluminado', 89.99]);
        $insertProduct->execute(['Mouse inalámbrico', 'Sensor óptico de 16000 DPI', 45.50]);
        $insertProduct->execute(['Monitor 27"', 'Panel IPS, 144 Hz', 320.00]);
        $insertProduct->execute(['Dock USB-C', 'HDMI doble, carga de 100 W', 129.00]);
    }
}

<?php

namespace Api\Core;

/**
 * Resuelve la clave con la que se firman los JWT.
 *
 * - Si JWT_SECRET está definida, se usa (debe tener al menos 32 bytes).
 * - En producción (APP_ENV=production) es obligatoria: sin ella la API no
 *   arranca, en vez de firmar con una clave conocida.
 * - En desarrollo, si falta, se genera una clave aleatoria y se guarda en un
 *   archivo dentro de data/ (fuera del repositorio). Así no hay ninguna clave
 *   versionada y todas las peticiones (cada una es un proceso PHP distinto)
 *   comparten la misma clave.
 */
final class JwtSecret
{
    public const MIN_BYTES = 32;

    public static function resolve(string|false $fromEnv, string $environment, string $devSecretFile): string
    {
        if (is_string($fromEnv) && $fromEnv !== '') {
            if (strlen($fromEnv) < self::MIN_BYTES) {
                throw new \RuntimeException(
                    'JWT_SECRET debe tener al menos ' . self::MIN_BYTES . ' bytes'
                );
            }
            return $fromEnv;
        }

        if ($environment === 'production') {
            throw new \RuntimeException(
                'JWT_SECRET es obligatoria cuando APP_ENV=production'
            );
        }

        return self::loadOrCreate($devSecretFile);
    }

    private static function loadOrCreate(string $file): string
    {
        if (is_file($file)) {
            $secret = trim((string) file_get_contents($file));
            if (strlen($secret) >= self::MIN_BYTES) {
                return $secret;
            }
        }

        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $secret = bin2hex(random_bytes(self::MIN_BYTES));
        // LOCK_EX evita que dos peticiones simultáneas escriban a la vez; si
        // otra ya lo creó, se respeta su valor releyendo el archivo.
        $handle = fopen($file, 'c+');
        if ($handle === false) {
            throw new \RuntimeException("No se pudo crear el archivo de clave $file");
        }
        try {
            flock($handle, LOCK_EX);
            $existing = trim((string) stream_get_contents($handle));
            if (strlen($existing) >= self::MIN_BYTES) {
                return $existing;
            }
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $secret);
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
        @chmod($file, 0600);

        error_log('JWT_SECRET no está definida: se generó una clave de desarrollo en ' . $file);
        return $secret;
    }
}

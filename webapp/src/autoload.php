<?php

/**
 * Autoloader PSR-4 simple: mapea el namespace WebApp\ a la carpeta src/.
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'WebApp\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

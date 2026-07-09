<?php

/**
 * Autoloader PSR-4 simple: mapea el namespace Api\ a la carpeta src/.
 * Evita la necesidad de Composer manteniendo el ejemplo en PHP puro.
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'Api\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

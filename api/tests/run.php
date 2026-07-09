<?php

/**
 * Runner de las pruebas unitarias de la API.
 *
 * Uso:  php tests/run.php        (desde la carpeta api/)
 *       php api/tests/run.php    (desde la raíz del repositorio)
 *
 * Descubre todas las clases *Test.php de esta carpeta, ejecuta sus métodos
 * test* (cada uno sobre una instancia nueva, con setUp previo) y termina
 * con código distinto de 0 si alguna prueba falla.
 */

declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/TestCase.php';

foreach (glob(__DIR__ . '/*Test.php') as $file) {
    require_once $file;
}

$testClasses = array_filter(
    get_declared_classes(),
    fn (string $class): bool => is_subclass_of($class, TestCase::class),
);

$passed = 0;
$failed = 0;
$errors = [];
$start = microtime(true);

foreach ($testClasses as $class) {
    echo $class, PHP_EOL;
    foreach (get_class_methods($class) as $method) {
        if (!str_starts_with($method, 'test')) {
            continue;
        }

        try {
            /** @var TestCase $test */
            $test = new $class();
            (new ReflectionMethod($test, 'setUp'))->setAccessible(true);
            (new ReflectionMethod($test, 'setUp'))->invoke($test);
            $test->$method();
            $passed++;
            echo "  ✓ $method", PHP_EOL;
        } catch (AssertionFailedError $e) {
            $failed++;
            $errors[] = "$class::$method\n  " . str_replace("\n", "\n  ", $e->getMessage());
            echo "  ✗ $method (FALLÓ)", PHP_EOL;
        } catch (Throwable $e) {
            $failed++;
            $errors[] = "$class::$method\n  ERROR " . $e::class . ': ' . $e->getMessage()
                . "\n  en " . $e->getFile() . ':' . $e->getLine();
            echo "  ✗ $method (ERROR)", PHP_EOL;
        }
    }
}

echo PHP_EOL;
foreach ($errors as $error) {
    echo "FALLO: $error", PHP_EOL, PHP_EOL;
}

printf(
    "%d pruebas, %d correctas, %d fallidas (%.2f s)%s",
    $passed + $failed,
    $passed,
    $failed,
    microtime(true) - $start,
    PHP_EOL,
);

exit($failed > 0 ? 1 : 0);

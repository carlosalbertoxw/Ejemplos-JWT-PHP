<?php

/**
 * Estilo de código (https://cs.symfony.com). Se ejecuta en CI con el phar
 * oficial, sin Composer, igual que PHPStan:
 *   php php-cs-fixer.phar check --diff     (verifica)
 *   php php-cs-fixer.phar fix              (corrige)
 */

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__ . '/api', __DIR__ . '/webapp', __DIR__ . '/tests'])
    ->exclude(['data']);

return (new PhpCsFixer\Config())
    ->setRules(['@PER-CS' => true])
    ->setFinder($finder);

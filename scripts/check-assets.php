<?php

declare(strict_types=1);

use Pepperfm\Flashboard\UI\Assets\AssetManifestInspector;

require dirname(__DIR__).'/vendor/autoload.php';

$errors = (new AssetManifestInspector())->inspect(dirname(__DIR__).'/public/build', checkUnreferenced: true);

if ($errors !== []) {
    fwrite(STDERR, 'Invalid Flashboard asset distribution: '.implode(', ', $errors).PHP_EOL);
    exit(1);
}

echo 'Flashboard asset distribution verified.'.PHP_EOL;

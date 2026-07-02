<?php
/**
 * Reemplaza "FacturaScripts" por otra marca en todos los archivos .json del proyecto.
 * Ejecutar desde el navegador o por CLI: php importa/replace_brand_json.php
 */

$search      = 'FacturaScripts';
$replacement = 'Interiberica Pyme';
$baseDir     = dirname(__DIR__);   // sube un nivel: carpeta raíz del proyecto
$extensions  = ['json'];

// --- Buscar archivos ---
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

$changed  = [];
$errors   = [];
$scanned  = 0;

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }
    if (!in_array(strtolower($file->getExtension()), $extensions, true)) {
        continue;
    }

    $scanned++;
    $path    = $file->getRealPath();
    $content = file_get_contents($path);
    if ($content === false) {
        $errors[] = $path;
        continue;
    }

    // Reemplaza respetando mayúsculas/minúsculas exactas
    $new = str_replace($search, $replacement, $content);

    if ($new !== $content) {
        if (file_put_contents($path, $new) === false) {
            $errors[] = $path;
        } else {
            $changed[] = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $path);
        }
    }
}

// --- Salida ---
header('Content-Type: text/plain; charset=utf-8');

echo "=== Reemplazo: '$search' → '$replacement' ===" . PHP_EOL . PHP_EOL;
echo "Directorio base: $baseDir" . PHP_EOL;
echo "Archivos .json escaneados: $scanned" . PHP_EOL . PHP_EOL;

if (empty($changed)) {
    echo "No se encontraron coincidencias en ningún .json." . PHP_EOL;
} else {
    echo count($changed) . " archivo(s) modificado(s):" . PHP_EOL;
    foreach ($changed as $f) {
        echo "  ✔ $f" . PHP_EOL;
    }
}

if (!empty($errors)) {
    echo PHP_EOL . count($errors) . " error(es) al escribir:" . PHP_EOL;
    foreach ($errors as $f) {
        echo "  ✘ $f" . PHP_EOL;
    }
}

echo PHP_EOL . "Listo." . PHP_EOL;

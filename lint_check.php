<?php
$dirs = ['app', 'routes', 'config', 'Modules', 'bootstrap', 'resources', 'database', 'tests'];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    $dirIt = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $it = new RecursiveIteratorIterator($dirIt);
    foreach ($it as $file) {
        $pathname = $file->getPathname();
        if (strpos($pathname, 'vendor') !== false || strpos($pathname, 'node_modules') !== false) {
            continue;
        }
        if ($file->getExtension() === 'php') {
            $output = [];
            $returnVar = 0;
            exec('php -l "' . $pathname . '" 2>&1', $output, $returnVar);
            if ($returnVar !== 0) {
                echo "SYNTAX ERROR IN: " . $pathname . "\n";
                echo implode("\n", $output) . "\n\n";
            }
        }
    }
}
echo "LINT FINISHED\n";

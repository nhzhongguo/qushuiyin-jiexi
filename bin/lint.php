<?php

$root = dirname(__DIR__);
$directories = ['app', 'config', 'public'];
$files = [];

foreach ($directories as $directory) {
    $path = $root . DIRECTORY_SEPARATOR . $directory;
    if (!is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

sort($files);

// Detect PHP binary
$phpBinary = 'php';
if (PHP_OS_FAMILY === 'Windows') {
    // Try to find PHP in common locations
    $possiblePaths = [
        getenv('PHP_BINARY'),
        'C:/Users/冷漠不是神/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.1_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe',
        'C:/php/php.exe',
        'C:/Program Files/PHP/php.exe',
    ];
    foreach ($possiblePaths as $path) {
        if ($path && file_exists($path)) {
            $phpBinary = $path;
            break;
        }
    }
}

$failed = false;
foreach ($files as $file) {
    exec($phpBinary . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $exitCode);
    echo implode(PHP_EOL, $output), PHP_EOL;
    if ($exitCode !== 0) {
        $failed = true;
    }
    $output = [];
}

exit($failed ? 1 : 0);

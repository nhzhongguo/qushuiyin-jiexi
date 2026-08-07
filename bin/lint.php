<?php

$root = dirname(__DIR__);
if (!chdir($root)) {
    fwrite(STDERR, '无法切换到项目根目录: ' . $root . PHP_EOL);
    exit(1);
}
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
        PHP_BINARY,
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
    $output = [];
    $exitCode = 1;
    $lintPath = ltrim(substr($file, strlen($root)), '\\/');
    $process = proc_open([$phpBinary, '-l', $lintPath], [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);
    if (is_resource($process)) {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $output = array_filter(array_merge(
            explode(PHP_EOL, trim((string) $stdout)),
            explode(PHP_EOL, trim((string) $stderr))
        ));
    } else {
        $output[] = '无法启动 PHP 语法检查: ' . $file;
    }
    echo implode(PHP_EOL, $output), PHP_EOL;
    if ($exitCode !== 0) {
        $failed = true;
    }
}

exit($failed ? 1 : 0);

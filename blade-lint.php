<?php

/**
 * Compile Blade templates and syntax-check the result, without booting the application.
 *
 * Booting is what we are avoiding: the app's providers open a database connection, and gemilang's
 * database is on the VPS, so `php artisan` locally just hangs until it times out. The Blade compiler
 * itself needs nothing but a filesystem, so it is instantiated directly.
 *
 * Usage: php blade-lint.php resources/views/a.blade.php [more...]
 */
require __DIR__.'/vendor/autoload.php';

$files = array_slice($argv, 1);

if ($files === []) {
    fwrite(STDERR, "usage: php blade-lint.php <blade file> [...]\n");
    exit(2);
}

$cache = sys_get_temp_dir().'/blade-lint-'.getmypid();
@mkdir($cache, 0777, true);

$compiler = new Illuminate\View\Compilers\BladeCompiler(
    new Illuminate\Filesystem\Filesystem,
    $cache
);

$failed = 0;
$report = '';

foreach ($files as $file) {
    if (! is_file($file)) {
        $report .= str_pad($file, 46)." MISSING\n";
        $failed++;
        continue;
    }

    $tmp = $cache.'/'.md5($file).'.php';
    file_put_contents($tmp, $compiler->compileString(file_get_contents($file)));

    $result = trim((string) shell_exec('php -l '.escapeshellarg($tmp).' 2>&1'));
    $ok = str_contains($result, 'No syntax errors');

    $report .= str_pad($file, 46).($ok ? " OK\n" : " FAIL\n".$result."\n");

    if (! $ok) {
        $failed++;
    }

    @unlink($tmp);
}

@rmdir($cache);

// Written to a file as well as stdout: this shell wrapper swallows stdout, and a lint result nobody
// can read is the same as not having run it.
$report .= $failed === 0 ? "ALL OK\n" : "{$failed} FAILED\n";
file_put_contents(__DIR__.'/blade-lint.out', $report);
echo $report;

exit($failed === 0 ? 0 : 1);

<?php

if (!class_exists('Phar', false)) {
    throw new Exception('Phar is not accessible');
}
if (!Phar::canWrite()) {
    throw new Exception('can not write, run with -d phar.readonly=0');
}

$dir = dirname(__FILE__).DIRECTORY_SEPARATOR.'kernel3';
$pharName = 'kernel3.phar';

file_exists($pharName) && unlink($pharName);
file_exists($pharName.'.gz') && unlink($pharName.'.gz');

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
    RecursiveIteratorIterator::SELF_FIRST
);

$phar = new Phar($pharName, 0, $pharName);
$phar->buildFromIterator($iterator, $dir);
$gzPhar = $phar->compress(Phar::GZ);

// stubs
$stub = <<<'PHP'
<?php
if (in_array('phar', stream_get_wrappers()) && class_exists('Phar', 0)) {
    set_include_path('phar://'.__FILE__.PATH_SEPARATOR.get_include_path());
    include 'phar://'.__FILE__.'/kernel3.php';
    return;
} else {
    throw new Exception('Phar is not installed');
}
__HALT_COMPILER(); ?>
PHP;

$phar->setStub($stub);
$gzPhar->setStub($stub);

print 'Successfully created phar files.'.PHP_EOL;

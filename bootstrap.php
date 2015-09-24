<?php

defined('STARTED') || define('STARTED', true);

if (file_exists(__DIR__.'/kernel3.phar')) {
    require_once 'phar://'.__DIR__.'/kernel3.phar';
} else {
    require_once __DIR__.'/kernel3/kernel3.php';
}

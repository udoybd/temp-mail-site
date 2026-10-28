<?php
declare(strict_types=1);

define('TMS_ROOT', dirname(__DIR__));
define('TMS_STORAGE', getenv('TMS_STORAGE') ?: TMS_ROOT . '/storage');

require TMS_ROOT . '/vendor/autoload.php';

spl_autoload_register(function (string $class): void {
    if (strncmp($class, 'Tms\\', 4) === 0) {
        $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

date_default_timezone_set('UTC');

Tms\Config::load();

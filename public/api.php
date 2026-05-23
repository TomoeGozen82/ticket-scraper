<?php
declare(strict_types=1);

$autoload = static function (string $class): void {
    $prefix = 'TicketScraper\\';
    $base   = dirname(__DIR__) . '/src/';
    if (!str_starts_with($class, $prefix)) return;
    $file = $base . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) require $file;
};
spl_autoload_register($autoload);

use TicketScraper\Controller\ApiController;
(new ApiController())->fetch();

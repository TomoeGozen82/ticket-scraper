#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Ticket Scraper — CLI
 *
 * Usage:
 *   php bin/scraper.php <event-url> [seatgeek_client_id]
 *
 * Examples:
 *   php bin/scraper.php "https://www.vividseats.com/.../production/6204797"
 *   php bin/scraper.php "https://seatgeek.com/.../18119434" "your_client_id"
 *   SEATGEEK_CLIENT_ID=xxx php bin/scraper.php "https://seatgeek.com/..."
 */

$autoload = static function (string $class): void {
    $prefix = 'TicketScraper\\';
    $base   = dirname(__DIR__) . '/src/';
    if (!str_starts_with($class, $prefix)) return;
    $file = $base . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) require $file;
};
spl_autoload_register($autoload);

if (PHP_MAJOR_VERSION < 8 || (PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION < 1)) {
    fwrite(STDERR, "[ERROR] PHP 8.1 or higher is required.\n");
    exit(1);
}
if ($argc < 2) {
    fwrite(STDERR, "Usage: php bin/scraper.php <event-url> [client_id]\n");
    exit(1);
}

$url      = trim($argv[1]);
$clientId = trim($argv[2] ?? getenv('SEATGEEK_CLIENT_ID') ?: '');
$dataDome = trim($argv[3] ?? getenv('SEATGEEK_DATADOME')  ?: '');

if (filter_var($url, FILTER_VALIDATE_URL) === false) {
    fwrite(STDERR, "[ERROR] Not a valid URL: {$url}\n");
    exit(1);
}

use TicketScraper\Service\ScraperService;
use TicketScraper\Service\ConsoleFormatter;

$service   = new ScraperService();
$formatter = new ConsoleFormatter();

if (!$service->isSupportedUrl($url)) {
    fwrite(STDERR, "[ERROR] Unsupported platform. Supported: VividSeats, SeatGeek.\n");
    exit(2);
}

echo "Fetching: {$url}\n";

$result = $service->fetch($url, $clientId, $dataDome);

if (!$result['success']) {
    fwrite(STDERR, "[ERROR] " . ($result['error'] ?? 'Unknown error') . "\n");
    exit(3);
}

$formatter->render($result['tickets'], $url);

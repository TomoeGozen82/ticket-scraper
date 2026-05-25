<?php

declare(strict_types=1);

namespace TicketScraper\Service;

use TicketScraper\Model\Ticket;

/**
 * Calls the Node.js headless scraper and returns structured ticket data.
 */
class ScraperService
{
    private string $nodeScript;
    private string $node;

    public function __construct()
    {
        $this->nodeScript = dirname(__DIR__, 2) . '/node/scrape.js';
        $this->node       = trim(shell_exec('which node 2>/dev/null') ?? '');
    }

    public function isAvailable(): bool
    {
        return $this->node !== '' && file_exists($this->nodeScript);
    }

    /**
     * Fetch tickets for the given URL.
     *
     * @return array{ success: bool, tickets: Ticket[], eventTitle: string, platform: string, error?: string, needsClientId?: bool }
     */
    public function fetch(string $url, string $clientId = '', string $dataDome = ''): array
    {
        // The whole pipeline is wrapped so that *any* failure (a missing binary,
        // a crashed subprocess, malformed output) degrades into a controlled
        // payload instead of bubbling up as a PHP fatal error.
        try {
            if (!$this->isAvailable()) {
                throw new \RuntimeException('Node.js runtime or scraper script not found.');
            }

            $cmd = $this->node . ' ' . escapeshellarg($this->nodeScript)
                 . ' ' . escapeshellarg($url)
                 . ' ' . escapeshellarg($clientId)
                 . ' ' . escapeshellarg($dataDome)
                 . ' 2>/dev/null';

            $output = shell_exec($cmd);
            if ($output === null || trim((string) $output) === '') {
                throw new \RuntimeException('The scraper subprocess returned no output.');
            }

            $data = json_decode($output, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                throw new \RuntimeException('Could not parse the scraper response.');
            }

            // Hydrate raw rows into typed Ticket value objects.
            if (!empty($data['tickets']) && is_array($data['tickets'])) {
                $data['tickets'] = array_map(
                    static fn(array $t) => new Ticket(
                        (string) ($t['section'] ?? 'Unknown'),
                        ($t['row'] ?? '') ?: 'GA',
                        (float) ($t['price'] ?? 0),
                        (int)   ($t['quantity'] ?? 1),
                        (string) ($t['platform'] ?? ''),
                    ),
                    $data['tickets']
                );
            } else {
                $data['tickets'] = [];
            }

            return $data;
        } catch (\Throwable $e) {
            // Never let an exception escape the service layer.
            return ['success' => false, 'error' => $e->getMessage(), 'tickets' => []];
        }
    }

    public function isSupportedUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        return str_contains($host, 'vividseats.com') || str_contains($host, 'seatgeek.com');
    }

    /**
     * Operator-assisted ingestion: parse a SeatGeek listings payload that the
     * user copied from their own (trusted) browser session — the reliable way
     * to obtain data that DataDome blocks for automated clients.
     *
     * Accepts the full `event_listings_v2` response, a `{ "listings": [...] }`
     * object, or a bare array of listing objects.
     *
     * @return array{ success: bool, tickets: Ticket[], eventTitle: string, platform: string, error?: string }
     */
    public function parsePastedSeatGeek(string $json): array
    {
        try {
            $data = json_decode(trim($json), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('That is not valid JSON. Copy the full response body, not the table text.');
            }

            // Locate the listings array wherever it lives in the payload.
            $listings = $data['listings']
                ?? $data['tickets']
                ?? (array_is_list($data ?? []) ? $data : null);

            if (!is_array($listings) || $listings === []) {
                throw new \RuntimeException('No listings found in the pasted data. Make sure you copied the event_listings response.');
            }

            $tickets = [];
            foreach ($listings as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $price = $this->extractPrice($row);
                if ($price <= 0) {
                    continue;
                }
                $tickets[] = new Ticket(
                    (string) ($row['section'] ?? $row['sectionName'] ?? $row['s'] ?? 'Unknown'),
                    (string) ($row['row'] ?? $row['r'] ?? 'GA') ?: 'GA',
                    $price,
                    (int) ($row['quantity'] ?? $row['number_of_tickets'] ?? $row['q'] ?? 1),
                    'SeatGeek',
                );
            }

            if ($tickets === []) {
                throw new \RuntimeException('The pasted data contained no priced listings.');
            }

            return [
                'success'    => true,
                'tickets'    => $tickets,
                'eventTitle' => (string) ($data['event']['title'] ?? $data['title'] ?? 'SeatGeek listings'),
                'platform'   => 'SeatGeek',
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'tickets' => []];
        }
    }

    /** Pull a per-ticket price out of the many shapes SeatGeek uses. */
    private function extractPrice(array $row): float
    {
        foreach (['lowest_price', 'display_price', 'price', 'p'] as $key) {
            if (!isset($row[$key])) {
                continue;
            }
            $v = $row[$key];
            if (is_numeric($v)) {
                return (float) $v;
            }
            if (is_array($v)) {
                // e.g. { "total": 123.45 } or { "amount": 123.45 }
                foreach (['total', 'amount', 'value'] as $sub) {
                    if (isset($v[$sub]) && is_numeric($v[$sub])) {
                        return (float) $v[$sub];
                    }
                }
            }
        }
        return 0.0;
    }
}

<?php

declare(strict_types=1);

namespace TicketScraper\Service;

use TicketScraper\Model\Ticket;

class ConsoleFormatter
{
    private bool $useColour;

    public function __construct()
    {
        $this->useColour = PHP_OS_FAMILY !== 'Windows'
            && function_exists('posix_isatty')
            && posix_isatty(STDOUT);
    }

    /** @param Ticket[] $tickets */
    public function render(array $tickets, string $sourceUrl): void
    {
        if (empty($tickets)) {
            $this->line($this->red('No tickets found for this event.'));
            return;
        }

        $grouped  = $this->groupTickets($tickets);
        $platform = $tickets[0]->platform;

        $this->printHeader($platform, $sourceUrl, count($tickets));

        foreach ($grouped as $section => $rows) {
            $this->printSection($section);
            ksort($rows, SORT_NATURAL);
            foreach ($rows as $row => $rowTickets) {
                $this->printRow($row, $rowTickets);
            }
        }

        $this->printSummary($tickets);
    }

    /** @param Ticket[] $tickets @return array<string, array<string, Ticket[]>> */
    private function groupTickets(array $tickets): array
    {
        $grouped = [];
        foreach ($tickets as $ticket) {
            $grouped[$ticket->section][$ticket->row][] = $ticket;
        }
        ksort($grouped, SORT_NATURAL);
        foreach ($grouped as &$rows) {
            foreach ($rows as &$rowTickets) {
                usort($rowTickets, static fn(Ticket $a, Ticket $b) => $a->price <=> $b->price);
            }
        }
        return $grouped;
    }

    private function printHeader(string $platform, string $url, int $count): void
    {
        $bar = str_repeat('═', 70);
        $this->line($this->cyan($bar));
        $this->line($this->bold("  {$platform} — Available Tickets"));
        $this->line("  URL   : {$url}");
        $this->line("  Found : {$count} listing(s)");
        $this->line($this->cyan($bar));
        echo "\n";
    }

    private function printSection(string $section): void
    {
        echo "\n";
        $this->line($this->yellow("┌─ SECTION: " . strtoupper($section)));
    }

    /** @param Ticket[] $rowTickets */
    private function printRow(string|int $row, array $rowTickets): void
    {
        $this->line($this->bold("│  Row: {$row}"));
        foreach ($rowTickets as $ticket) {
            $qty   = str_pad("x{$ticket->quantity}", 5);
            $price = str_pad($ticket->formattedPrice(), 10);
            $this->line("│    {$qty}  {$this->green($price)}  each");
        }
    }

    /** @param Ticket[] $tickets */
    private function printSummary(array $tickets): void
    {
        $prices = array_map(static fn(Ticket $t) => $t->price, $tickets);
        sort($prices);
        $min = '$' . number_format(min($prices), 2);
        $max = '$' . number_format(max($prices), 2);
        $avg = '$' . number_format(array_sum($prices) / count($prices), 2);
        echo "\n";
        $this->line($this->cyan(str_repeat('─', 70)));
        $this->line("  Price range    : {$min} – {$max}  (avg {$avg})");
        $this->line("  Total listings : " . count($tickets));
        $this->line($this->cyan(str_repeat('─', 70)));
        echo "\n";
    }

    private function line(string $text): void { echo $text . "\n"; }
    private function bold(string $s): string   { return $this->useColour ? "\033[1m{$s}\033[0m" : $s; }
    private function cyan(string $s): string   { return $this->useColour ? "\033[36m{$s}\033[0m" : $s; }
    private function yellow(string $s): string { return $this->useColour ? "\033[33m{$s}\033[0m" : $s; }
    private function green(string $s): string  { return $this->useColour ? "\033[32m{$s}\033[0m" : $s; }
    private function red(string $s): string    { return $this->useColour ? "\033[31m{$s}\033[0m" : $s; }
}

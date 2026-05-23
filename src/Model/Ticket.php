<?php

declare(strict_types=1);

namespace TicketScraper\Model;

final class Ticket
{
    public function __construct(
        public readonly string $section,
        public readonly string $row,
        public readonly float  $price,
        public readonly int    $quantity,
        public readonly string $platform,
    ) {}

    public function formattedPrice(): string
    {
        return '$' . number_format($this->price, 2);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'platform' => $this->platform,
            'section'  => $this->section,
            'row'      => $this->row,
            'price'    => $this->price,
            'quantity' => $this->quantity,
        ];
    }
}

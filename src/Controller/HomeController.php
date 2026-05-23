<?php

declare(strict_types=1);

namespace TicketScraper\Controller;

class HomeController
{
    public function index(): void
    {
        require __DIR__ . '/../View/home.php';
    }
}

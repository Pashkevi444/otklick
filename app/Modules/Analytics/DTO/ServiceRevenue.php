<?php

declare(strict_types=1);

namespace App\Modules\Analytics\DTO;

/**
 * Строка «топа услуг по выручке» в «Отчёте ценности»: услуга, число записей и
 * суммарная выручка (рубли) за период по одной CRM.
 */
final readonly class ServiceRevenue
{
    public function __construct(
        public string $title,
        public int $bookings,
        public int $revenue,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'bookings' => $this->bookings,
            'revenue' => $this->revenue,
        ];
    }
}

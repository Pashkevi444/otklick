<?php

declare(strict_types=1);

namespace App\Modules\Analytics\DTO;

/**
 * «Отчёт ценности» по одной CRM: сколько денег и записей бот принёс за период.
 * У тенанта может быть несколько CRM — на каждую свой отчёт (своя вкладка/выгрузка).
 */
final readonly class ValueReport
{
    /**
     * @param  list<MetricCard>  $kpis
     * @param  list<ServiceRevenue>  $topServices
     */
    public function __construct(
        public string $crmConnectionId,
        public string $crmLabel,
        public array $kpis,
        public array $topServices,
        /** Пояснение (например, у скольких записей цена не указана в CRM). null — нечего пояснять. */
        public ?string $note,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'crmConnectionId' => $this->crmConnectionId,
            'crmLabel' => $this->crmLabel,
            'kpis' => array_map(static fn (MetricCard $c): array => $c->toArray(), $this->kpis),
            'topServices' => array_map(static fn (ServiceRevenue $s): array => $s->toArray(), $this->topServices),
            'note' => $this->note,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\DealData;
use App\Enums\DealStageAutomation;
use App\Enums\DealStageKind;
use App\Models\Deal;
use App\Repositories\Contracts\DealRepositoryInterface;
use App\Repositories\Contracts\DealStageRepositoryInterface;

/**
 * Сделки CRM (воронка продаж). Универсально для любого бизнеса: сделка движется
 * по настраиваемым стадиям. Создаётся вручную или конвертацией из лида.
 *
 * Не final — мокается в юнит-тестах вызывающих сервисов.
 */
class DealService
{
    /**
     * Дефолтная воронка — создаётся при первом обращении тенанта. Каждой стадии
     * задана automation-роль: по ней бот авто-двигает сделку при событиях диалога.
     */
    private const array DEFAULT_STAGES = [
        ['name' => 'Новый', 'kind' => DealStageKind::Active, 'automation' => DealStageAutomation::New],
        ['name' => 'В работе', 'kind' => DealStageKind::Active, 'automation' => DealStageAutomation::Working],
        ['name' => 'Нужен человек', 'kind' => DealStageKind::Active, 'automation' => DealStageAutomation::NeedsHuman],
        ['name' => 'Выиграно', 'kind' => DealStageKind::Won, 'automation' => DealStageAutomation::Won],
        ['name' => 'Проиграно', 'kind' => DealStageKind::Lost, 'automation' => DealStageAutomation::Lost],
    ];

    public function __construct(
        private readonly DealRepositoryInterface $deals,
        private readonly DealStageRepositoryInterface $stages,
    ) {}

    /** Гарантирует наличие воронки у текущего тенанта (создаёт дефолтную). */
    public function ensureStages(): void
    {
        if ($this->stages->existsForCurrentTenant()) {
            return;
        }

        foreach (self::DEFAULT_STAGES as $i => $stage) {
            $this->stages->create([
                'name' => $stage['name'],
                'kind' => $stage['kind'],
                'automation' => $stage['automation'],
                'sort_order' => $i,
            ]);
        }
    }

    public function create(DealData $data): Deal
    {
        $this->ensureStages();

        return $this->deals->create($data);
    }

    public function moveToStage(Deal $deal, string $stageId): void
    {
        $this->deals->update($deal, ['stage_id' => $stageId]);
    }

    /** id первой (рабочей) стадии воронки текущего тенанта. */
    public function firstStageId(): ?string
    {
        $this->ensureStages();

        return $this->stages->forCurrentTenant()->first()?->id;
    }
}

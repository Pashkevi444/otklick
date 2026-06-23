<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\GridViewData;
use App\Enums\GridEntity;
use App\Models\GridView;
use App\Repositories\Contracts\GridViewRepositoryInterface;

/**
 * Сохранённые виды универсального грида (личные, на пользователя): список,
 * создание, правка, удаление. Конфиг (колонки/фильтры/сортировка) хранится как
 * есть — это UI-настройка.
 */
final class GridViewService
{
    public function __construct(
        private readonly GridViewRepositoryInterface $views,
    ) {}

    /**
     * Виды пользователя для сущности — в форме для фронтенда.
     *
     * @return list<array<string, mixed>>
     */
    public function present(int $userId, GridEntity $entity): array
    {
        return $this->views->forUserEntity($userId, $entity)->map(fn (GridView $v): array => [
            'id' => $v->id,
            'name' => $v->name,
            'config' => $v->config,
        ])->all();
    }

    public function create(GridViewData $data): GridView
    {
        return $this->views->create([
            'user_id' => $data->userId,
            'entity' => $data->entity,
            'name' => $data->name,
            'config' => $data->config,
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function update(GridView $view, string $name, array $config): void
    {
        $this->views->update($view, ['name' => $name, 'config' => $config]);
    }

    public function delete(GridView $view): void
    {
        $this->views->delete($view);
    }
}

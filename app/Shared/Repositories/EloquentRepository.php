<?php

declare(strict_types=1);

namespace App\Shared\Repositories;

use Illuminate\Database\Eloquent\Model;

/**
 * Базовый Eloquent-репозиторий с общими операциями. Конкретный репозиторий
 * задаёт модель через {@see Model()} и переиспользует хелперы в своих
 * типизированных методах (поиск по id, удаление).
 *
 * @template TModel of Model
 */
abstract class EloquentRepository
{
    /**
     * @return class-string<TModel>
     */
    abstract protected function model(): string;

    /**
     * @return TModel|null
     */
    protected function findById(string $id): ?Model
    {
        $class = $this->model();

        return (new $class)->newQuery()->find($id);
    }

    /**
     * @param  TModel  $model
     */
    protected function remove(Model $model): void
    {
        $model->delete();
    }
}

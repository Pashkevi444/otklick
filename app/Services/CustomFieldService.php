<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\CustomFieldDefData;
use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Models\CustomFieldDef;
use App\Repositories\Contracts\CustomFieldDefRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Кастомные поля бизнеса для лидов/сделок: управление определениями
 * (ключ генерируется из подписи) и приведение значений по типу перед записью в
 * jsonb `custom`. Поиск/фильтр по `custom` работает на GIN-индексе.
 */
final class CustomFieldService
{
    public function __construct(
        private readonly CustomFieldDefRepositoryInterface $defs,
    ) {}

    /**
     * @return Collection<int, CustomFieldDef>
     */
    public function defsFor(CustomFieldEntity $entity): Collection
    {
        return $this->defs->forEntity($entity);
    }

    /**
     * Определения для фронтенда (рендер инпутов в карточке/модалке).
     *
     * @return list<array<string, mixed>>
     */
    public function present(CustomFieldEntity $entity): array
    {
        return $this->defsFor($entity)->map(fn (CustomFieldDef $d): array => [
            'id' => $d->id,
            'key' => $d->key,
            'label' => $d->label,
            'type' => $d->type->value,
            'options' => $d->options ?? [],
        ])->all();
    }

    public function createDef(CustomFieldDefData $data): CustomFieldDef
    {
        return $this->defs->create([
            'entity' => $data->entity,
            'key' => $this->uniqueKey($data->entity, $data->label),
            'label' => $data->label,
            'type' => $data->type,
            'options' => $data->type->hasOptions() ? $this->cleanOptions($data->options) : null,
            'sort_order' => $this->defs->nextSortOrder($data->entity),
        ]);
    }

    /**
     * Правка определения: подпись и варианты (ключ и тип неизменны — иначе
     * «осиротеют» уже сохранённые значения).
     *
     * @param  array<int, string>|null  $options
     */
    public function updateDef(CustomFieldDef $def, string $label, ?array $options): void
    {
        $this->defs->update($def, [
            'label' => $label,
            'options' => $def->type->hasOptions() ? $this->cleanOptions($options) : null,
        ]);
    }

    public function deleteDef(CustomFieldDef $def): void
    {
        $this->defs->delete($def);
    }

    /**
     * Очищает и приводит сырой ввод кастомных полей к схеме сущности: оставляет
     * только известные ключи, кастует по типу, отбрасывает пустые/некорректные.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function sanitize(CustomFieldEntity $entity, array $input): array
    {
        $out = [];
        foreach ($this->defsFor($entity) as $def) {
            if (! array_key_exists($def->key, $input)) {
                continue;
            }
            $value = $this->cast($def, $input[$def->key]);
            if ($value !== null) {
                $out[$def->key] = $value;
            }
        }

        return $out;
    }

    private function cast(CustomFieldDef $def, mixed $value): mixed
    {
        return match ($def->type) {
            CustomFieldType::Text => $this->castText($value),
            CustomFieldType::Number => $this->castNumber($value),
            CustomFieldType::Bool => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            CustomFieldType::Date => $this->castDate($value),
            CustomFieldType::Select => in_array($value, $def->options ?? [], true) ? $value : null,
        };
    }

    private function castNumber(mixed $value): int|float|null
    {
        if (! is_numeric($value)) {
            return null;
        }

        return str_contains((string) $value, '.') ? (float) $value : (int) $value;
    }

    private function castText(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : Str::limit($text, 2000, '');
    }

    private function castDate(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $value));

        return checkdate($m, $d, $y) ? $value : null;
    }

    /**
     * Уникальный по (тенант, сущность) машинный ключ из подписи. Транслитерация
     * кириллицы через Str::slug; пустой результат → `field`.
     */
    private function uniqueKey(CustomFieldEntity $entity, string $label): string
    {
        $base = Str::slug($label, '_');
        if ($base === '') {
            $base = 'field';
        }
        $base = Str::limit($base, 48, '');

        $key = $base;
        $i = 1;
        while ($this->defs->existsKey($entity, $key)) {
            $key = $base.'_'.(++$i);
        }

        return $key;
    }

    /**
     * @param  array<int, string>|null  $options
     * @return array<int, string>
     */
    private function cleanOptions(?array $options): array
    {
        return array_values(array_filter(
            array_map(fn ($o): string => trim((string) $o), $options ?? []),
            fn (string $o): bool => $o !== '',
        ));
    }
}

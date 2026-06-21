<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Справочник типов бизнеса (ниш). Глобальный (без tenant_id). Ключ (`key`,
 * например `nails`) совпадает со значением шаблонов `business_type` и с типом
 * тенанта (`tenants.business_type`). Засеян из {@see \App\Enums\BusinessType}.
 *
 * @property string $key
 * @property string $label
 * @property int $sort_order
 */
class BusinessType extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'label', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /**
     * Справочник для фронта: [{value, label}] в порядке сортировки.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return self::query()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (self $t): array => ['value' => $t->key, 'label' => $t->label])
            ->all();
    }
}

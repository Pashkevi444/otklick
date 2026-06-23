<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\DTO\CustomFieldDefData;
use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Models\Tenant;
use App\Services\CustomFieldService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomFieldServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->max()->create();
        $this->app->make(TenantContext::class)->set($this->tenant->id);
    }

    private function service(): CustomFieldService
    {
        return $this->app->make(CustomFieldService::class);
    }

    public function test_create_def_generates_key_and_increments_sort_order(): void
    {
        $a = $this->service()->createDef(new CustomFieldDefData(CustomFieldEntity::Deal, 'Бюджет', CustomFieldType::Number));
        $b = $this->service()->createDef(new CustomFieldDefData(CustomFieldEntity::Deal, 'Бюджет', CustomFieldType::Number));

        $this->assertNotSame('', $a->key);
        $this->assertNotSame($a->key, $b->key); // одинаковая подпись → уникальный ключ
        $this->assertSame(1, $a->sort_order);
        $this->assertSame(2, $b->sort_order);
    }

    public function test_select_field_keeps_cleaned_options(): void
    {
        $def = $this->service()->createDef(new CustomFieldDefData(
            CustomFieldEntity::Lead,
            'Источник',
            CustomFieldType::Select,
            options: ['Сайт', ' ', 'Звонок'],
        ));

        $this->assertSame(['Сайт', 'Звонок'], $def->options);
    }

    public function test_sanitize_casts_values_by_type_and_drops_unknown(): void
    {
        $this->service()->createDef(new CustomFieldDefData(CustomFieldEntity::Deal, 'Бюджет', CustomFieldType::Number)); // key: byudzhet
        $this->service()->createDef(new CustomFieldDefData(CustomFieldEntity::Deal, 'Срочно', CustomFieldType::Bool));
        $this->service()->createDef(new CustomFieldDefData(CustomFieldEntity::Deal, 'Источник', CustomFieldType::Select, options: ['Сайт', 'Звонок']));

        $defs = $this->service()->defsFor(CustomFieldEntity::Deal)->keyBy('label');
        $budget = $defs['Бюджет']->key;
        $urgent = $defs['Срочно']->key;
        $source = $defs['Источник']->key;

        $clean = $this->service()->sanitize(CustomFieldEntity::Deal, [
            $budget => '50000',
            $urgent => 'true',
            $source => 'Почта',      // нет в options → отбрасывается
            'ghost' => 'злоумышленник', // нет такого поля → отбрасывается
        ]);

        $this->assertSame(50000, $clean[$budget]);
        $this->assertTrue($clean[$urgent]);
        $this->assertArrayNotHasKey($source, $clean);
        $this->assertArrayNotHasKey('ghost', $clean);
    }

    public function test_sanitize_validates_date_and_drops_empty_text(): void
    {
        $this->service()->createDef(new CustomFieldDefData(CustomFieldEntity::Lead, 'Дедлайн', CustomFieldType::Date));
        $this->service()->createDef(new CustomFieldDefData(CustomFieldEntity::Lead, 'Комментарий', CustomFieldType::Text));

        $defs = $this->service()->defsFor(CustomFieldEntity::Lead)->keyBy('label');
        $deadline = $defs['Дедлайн']->key;
        $comment = $defs['Комментарий']->key;

        $ok = $this->service()->sanitize(CustomFieldEntity::Lead, [$deadline => '2026-13-40', $comment => '   ']);
        $this->assertArrayNotHasKey($deadline, $ok); // невалидная дата
        $this->assertArrayNotHasKey($comment, $ok);  // пустой текст

        $good = $this->service()->sanitize(CustomFieldEntity::Lead, [$deadline => '2026-07-10', $comment => '  важно ']);
        $this->assertSame('2026-07-10', $good[$deadline]);
        $this->assertSame('важно', $good[$comment]);
    }
}

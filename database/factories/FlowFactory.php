<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Flows\Models\Flow;
use App\Shared\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Flow>
 */
class FlowFactory extends Factory
{
    protected $model = Flow::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'Сценарий',
            'is_active' => true,
            'triggers' => ['акция'],
            'definition' => [
                'start' => 'n1',
                'nodes' => [
                    'n1' => ['type' => 'message', 'text' => 'Чем помочь?', 'action' => 'none', 'options' => [
                        ['label' => 'Записаться', 'next' => 'n2'],
                        ['label' => 'Вопрос', 'next' => 'n3'],
                    ]],
                    'n2' => ['type' => 'message', 'text' => 'Отлично, записываю!', 'action' => 'start_booking', 'options' => []],
                    'n3' => ['type' => 'message', 'text' => 'Передаю администратору.', 'action' => 'escalate', 'options' => []],
                ],
            ],
        ];
    }
}

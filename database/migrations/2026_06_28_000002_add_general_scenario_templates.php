<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * +6 «Общих» сценариев (businessType = null) — годятся любому бизнесу, чтобы общих
 * стало ~10. Идемпотентно (updateOrInsert по key). Без действия start_booking.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $base = (int) DB::table('scenario_templates')->max('sort_order') + 1;

        foreach ($this->templates() as $i => $t) {
            DB::table('scenario_templates')->updateOrInsert(['key' => $t['key']], [
                'id' => (string) Str::uuid(),
                'name' => $t['name'],
                'description' => $t['description'],
                'business_type' => null,
                'triggers' => json_encode($t['triggers'], JSON_UNESCAPED_UNICODE),
                'definition' => json_encode($t['definition'], JSON_UNESCAPED_UNICODE),
                'sort_order' => $base + $i,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Только данные.
    }

    /**
     * @return list<array{key: string, name: string, description: string, triggers: list<string>, definition: array<string, mixed>}>
     */
    private function templates(): array
    {
        return [
            $this->tpl('gen_contacts_flow', 'Как с нами связаться', 'Быстрый ответ на «как связаться / телефон / почта».',
                ['контакты', 'как связаться', 'телефон', 'почта', 'как с вами связаться'], 'n1', [
                    'n1' => $this->msg('Связаться с нами можно по телефону …, в мессенджерах … или по почте …. Будем рады помочь!', 'end', [], 0, 0),
                ]),
            $this->tpl('gen_review_flow', 'Оставить отзыв', 'Клиент хочет оставить отзыв или благодарность.',
                ['отзыв', 'оставить отзыв', 'спасибо', 'благодарность', 'хочу похвалить'], 'n1', [
                    'n1' => $this->input('Спасибо, что делитесь! Напишите ваш отзыв здесь 🙏', 'review', 'n2', 0, 0),
                    'n2' => $this->msg('Благодарим за тёплые слова — обязательно передадим команде! Будем рады видеть вас снова.', 'end', [], 0, 160),
                ]),
            $this->tpl('gen_complaint_flow', 'Решить проблему', 'Клиент недоволен — мягко принимаем обращение и зовём ответственного.',
                ['жалоба', 'претензия', 'недоволен', 'проблема', 'верните деньги'], 'n1', [
                    'n1' => $this->input('Очень жаль, что так вышло. Опишите, пожалуйста, что произошло — мы разберёмся.', 'complaint', 'n2', 0, 0),
                    'n2' => $this->msg('Спасибо, что сообщили. Передаю ответственному — свяжемся с вами и всё решим.', 'escalate', [], 0, 160),
                ]),
            $this->tpl('gen_callback_flow', 'Заказать звонок', 'Клиент просит перезвонить — собираем имя и телефон.',
                ['перезвоните', 'обратный звонок', 'заказать звонок', 'нужен звонок', 'свяжитесь со мной'], 'n1', [
                    'n1' => $this->input('Организуем звонок! Как вас зовут?', 'name', 'n2', 0, 0),
                    'n2' => $this->input('Оставьте телефон и удобное время — перезвоним.', 'phone', 'n3', 0, 160),
                    'n3' => $this->msg('Спасибо, {{name}}! Передал заявку — перезвоним в удобное время.', 'escalate', [], 0, 320),
                ]),
            $this->tpl('gen_operator_flow', 'Позвать сотрудника', 'Клиент хочет живого человека — сразу зовём оператора.',
                ['оператор', 'менеджер', 'живой человек', 'позовите человека', 'хочу с человеком'], 'n1', [
                    'n1' => $this->msg('Конечно, зову сотрудника — он скоро подключится к диалогу.', 'escalate', [], 0, 0),
                ]),
            $this->tpl('gen_question_flow', 'Задать вопрос', 'Свободный вопрос клиента — собираем и передаём, если бот не ответил.',
                ['вопрос', 'хочу спросить', 'подскажите', 'уточнить', 'можно вопрос'], 'n1', [
                    'n1' => $this->input('Конечно! Напишите ваш вопрос — постараемся помочь.', 'question', 'n2', 0, 0),
                    'n2' => $this->msg('Спасибо за вопрос! Если бот не ответил полностью — передаю сотруднику, он уточнит.', 'escalate', [], 0, 160),
                ]),
        ];
    }

    /**
     * @param  list<string>  $triggers
     * @param  array<string, mixed>  $nodes
     * @return array{key: string, name: string, description: string, triggers: list<string>, definition: array<string, mixed>}
     */
    private function tpl(string $key, string $name, string $description, array $triggers, string $start, array $nodes): array
    {
        return [
            'key' => $key,
            'name' => $name,
            'description' => $description,
            'triggers' => $triggers,
            'definition' => ['start' => $start, 'nodes' => $nodes],
        ];
    }

    /**
     * @param  list<array{label: string, next: string}>  $options
     * @return array<string, mixed>
     */
    private function msg(string $text, string $action, array $options, int $x, int $y): array
    {
        return ['type' => 'message', 'action' => $action, 'text' => $text, 'options' => $options, 'position' => ['x' => $x, 'y' => $y]];
    }

    /**
     * @return array<string, mixed>
     */
    private function input(string $text, string $variable, string $next, int $x, int $y): array
    {
        return ['type' => 'input', 'text' => $text, 'variable' => $variable, 'next' => $next, 'position' => ['x' => $x, 'y' => $y]];
    }
};

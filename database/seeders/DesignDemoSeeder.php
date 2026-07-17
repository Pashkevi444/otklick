<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Booking\Models\CrmConnection;
use App\Modules\Broadcasts\Models\Broadcast;
use App\Modules\Channels\Models\Channel;
use App\Modules\Clients\Models\Client;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Flows\Models\Flow;
use App\Modules\Knowledge\Models\KnowledgeEntry;
use App\Modules\Knowledge\Models\KnowledgeGap;
use App\Modules\Notifications\Models\NotificationRecipient;
use App\Modules\Platform\Models\Announcement;
use App\Shared\Enums\AnnouncementType;
use App\Shared\Enums\BroadcastStatus;
use App\Shared\Enums\ChannelType;
use App\Shared\Enums\ConversationStatus;
use App\Shared\Enums\MemberPermission;
use App\Shared\Enums\MessageDirection;
use App\Shared\Enums\MessageStatus;
use App\Shared\Enums\TenantPlan;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Tenant;
use App\Shared\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Демо-данные ТОЛЬКО для локального просмотра дизайна всех страниц (редизайн).
 * Не для прода. Создаёт СУ, тенанта со всеми фичами (план Individual), владельца,
 * команду и репрезентативные данные во всех разделах кабинета/админки.
 *
 * Логины (пароль у всех — `password`):
 *   admin@demo.local     — супер-админ (/admin)
 *   owner@demo.local     — владелец бизнеса (/cabinet)
 */
class DesignDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Супер-админ (кросс-тенантный, без tenant_id).
        User::factory()->superAdmin()->create([
            'name' => 'Супер-админ',
            'email' => 'admin@demo.local',
            'password' => Hash::make('password'),
        ]);

        // Тенант со всеми фичами (Individual = всё включено), активный.
        $tenant = Tenant::factory()->state([
            'name' => 'Барбершоп «Метрополь»',
            'slug' => 'metropol-barbershop',
            'plan' => TenantPlan::Individual,
            'business_type' => 'barbershop',
            'is_blocked' => false,
            'access_expires_at' => null,
        ])->create();

        // Контекст тенанта — на случай read-путей глобального scope при сиде.
        app(TenantContext::class)->set($tenant->id);

        // Владелец.
        $owner = User::factory()->owner($tenant)->create([
            'name' => 'Иван Владелец',
            'email' => 'owner@demo.local',
            'password' => Hash::make('password'),
        ]);

        // Команда (страница «Команда»): полный доступ + частичный.
        User::factory()->state([
            'tenant_id' => $tenant->id,
            'role' => UserRole::Member,
            'name' => 'Пётр Оператор',
            'email' => 'operator@demo.local',
            'password' => Hash::make('password'),
            'permissions' => MemberPermission::values(),
        ])->create();
        User::factory()->state([
            'tenant_id' => $tenant->id,
            'role' => UserRole::Member,
            'name' => 'Мария Ресепшн',
            'email' => 'reception@demo.local',
            'password' => Hash::make('password'),
            'permissions' => ['conversations', 'clients', 'knowledge'],
        ])->create();

        // Каналы: Telegram (активный) + VK + MAX + веб-виджет.
        $tg = Channel::factory()->create(['tenant_id' => $tenant->id]);
        Channel::factory()->vk()->create(['tenant_id' => $tenant->id]);
        Channel::factory()->max()->create(['tenant_id' => $tenant->id]);
        Channel::factory()->state(['type' => ChannelType::Web])->create(['tenant_id' => $tenant->id]);

        // CRM (YClients).
        CrmConnection::factory()->create(['tenant_id' => $tenant->id]);

        // Диалоги + клиенты + сообщения (разные статусы — «живой» список лидов).
        $people = [
            ['Алексей Смирнов', '+79161112233', ConversationStatus::Open],
            ['Дмитрий Козлов', '+79162223344', ConversationStatus::NeedsHuman],
            ['Сергей Новиков', '+79163334455', ConversationStatus::Closed],
            ['Егор Волков', '+79164445566', ConversationStatus::Open],
            ['Никита Морозов', '+79165556677', ConversationStatus::NeedsHuman],
        ];
        foreach ($people as $i => [$name, $phone, $status]) {
            $conv = Conversation::factory()->withClient($name, $phone)->create([
                'tenant_id' => $tenant->id,
                'channel_id' => $tg->id,
                'status' => $status,
                'last_message_at' => now()->subHours($i + 1),
            ]);
            Message::factory()->create([
                'tenant_id' => $tenant->id, 'conversation_id' => $conv->id,
                'direction' => MessageDirection::Inbound, 'status' => MessageStatus::Received,
                'text' => 'Здравствуйте! Можно записаться на стрижку?',
            ]);
            Message::factory()->create([
                'tenant_id' => $tenant->id, 'conversation_id' => $conv->id,
                'direction' => MessageDirection::Outbound, 'status' => MessageStatus::Sent,
                'text' => 'Здравствуйте! Конечно. На какой день вам удобно?',
            ]);
        }

        // Ещё пара клиентов в базе.
        Client::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        // База знаний (пара записей с фото — data-URI, чтобы рендерилось офлайн).
        $photo = 'data:image/svg+xml;base64,'.base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="220"><rect width="100%" height="100%" fill="#2B5CE0"/>'
            .'<text x="50%" y="50%" fill="#cdd9f6" font-family="sans-serif" font-size="22" text-anchor="middle" dominant-baseline="middle">Фото работы</text></svg>'
        );
        $kb = [
            ['Стрижка «Фейд»', 'Классический фейд с плавным переходом. От 1500 ₽.', [['path' => 'demo/1.svg', 'url' => $photo], ['path' => 'demo/2.svg', 'url' => $photo]]],
            ['Оформление бороды', 'Моделирование формы и уход. От 900 ₽.', []],
            ['Детская стрижка', 'Для детей до 10 лет. От 1000 ₽.', []],
            ['Часы работы', 'Ежедневно с 10:00 до 22:00, без выходных.', []],
            ['Адрес и как добраться', 'Москва, ул. Пример, 1. 2 минуты пешком от метро.', []],
        ];
        foreach ($kb as [$title, $content, $images]) {
            KnowledgeEntry::factory()->create([
                'tenant_id' => $tenant->id, 'title' => $title, 'content' => $content,
                'is_published' => true, 'images' => $images,
            ]);
        }
        KnowledgeEntry::factory()->create([
            'tenant_id' => $tenant->id, 'title' => 'Черновик: весенняя акция',
            'content' => 'Скидка 15% на окрашивание.', 'is_published' => false, 'images' => [],
        ]);

        // Пробелы в знаниях (вкладка «Развитие бота»).
        foreach (['Есть ли у вас парковка?', 'Делаете ли окрашивание?', 'Можно оплатить картой?'] as $q) {
            KnowledgeGap::factory()->create([
                'tenant_id' => $tenant->id, 'question' => $q, 'normalized' => mb_strtolower($q),
            ]);
        }

        // Рассылки (разные статусы).
        Broadcast::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $owner->id, 'title' => 'Скидка 20% в будни', 'status' => BroadcastStatus::Sent, 'sent_count' => 142]);
        Broadcast::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $owner->id, 'title' => 'Напоминание про акцию', 'status' => BroadcastStatus::Scheduled, 'scheduled_at' => now()->addDays(2)]);
        Broadcast::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $owner->id, 'title' => 'Черновик рассылки', 'status' => BroadcastStatus::Draft]);

        // Сценарии-воронки.
        Flow::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Приветствие новичка', 'is_active' => true]);
        Flow::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Возврат клиента', 'is_active' => false]);

        // Получатели уведомлений.
        NotificationRecipient::factory()->create(['tenant_id' => $tenant->id, 'value' => 'owner@demo.local', 'label' => 'Владелец']);
        NotificationRecipient::factory()->telegram('123456789')->create(['tenant_id' => $tenant->id, 'label' => 'Директор']);

        // Новости площадки (Cabinet/Announcements + Admin/Announcements).
        Announcement::create(['type' => AnnouncementType::News, 'title' => 'Живой чат в виджете', 'body' => 'Теперь оператор может подключиться к диалогу прямо из кабинета.', 'is_published' => true, 'published_at' => now()->subDay()]);
        Announcement::create(['type' => AnnouncementType::News, 'title' => 'Распознавание голосовых', 'body' => 'Бот понимает голосовые сообщения клиентов.', 'is_published' => true, 'published_at' => now()->subDays(5)]);

        // Глобальные шаблоны сценариев/БЗ (Admin/*Templates) уже засеяны их
        // create-миграциями — здесь не дублируем, чтобы не ловить UNIQUE по key.
    }
}

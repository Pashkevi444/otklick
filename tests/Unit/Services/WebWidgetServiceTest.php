<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Modules\Bot\Contracts\BotApi;
use App\Modules\Channels\Models\Channel;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Conversations\Repositories\Contracts\ConversationRepositoryInterface;
use App\Modules\Conversations\Repositories\Contracts\MessageRepositoryInterface;
use App\Modules\Conversations\Services\ContactCapture;
use App\Modules\Conversations\Services\SpamDetector;
use App\Modules\Conversations\Services\WebWidgetService;
use App\Modules\Identity\Contracts\IdentityApi;
use App\Modules\Notifications\Contracts\NotificationsApi;
use App\Modules\Notifications\NotificationsApiService;
use App\Modules\Notifications\Repositories\Contracts\NotificationRecipientRepositoryInterface;
use App\Modules\Notifications\Repositories\Contracts\UserNotificationRepositoryInterface;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Notifications\Services\TelegramLinkService;
use App\Modules\Notifications\Services\UserNotificationService;
use App\Shared\DTO\BotReply;
use App\Shared\Enums\ChannelType;
use App\Shared\Enums\ConversationStatus;
use App\Shared\Enums\MessageStatus;
use App\Shared\Enums\UserNotificationType;
use App\Shared\Models\Tenant;
use App\Shared\Models\User;
use App\Shared\Vision\FakeImageToText;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

final class WebWidgetServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** Контракт Notifications: notify/исходящие — no-op (нет получателей). */
    private function notifications(): NotificationsApi
    {
        $api = Mockery::mock(NotificationsApi::class);
        $api->shouldReceive('notify')->byDefault();
        $api->shouldReceive('sendOwnerNotificationAsync')->byDefault();

        return $api;
    }

    public function test_booking_closes_conversation(): void
    {
        $channel = new Channel;
        $channel->id = 'web-1';
        $channel->setRelation('tenant', new Tenant(['name' => 'Бизнес']));

        $token = Crypt::encryptString('web-1|sess-1');
        $conversation = new Conversation;
        $conversation->id = 'conv-1';

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')
            ->once()->with('web-1', 'sess-1', null, null)->andReturn($conversation);
        $conversations->shouldReceive('touchLastMessage')->once()->with($conversation);
        $conversations->shouldReceive('markBooked')->once()->with($conversation);

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn(new Message);
        $messages->shouldReceive('recordOutbound')
            ->once()->with($conversation, 'Записал вас!', MessageStatus::Sent)->andReturn(new Message);

        $responder = Mockery::mock(BotApi::class);
        $responder->shouldReceive('respond')->once()->with(Mockery::any(), $conversation, 'да, записывайте')
            ->andReturn(new BotReply('Записал вас!', escalate: false, booked: true));

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldReceive('fromInbound')->once()->with($conversation, 'да, записывайте');

        ['reply' => $reply] = (new WebWidgetService($conversations, $messages, $responder, $contacts, Mockery::mock(SpamDetector::class)->allows('isSpam')->andReturn(false)->getMock(), new FakeImageToText, $this->notifications()))
            ->reply($channel, $token, 'да, записывайте');

        $this->assertTrue($reply->booked);
    }

    public function test_operator_handling_silences_bot(): void
    {
        // Диалог перехвачен оператором → бот не отвечает (responder не зовём,
        // исходящее не пишем), reply пустой; курсор поллинга — id входящего.
        $channel = new Channel;
        $channel->id = 'web-1';
        $channel->setRelation('tenant', new Tenant(['name' => 'Бизнес']));

        $token = Crypt::encryptString('web-1|sess-1');
        $conversation = new Conversation;
        $conversation->id = 'conv-1';
        $conversation->operator_active_at = now(); // активный перехват

        $inbound = new Message;
        $inbound->id = 'm-in-1';

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->andReturn($conversation);
        $conversations->shouldReceive('touchLastMessage')->once()->with($conversation);

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn($inbound);
        $messages->shouldNotReceive('recordOutbound');

        $responder = Mockery::mock(BotApi::class);
        $responder->shouldNotReceive('respond');

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldReceive('fromInbound')->once();

        ['reply' => $reply, 'lastId' => $lastId] = (new WebWidgetService($conversations, $messages, $responder, $contacts, Mockery::mock(SpamDetector::class)->allows('isSpam')->andReturn(false)->getMock(), new FakeImageToText, $this->notifications()))
            ->reply($channel, $token, 'есть кто живой?');

        $this->assertSame('', $reply->text);
        $this->assertSame('m-in-1', $lastId);
    }

    public function test_escalation_creates_cabinet_bell_notification(): void
    {
        // Регресс: веб-виджет НЕ слал in-app уведомление в кабинет — клиент звал
        // админа, а в колоколе пусто. Теперь на эскалацию создаётся уведомление.
        Queue::fake();

        $channel = new Channel;
        $channel->id = 'web-1';
        $channel->type = ChannelType::Web;
        $channel->tenant_id = 't-1';
        $channel->setRelation('tenant', new Tenant(['name' => 'Барбершоп']));

        $token = Crypt::encryptString('web-1|sess-1');
        $conversation = new Conversation;
        $conversation->id = 'conv-1';

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->andReturn($conversation);
        $conversations->shouldReceive('touchLastMessage')->once()->with($conversation);
        $conversations->shouldReceive('updateStatus')->once()->with($conversation, ConversationStatus::NeedsHuman);

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn(new Message);
        $messages->shouldReceive('recordOutbound')->once()->andReturn(new Message);

        $responder = Mockery::mock(BotApi::class);
        $responder->shouldReceive('respond')->once()->andReturn(new BotReply('Передаю администратору.', escalate: true));

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldReceive('fromInbound')->once();

        // Реальный сервис с одним сотрудником-получателем → проверяем, что строка
        // уведомления реально вставляется (раньше веб-виджет её не создавал).
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('allows')->andReturn(true);
        $user->forceFill(['id' => 'u-1', 'tenant_id' => 't-1']);
        $userRepo = Mockery::mock(IdentityApi::class);
        $userRepo->shouldReceive('forCurrentTenant')->andReturn(collect([$user]));
        $notifyRepo = Mockery::mock(UserNotificationRepositoryInterface::class);
        $notifyRepo->shouldReceive('insertMany')->once()
            ->with(Mockery::on(fn (array $rows): bool => count($rows) === 1 && $rows[0]['type'] === UserNotificationType::Escalation->value));
        // Реальный сервис in-app уведомлений за фасадом NotificationsApi — проверяем,
        // что строка уведомления реально вставляется (delegate notify → репозиторий).
        $notifications = new NotificationsApiService(
            new UserNotificationService($notifyRepo, $userRepo),
            app(NotificationService::class),
            app(TelegramLinkService::class),
            app(NotificationRecipientRepositoryInterface::class),
        );

        $service = new WebWidgetService(
            $conversations,
            $messages,
            $responder,
            $contacts,
            Mockery::mock(SpamDetector::class)->allows('isSpam')->andReturn(false)->getMock(),
            new FakeImageToText,
            $notifications,
        );

        ['reply' => $reply] = $service->reply($channel, $token, 'позовите администратора');

        $this->assertTrue($reply->escalate);
    }

    public function test_escalated_conversation_still_answers_with_note(): void
    {
        // Диалог уже эскалирован (ждёт оператора), оператор НЕ перехватил → бот не
        // молчит: отвечает на вопрос посетителя с пометкой, что оператор подключён.
        $channel = new Channel;
        $channel->id = 'web-1';
        $channel->setRelation('tenant', new Tenant(['name' => 'Бизнес']));

        $token = Crypt::encryptString('web-1|sess-1');
        $conversation = new Conversation;
        $conversation->id = 'conv-1';
        $conversation->status = ConversationStatus::NeedsHuman;

        $expected = BotReply::ESCALATED_NOTE."\n\nВот примеры наших работ!";

        $outbound = new Message;
        $outbound->id = 'm-out-1';

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->andReturn($conversation);
        $conversations->shouldReceive('touchLastMessage')->once()->with($conversation);
        $conversations->shouldNotReceive('updateStatus'); // уже эскалирован — повторно не трогаем

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn(new Message);
        $messages->shouldReceive('recordOutbound')->once()->with($conversation, $expected, MessageStatus::Sent)->andReturn($outbound);

        $responder = Mockery::mock(BotApi::class);
        $responder->shouldReceive('respond')->once()->with(Mockery::any(), $conversation, 'покажи примеры')
            ->andReturn(new BotReply('Вот примеры наших работ!', escalate: false, images: ['https://x/a.jpg']));

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldReceive('fromInbound')->once();

        $service = new WebWidgetService(
            $conversations,
            $messages,
            $responder,
            $contacts,
            Mockery::mock(SpamDetector::class)->allows('isSpam')->andReturn(false)->getMock(),
            new FakeImageToText,
            $this->notifications(),
        );

        ['reply' => $reply, 'lastId' => $lastId] = $service->reply($channel, $token, 'покажи примеры');

        $this->assertSame($expected, $reply->text);
        $this->assertSame(['https://x/a.jpg'], $reply->images); // картинки «примеры работ» не теряются в эскалации
        $this->assertSame('m-out-1', $lastId);
    }

    public function test_recognized_image_gets_bot_answer_instead_of_escalation(): void
    {
        // Vision распознал фото → бот отвечает по базе знаний, диалог НЕ уходит
        // администратору (статус needs_human не ставится).
        Storage::fake('public');
        Storage::disk('public')->put('widget/t-1/cut.jpg', 'JPEG-BYTES');

        $channel = new Channel;
        $channel->id = 'web-1';
        $channel->tenant_id = 't-1';
        $channel->setRelation('tenant', new Tenant(['name' => 'Барбершоп']));

        $token = Crypt::encryptString('web-1|sess-1');
        $conversation = new Conversation;
        $conversation->id = 'conv-1';

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->andReturn($conversation);
        $conversations->shouldReceive('touchLastMessage')->once()->with($conversation);
        $conversations->shouldNotReceive('updateStatus');

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn(new Message);
        $outbound = new Message;
        $outbound->id = 'm-out-1';
        $messages->shouldReceive('recordOutbound')
            ->once()->with($conversation, 'Делаем такие стрижки, записать вас?', MessageStatus::Sent)->andReturn($outbound);

        $responder = Mockery::mock(BotApi::class);
        $responder->shouldReceive('respond')
            ->once()
            ->with(Mockery::any(), $conversation, '[Клиент прислал фото. На фото: Мужская стрижка андеркат.]')
            ->andReturn(new BotReply('Делаем такие стрижки, записать вас?', escalate: false));

        $contacts = Mockery::mock(ContactCapture::class);

        $service = new WebWidgetService(
            $conversations,
            $messages,
            $responder,
            $contacts,
            Mockery::mock(SpamDetector::class)->allows('isSpam')->andReturn(false)->getMock(),
            new FakeImageToText('Мужская стрижка андеркат.'),
            $this->notifications(),
        );

        ['reply' => $reply, 'lastId' => $lastId, 'operatorActive' => $operatorActive] = $service->receiveImage(
            $channel,
            $token,
            [['path' => 'widget/t-1/cut.jpg', 'url' => 'https://x/storage/widget/t-1/cut.jpg']],
            '',
        );

        $this->assertSame('Делаем такие стрижки, записать вас?', $reply->text);
        $this->assertFalse($reply->escalate);
        $this->assertFalse($operatorActive);
        $this->assertSame('m-out-1', $lastId);
    }

    public function test_unrecognized_image_escalates_to_admin(): void
    {
        // Vision выключен/не распознал → прежнее поведение: фото уходит админу.
        Queue::fake();
        Storage::fake('public');
        Storage::disk('public')->put('widget/t-1/blur.jpg', 'JPEG-BYTES');

        $channel = new Channel;
        $channel->id = 'web-1';
        $channel->type = ChannelType::Web;
        $channel->tenant_id = 't-1';
        $channel->setRelation('tenant', new Tenant(['name' => 'Барбершоп']));

        $token = Crypt::encryptString('web-1|sess-1');
        $conversation = new Conversation;
        $conversation->id = 'conv-1';

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->andReturn($conversation);
        $conversations->shouldReceive('touchLastMessage')->once()->with($conversation);
        $conversations->shouldReceive('updateStatus')->once()->with($conversation, ConversationStatus::NeedsHuman);

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn(new Message);
        $messages->shouldReceive('recordOutbound')->once()->andReturn(new Message);

        $responder = Mockery::mock(BotApi::class);
        $responder->shouldNotReceive('respond');

        $service = new WebWidgetService(
            $conversations,
            $messages,
            $responder,
            Mockery::mock(ContactCapture::class),
            Mockery::mock(SpamDetector::class)->allows('isSpam')->andReturn(false)->getMock(),
            new FakeImageToText, // null описание
            $this->notifications(),
        );

        ['reply' => $reply] = $service->receiveImage(
            $channel,
            $token,
            [['path' => 'widget/t-1/blur.jpg', 'url' => 'https://x/storage/widget/t-1/blur.jpg']],
            '',
        );

        $this->assertTrue($reply->escalate);
    }
}

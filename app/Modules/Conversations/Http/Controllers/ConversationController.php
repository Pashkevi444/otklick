<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Services\BookingFlow;
use App\Modules\Channels\Models\Channel;
use App\Modules\Conversations\Events\OperatorTyping;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Conversations\Repositories\Contracts\ConversationRepositoryInterface;
use App\Modules\Conversations\Repositories\Contracts\MessageRepositoryInterface;
use App\Modules\Conversations\Services\ConversationHandoffService;
use App\Modules\Notifications\Services\UserNotificationService;
use App\Shared\Enums\ChannelType;
use App\Shared\Enums\ConversationOutcome;
use App\Shared\Enums\ConversationStatus;
use App\Shared\Models\User;
use App\Shared\Support\TenantImageStorage;
use App\Shared\Support\WidgetRealtimeChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Журнал переписок бота в кабинете тенанта: список диалогов (по клиентам) и
 * детальная переписка. Данные скоупятся текущим тенантом (RLS + scope).
 */
final class ConversationController extends Controller
{
    public function __construct(
        private readonly ConversationRepositoryInterface $conversations,
        private readonly MessageRepositoryInterface $messages,
        private readonly BookingFlow $booking,
        private readonly ConversationHandoffService $handoff,
        private readonly UserNotificationService $notifications,
        private readonly TenantImageStorage $images,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', '')) ?: null;
        $status = ConversationStatus::tryFrom((string) $request->query('status', ''));
        $channel = ChannelType::tryFrom((string) $request->query('channel', ''));
        $sort = in_array($request->query('sort'), ['last', 'contact', 'messages', 'created'], true) ? (string) $request->query('sort') : 'last';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $page = $this->conversations->paginateForCurrentTenant($search, $status, $channel, $sort, $dir, 15);

        // Новые лиды (с непрочитанным уведомлением: новый лид/эскалация/запись) —
        // подсвечиваем «Новый». Метка держится, пока не откроют диалог (`show()`
        // пометит прочитанным) — как у уведомлений/контактов. Per-user.
        $user = $request->user();
        $newIds = $user instanceof User ? $this->notifications->unreadEntityIds($user, 'conversation') : [];

        return Inertia::render('Cabinet/Conversations/Index', [
            'newConversationIds' => $newIds,
            'conversations' => array_map($this->present(...), $page->items()),
            'pagination' => [
                'current' => $page->currentPage(),
                'last' => $page->lastPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
            'filters' => [
                'search' => $search ?? '',
                'status' => $status instanceof ConversationStatus ? $status->value : '',
                'channel' => $channel instanceof ChannelType ? $channel->value : '',
                'sort' => $sort,
                'dir' => $dir,
            ],
            'statuses' => array_map(
                fn (ConversationStatus $s): array => ['value' => $s->value, 'label' => $s->stageLabel()],
                ConversationStatus::cases(),
            ),
            'channels' => array_map(
                fn (string $type): array => ['value' => $type, 'label' => ChannelType::tryFrom($type)?->label() ?? $type],
                $this->conversations->channelTypesForCurrentTenant(),
            ),
        ]);
    }

    /** «Прочитать всё»: гасит подсветку «Новый» у всех лидов (per-user). */
    public function readAll(Request $request): RedirectResponse
    {
        if (($user = $request->user()) instanceof User) {
            $this->notifications->markEntityTypeRead($user, 'conversation');
        }

        return back();
    }

    public function show(Request $request, string $conversation): Response
    {
        $model = $this->conversations->findForCurrentTenant($conversation);

        abort_if($model === null, 404);

        // Открыл конкретный диалог → его уведомления (лид/эскалация/запись) гаснут.
        if (($user = $request->user()) instanceof User) {
            $this->notifications->markEntityRead($user, 'conversation', (string) $model->id);
        }

        return Inertia::render('Cabinet/Conversations/Show', [
            'conversation' => [
                'id' => $model->id,
                'contact' => $this->contact($model),
                'phone' => $this->displayPhone($model),
                'channel' => $model->channel?->type->label() ?? '—',
                'source' => $this->source($model->channel),
                'contactRef' => $model->contact_ref,
                'status' => $model->status->value,
                'statusLabel' => $model->status->label(),
                'outcome' => $model->outcome()->value,
                'outcomeLabel' => $model->outcome()->label(),
                'createdAt' => $model->created_at?->format('d.m.Y'),
                // Связь лида с CRM-записью (id записи в CRM + какая CRM).
                'crmRecordId' => $model->crm_record_id,
                'crmProvider' => $model->crm_connection_id !== null ? ($model->crmConnection?->provider->label() ?? 'CRM') : null,
                // Перехват диалога оператором (живой чат): кто и активен ли сейчас.
                'operatorActive' => $model->isOperatorHandling(),
                'operatorName' => $model->isOperatorHandling() ? $model->operator?->name : null,
            ],
            'outcomes' => array_map(
                fn (ConversationOutcome $o): array => ['value' => $o->value, 'label' => $o->label()],
                ConversationOutcome::cases(),
            ),
            'messages' => $this->messages->allForConversation($model)->map($this->presentMessage(...))->all(),
            'canReply' => $request->user()->allows('conversations.edit'),
        ]);
    }

    /**
     * Лайв-поллинг: новые сообщения после $after + текущее состояние перехвата.
     */
    public function messages(Request $request, string $conversation): JsonResponse
    {
        $model = $this->conversations->findForCurrentTenant($conversation);
        abort_if($model === null, 404);

        $after = trim((string) $request->query('after', ''));

        return response()->json([
            'messages' => $this->messages->sinceForConversation($model, $after !== '' ? $after : null)
                ->map($this->presentMessage(...))->values()->all(),
            'operatorActive' => $model->isOperatorHandling(),
            'operatorName' => $model->isOperatorHandling() ? $model->operator?->name : null,
            'status' => $model->status->value,
            'statusLabel' => $model->status->label(),
        ]);
    }

    /** Оператор перехватывает диалог у бота (бот замолкает). */
    public function takeover(Request $request, string $conversation): JsonResponse
    {
        abort_unless($request->user()->allows('conversations.edit'), 403);

        $model = $this->conversations->findForCurrentTenant($conversation);
        abort_if($model === null, 404);

        $this->handoff->takeOver($model, (int) $request->user()->id);

        return response()->json(['operatorActive' => true, 'operatorName' => $request->user()->name]);
    }

    /** Оператор возвращает диалог боту. */
    public function release(Request $request, string $conversation): JsonResponse
    {
        abort_unless($request->user()->allows('conversations.edit'), 403);

        $model = $this->conversations->findForCurrentTenant($conversation);
        abort_if($model === null, 404);

        $this->handoff->release($model);

        return response()->json(['operatorActive' => false]);
    }

    /**
     * Оператор печатает ответ — эфемерный сигнал в виджет («оператор печатает»).
     * Только при активном перехвате (иначе индикатор бессмыслен). Без тела ответа.
     */
    public function typing(Request $request, string $conversation): JsonResponse
    {
        abort_unless($request->user()->allows('conversations.edit'), 403);

        $model = $this->conversations->findForCurrentTenant($conversation);
        abort_if($model === null, 404);

        // Печатает оператор → видит только клиент веб-виджета: канал выводим из
        // диалога (channel_id + id сессии посетителя = external_chat_id).
        if ($model->isOperatorHandling() && $model->channel?->type === ChannelType::Web && $model->external_chat_id !== '') {
            OperatorTyping::dispatch(WidgetRealtimeChannel::name((string) $model->channel_id, $model->external_chat_id));
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Ответ оператора клиенту (только при активном перехвате). Можно приложить фото
     * (`image`) — тогда текст необязателен; картинка уходит в канал и сохраняется
     * к диалогу (в мессенджеры — загрузкой через шлюз, в веб-виджет — поллингом).
     */
    public function reply(Request $request, string $conversation): JsonResponse
    {
        abort_unless($request->user()->allows('conversations.edit'), 403);

        $model = $this->conversations->findForCurrentTenant($conversation);
        abort_if($model === null, 404);

        abort_unless($model->isOperatorHandling(), 422, 'Сначала перехватите диалог.');

        $data = $request->validate([
            'text' => ['required_without:image', 'nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'],
        ]);

        $images = [];
        if ($request->hasFile('image')) {
            $stored = $this->images->store((string) $model->getAttribute('tenant_id'), [$request->file('image')], 'operator');
            $images = array_map(static fn (array $i): string => $i['url'], $stored);
        }

        $message = $this->handoff->reply($model, (string) ($data['text'] ?? ''), $images);

        return response()->json(['message' => $this->presentMessage($message)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentMessage(Message $m): array
    {
        return [
            'id' => $m->id,
            'direction' => $m->direction->value,
            'text' => (string) $m->text,
            'images' => $this->messageImages($m),
            'time' => $m->created_at?->format('H:i'),
            'date' => $m->created_at?->format('d.m.Y'),
        ];
    }

    /**
     * URL картинок сообщения из `payload.images`. Фото клиента из веб-виджета лежат
     * как {path, url}, ответ оператора — как строки-URL. Поддерживаем оба формата.
     *
     * @return list<string>
     */
    private function messageImages(Message $m): array
    {
        $images = $m->payload['images'] ?? null;

        if (! is_array($images)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($img): ?string => is_array($img)
                ? (is_string($img['url'] ?? null) ? $img['url'] : null)
                : (is_string($img) ? $img : null),
            $images,
        )));
    }

    /**
     * Админ вручную выставляет итог лида (любой из ConversationOutcome) — статус
     * диалога синхронизируется автоматически.
     */
    public function setStatus(Request $request, string $conversation): RedirectResponse
    {
        // Право-действие: редактирование лидов (владелец — всегда).
        abort_unless($request->user()->allows('conversations.edit'), 403);

        $model = $this->conversations->findForCurrentTenant($conversation);

        abort_if($model === null, 404);

        $validated = $request->validate([
            'outcome' => ['required', Rule::enum(ConversationOutcome::class)],
        ]);

        $outcome = ConversationOutcome::from((string) $validated['outcome']);
        $this->conversations->setOutcome($model, $outcome);

        // Статус «Отменён» — отменяем и запись в CRM (если у лида она есть).
        if ($outcome === ConversationOutcome::Cancelled) {
            $this->booking->cancelBookingForConversation($model);
        }

        return back()->with('success', "Лид: статус «{$outcome->label()}».");
    }

    public function destroy(Request $request, string $conversation): RedirectResponse
    {
        // Право-действие: удаление лидов (владелец — всегда).
        abort_unless($request->user()->allows('conversations.delete'), 403);

        $model = $this->conversations->findForCurrentTenant($conversation);

        abort_if($model === null, 404);

        // Удаляем лид — если у него есть запись в CRM, отменяем её там же.
        $this->booking->cancelBookingForConversation($model);
        $this->conversations->delete($model);

        // Со страницы самого диалога back() вернул бы на удалённую страницу
        // (ошибка) — уходим в журнал; из грида back() сохраняет фильтры/страницу.
        $fromDetail = str_contains((string) $request->headers->get('referer', ''), route('cabinet.conversations.show', $model->id, false));

        return $fromDetail
            ? redirect()->route('cabinet.conversations.index')->with('success', 'Лид удалён.')
            : back()->with('success', 'Лид удалён.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Conversation $c): array
    {
        return [
            'id' => $c->id,
            'contact' => $this->contact($c),
            'phone' => $this->displayPhone($c),
            'channel' => $c->channel?->type->label() ?? '—',
            'source' => $this->source($c->channel),
            'outcome' => $c->outcome()->value,
            'outcomeLabel' => $c->outcome()->label(),
            'messagesCount' => (int) $c->getAttribute('messages_count'),
            'lastMessage' => $c->latestMessage?->text,
            'lastMessageAt' => $c->last_message_at?->format('d.m.Y H:i'),
            'createdAt' => $c->created_at?->format('d.m.Y H:i'),
        ];
    }

    /**
     * Имя клиента для отображения. Источник правды — карточка клиента (по
     * `client_id`), если лид к ней привязан; иначе захваченное по диалогу имя;
     * иначе нейтральное «Гость». Так правка имени в карточке отражается на лидах.
     */
    private function contact(Conversation $c): string
    {
        $name = $c->displayName();

        return $name !== null && $name !== '' ? $name : 'Гость';
    }

    /** Телефон для отображения: из карточки клиента, иначе захваченный по диалогу. */
    private function displayPhone(Conversation $c): ?string
    {
        return $c->displayPhone();
    }

    /**
     * Откуда пришёл клиент: тип канала + конкретный источник (домен сайта для
     * веб-виджета, id бота для Telegram) — чтобы различать несколько сайтов/ботов.
     */
    private function source(?Channel $channel): string
    {
        if ($channel === null) {
            return '—';
        }

        $detail = match ($channel->type) {
            ChannelType::Web => $this->siteHost($channel),
            ChannelType::Telegram => $channel->external_id !== null ? 'бот '.$channel->external_id : null,
            default => null,
        };

        return $detail !== null ? $channel->type->label().' · '.$detail : $channel->type->label();
    }

    private function siteHost(Channel $channel): ?string
    {
        $origins = $channel->settings['allowed_origins'] ?? [];

        if (! is_array($origins) || $origins === []) {
            return null;
        }

        $host = parse_url((string) $origins[0], PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }
}

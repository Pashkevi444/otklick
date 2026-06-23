<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Enums\ChannelType;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\BookingFlow;
use App\Services\ConversationHandoffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', '')) ?: null;
        $channel = ChannelType::tryFrom((string) $request->query('channel', ''));
        $sort = in_array($request->query('sort'), ['last', 'contact', 'messages'], true) ? (string) $request->query('sort') : 'last';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $page = $this->conversations->paginateForCurrentTenant($search, null, $channel, $sort, $dir, 15);

        return Inertia::render('Cabinet/Conversations/Index', [
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
                'channel' => $channel instanceof ChannelType ? $channel->value : '',
                'sort' => $sort,
                'dir' => $dir,
            ],
            'channels' => array_map(
                fn (string $type): array => ['value' => $type, 'label' => ChannelType::tryFrom($type)?->label() ?? $type],
                $this->conversations->channelTypesForCurrentTenant(),
            ),
        ]);
    }

    public function show(Request $request, string $conversation): Response
    {
        $model = $this->conversations->findForCurrentTenant($conversation);

        abort_if($model === null, 404);

        return Inertia::render('Cabinet/Conversations/Show', [
            'conversation' => [
                'id' => $model->id,
                'contact' => $this->contact($model),
                'phone' => $this->displayPhone($model),
                'channel' => $model->channel?->type->label() ?? '—',
                'source' => $this->source($model->channel),
                'contactRef' => $model->contact_ref,
                'createdAt' => $model->created_at?->format('d.m.Y'),
                // Связь лида с CRM-записью (id записи в CRM + какая CRM).
                'crmRecordId' => $model->crm_record_id,
                'crmProvider' => $model->crm_connection_id !== null ? ($model->crmConnection?->provider->label() ?? 'CRM') : null,
                // Перехват диалога оператором (живой чат): кто и активен ли сейчас.
                'operatorActive' => $model->isOperatorHandling(),
                'operatorName' => $model->isOperatorHandling() ? $model->operator?->name : null,
            ],
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

    /** Ответ оператора клиенту (только при активном перехвате). */
    public function reply(Request $request, string $conversation): JsonResponse
    {
        abort_unless($request->user()->allows('conversations.edit'), 403);

        $model = $this->conversations->findForCurrentTenant($conversation);
        abort_if($model === null, 404);

        abort_unless($model->isOperatorHandling(), 422, 'Сначала перехватите диалог.');

        $data = $request->validate(['text' => ['required', 'string', 'max:2000']]);
        $message = $this->handoff->reply($model, (string) $data['text']);

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
            'time' => $m->created_at?->format('H:i'),
            'date' => $m->created_at?->format('d.m.Y'),
        ];
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
        $fromDetail = str_contains((string) $request->headers->get('referer', ''), "/cabinet/conversations/{$model->id}");

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
            'messagesCount' => (int) $c->getAttribute('messages_count'),
            'lastMessage' => $c->latestMessage?->text,
            'lastMessageAt' => $c->last_message_at?->format('d.m.Y H:i'),
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

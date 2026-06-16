<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Enums\ChannelType;
use App\Enums\ConversationOutcome;
use App\Enums\ConversationStatus;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
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
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', '')) ?: null;
        $status = ConversationStatus::tryFrom((string) $request->query('status', ''));
        $sort = in_array($request->query('sort'), ['last', 'contact', 'messages'], true) ? (string) $request->query('sort') : 'last';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $page = $this->conversations->paginateForCurrentTenant($search, $status, $sort, $dir, 15);

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
                'status' => $status instanceof ConversationStatus ? $status->value : '',
                'sort' => $sort,
                'dir' => $dir,
            ],
            'statuses' => array_map(
                fn (ConversationStatus $s): array => ['value' => $s->value, 'label' => $s->label()],
                ConversationStatus::cases(),
            ),
        ]);
    }

    public function show(string $conversation): Response
    {
        $model = $this->conversations->findForCurrentTenant($conversation);

        abort_if($model === null, 404);

        return Inertia::render('Cabinet/Conversations/Show', [
            'conversation' => [
                'id' => $model->id,
                'contact' => $this->contact($model),
                'phone' => $model->contact_phone,
                'channel' => $model->channel?->type->label() ?? '—',
                'source' => $this->source($model->channel),
                'contactRef' => $model->contact_ref,
                'status' => $model->status->value,
                'statusLabel' => $model->status->label(),
                'outcome' => $model->outcome()->value,
                'outcomeLabel' => $model->outcome()->label(),
                'createdAt' => $model->created_at?->format('d.m.Y'),
            ],
            'outcomes' => array_map(
                fn (ConversationOutcome $o): array => ['value' => $o->value, 'label' => $o->label()],
                ConversationOutcome::cases(),
            ),
            'messages' => $this->messages->allForConversation($model)->map(fn (Message $m): array => [
                'id' => $m->id,
                'direction' => $m->direction->value,
                'text' => (string) $m->text,
                'time' => $m->created_at?->format('H:i'),
                'date' => $m->created_at?->format('d.m.Y'),
            ])->all(),
        ]);
    }

    /**
     * Админ вручную выставляет итог лида (любой из ConversationOutcome) — статус
     * диалога синхронизируется автоматически.
     */
    public function setStatus(Request $request, string $conversation): RedirectResponse
    {
        $model = $this->conversations->findForCurrentTenant($conversation);

        abort_if($model === null, 404);

        $validated = $request->validate([
            'outcome' => ['required', Rule::enum(ConversationOutcome::class)],
        ]);

        $outcome = ConversationOutcome::from((string) $validated['outcome']);
        $this->conversations->setOutcome($model, $outcome);

        return back()->with('success', "Лид: статус «{$outcome->label()}».");
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Conversation $c): array
    {
        return [
            'id' => $c->id,
            'contact' => $this->contact($c),
            'phone' => $c->contact_phone,
            'channel' => $c->channel?->type->label() ?? '—',
            'source' => $this->source($c->channel),
            'status' => $c->status->value,
            'statusLabel' => $c->status->label(),
            'messagesCount' => (int) $c->getAttribute('messages_count'),
            'lastMessage' => $c->latestMessage?->text,
            'lastMessageAt' => $c->last_message_at?->format('d.m.Y H:i'),
        ];
    }

    /**
     * Имя клиента, если он представился; иначе нейтральное «Гость» (без
     * технического id чата — имя и источник теперь разведены).
     */
    private function contact(Conversation $c): string
    {
        return $c->contact_name !== null && $c->contact_name !== ''
            ? $c->contact_name
            : 'Гость';
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

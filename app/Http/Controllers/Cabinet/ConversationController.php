<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
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

    public function index(): Response
    {
        return Inertia::render('Cabinet/Conversations/Index', [
            'conversations' => $this->conversations->forCurrentTenant()->map($this->present(...))->all(),
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
                'channel' => $model->channel?->type->label() ?? '—',
                'status' => $model->status->value,
                'statusLabel' => $model->status->label(),
                'createdAt' => $model->created_at?->format('d.m.Y'),
            ],
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
     * @return array<string, mixed>
     */
    private function present(Conversation $c): array
    {
        return [
            'id' => $c->id,
            'contact' => $this->contact($c),
            'channel' => $c->channel?->type->label() ?? '—',
            'status' => $c->status->value,
            'statusLabel' => $c->status->label(),
            'messagesCount' => (int) $c->getAttribute('messages_count'),
            'lastMessage' => $c->latestMessage?->text,
            'lastMessageAt' => $c->last_message_at?->format('d.m.Y H:i'),
        ];
    }

    private function contact(Conversation $c): string
    {
        return $c->contact_name !== null && $c->contact_name !== ''
            ? $c->contact_name
            : 'Гость '.mb_substr($c->external_chat_id, 0, 6);
    }
}

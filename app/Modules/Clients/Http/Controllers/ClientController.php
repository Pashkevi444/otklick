<?php

declare(strict_types=1);

namespace App\Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clients\Http\Requests\UpdateClientRequest;
use App\Modules\Clients\Models\Client;
use App\Modules\Clients\Repositories\Contracts\ClientRepositoryInterface;
use App\Modules\Clients\Services\ClientService;
use App\Modules\Clients\Services\ClientSummaryService;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Notifications\Services\UserNotificationService;
use App\Shared\Enums\ChannelType;
use App\Shared\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * База клиентов тенанта: грид с поиском/фильтрами и карточка клиента (связанные
 * лиды + LLM-резюме). Гейтится тарифом (`plan:clientBase`). Скоупится тенантом.
 */
final class ClientController extends Controller
{
    public function __construct(
        private readonly ClientRepositoryInterface $clients,
        private readonly ClientSummaryService $summaries,
        private readonly ClientService $service,
        private readonly UserNotificationService $notifications,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', '')) ?: null;
        $channel = trim((string) $request->query('channel', '')) ?: null;
        $sort = in_array($request->query('sort'), ['last', 'name', 'first'], true) ? (string) $request->query('sort') : 'last';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $page = $this->clients->paginateForCurrentTenant($search, $channel, $sort, $dir, 15);

        // Новые клиенты (с непрочитанным уведомлением) — подсвечиваем «Новый» в списке.
        // Метку НЕ гасим при открытии списка: она держится, пока не откроют карточку
        // клиента (тогда `show()` пометит уведомление прочитанным) — как у уведомлений,
        // чтобы было видно, кого ещё не смотрели. Per-user (уведомления привязаны к user).
        $user = $request->user();
        $newIds = $user instanceof User ? $this->notifications->unreadEntityIds($user, 'client') : [];

        return Inertia::render('Cabinet/Clients/Index', [
            'newClientIds' => $newIds,
            'clients' => array_map($this->present(...), $page->items()),
            'pagination' => [
                'current' => $page->currentPage(),
                'last' => $page->lastPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
            'filters' => [
                'search' => $search ?? '',
                'channel' => $channel ?? '',
                'sort' => $sort,
                'dir' => $dir,
            ],
            'channels' => array_map(
                fn (string $c): array => ['value' => $c, 'label' => ChannelType::tryFrom($c)?->label() ?? $c],
                $this->clients->channelsForCurrentTenant(),
            ),
        ]);
    }

    /** «Прочитать всё»: гасит подсветку «Новый» у всех клиентов (per-user). */
    public function readAll(Request $request): RedirectResponse
    {
        if (($user = $request->user()) instanceof User) {
            $this->notifications->markEntityTypeRead($user, 'client');
        }

        return back();
    }

    public function show(Request $request, string $client): Response
    {
        $model = $this->findOrFail($client);
        $model->load(['conversations' => fn ($q) => $q->with('channel')->latest()]);

        // Открыл карточку клиента → уведомление «новый клиент» по нему гаснет.
        if (($user = $request->user()) instanceof User) {
            $this->notifications->markEntityRead($user, 'client', (string) $model->id);
        }

        return Inertia::render('Cabinet/Clients/Show', [
            'client' => $this->presentFull($model),
            'conversations' => $model->conversations->map($this->presentConversation(...))->all(),
        ]);
    }

    public function update(UpdateClientRequest $request, string $client): RedirectResponse
    {
        abort_unless($request->user()->allows('clients.edit'), 403);

        $model = $this->findOrFail($client);

        $this->clients->update($model, [
            'name' => $request->input('name') ?: null,
            'phone' => $request->input('phone') ?: null,
            'email' => $request->input('email') ?: null,
            'telegram_username' => $request->input('telegram_username') ?: null,
            'notes' => $request->input('notes') ?: null,
        ]);

        return back()->with('success', 'Карточка клиента обновлена.');
    }

    /** Пересобрать краткое резюме по переписке (синхронно — владелец ждёт ответ). */
    public function refreshSummary(Request $request, string $client): RedirectResponse
    {
        abort_unless($request->user()->allows('clients.edit'), 403);

        $model = $this->findOrFail($client);
        $summary = $this->summaries->summarize($model);

        if ($summary === null) {
            return back()->withErrors(['summary' => 'Пока недостаточно переписки, чтобы составить резюме.']);
        }

        $this->clients->update($model, ['summary' => $summary, 'summary_generated_at' => now()]);

        return back()->with('success', 'Резюме обновлено.');
    }

    /** Заблокировать клиента: бот перестаёт вести с ним диалог (без LLM). */
    public function ban(Request $request, string $client): RedirectResponse
    {
        abort_unless($request->user()->allows('clients.edit'), 403);

        $this->clients->update($this->findOrFail($client), ['banned_at' => now()]);

        return back()->with('success', 'Клиент заблокирован.');
    }

    /** Снять блокировку клиента. */
    public function unban(Request $request, string $client): RedirectResponse
    {
        abort_unless($request->user()->allows('clients.edit'), 403);

        $this->clients->update($this->findOrFail($client), ['banned_at' => null]);

        return back()->with('success', 'Клиент разблокирован.');
    }

    public function destroy(Request $request, string $client): RedirectResponse
    {
        abort_unless($request->user()->allows('clients.delete'), 403);

        $model = $this->findOrFail($client);
        $this->service->delete($model);

        // Из карточки клиента back() вёл бы на удалённую страницу — уходим в грид;
        // из грида back() сохраняет фильтры/страницу.
        $fromDetail = str_contains((string) $request->headers->get('referer', ''), route('cabinet.clients.show', $model->id, false));

        return $fromDetail
            ? redirect()->route('cabinet.clients.index')->with('success', 'Клиент удалён.')
            : back()->with('success', 'Клиент удалён.');
    }

    private function findOrFail(string $id): Client
    {
        $client = $this->clients->find($id);

        abort_if($client === null, 404);

        return $client;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Client $client): array
    {
        return [
            'id' => $client->id,
            'name' => $client->name,
            'phone' => $client->phone,
            'email' => $client->email,
            'telegram_username' => $client->telegram_username,
            'channel' => $client->first_channel_type !== null
                ? (ChannelType::tryFrom($client->first_channel_type)?->label() ?? $client->first_channel_type)
                : null,
            'conversations_count' => (int) $client->getAttribute('conversations_count'),
            'has_summary' => $client->summary !== null && $client->summary !== '',
            'last_seen_at' => $client->last_seen_at?->toDateString(),
            'banned' => $client->isBanned(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentFull(Client $client): array
    {
        return [
            'id' => $client->id,
            'name' => $client->name,
            'phone' => $client->phone,
            'email' => $client->email,
            'telegram_username' => $client->telegram_username,
            'channel' => $client->first_channel_type !== null
                ? (ChannelType::tryFrom($client->first_channel_type)?->label() ?? $client->first_channel_type)
                : null,
            'first_seen_at' => $client->first_seen_at?->toDateString(),
            'last_seen_at' => $client->last_seen_at?->toDateString(),
            'summary' => $client->summary,
            'summary_generated_at' => $client->summary_generated_at?->toDateTimeString(),
            'notes' => $client->notes,
            'banned' => $client->isBanned(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentConversation(Conversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'channel' => $conversation->channel?->type->label() ?? '—',
            'outcome' => $conversation->outcome()->label(),
            'booked' => $conversation->booked_at !== null,
            'created_at' => $conversation->created_at?->toDateString(),
        ];
    }
}

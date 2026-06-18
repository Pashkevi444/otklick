<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Enums\ChannelType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cabinet\UpdateClientRequest;
use App\Models\Client;
use App\Models\Conversation;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Services\ClientSummaryService;
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
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', '')) ?: null;
        $channel = trim((string) $request->query('channel', '')) ?: null;
        $sort = in_array($request->query('sort'), ['last', 'name', 'first'], true) ? (string) $request->query('sort') : 'last';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $page = $this->clients->paginateForCurrentTenant($search, $channel, $sort, $dir, 15);

        return Inertia::render('Cabinet/Clients/Index', [
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

    public function show(string $client): Response
    {
        $model = $this->findOrFail($client);
        $model->load(['conversations' => fn ($q) => $q->with('channel')->latest()]);

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

    public function destroy(Request $request, string $client): RedirectResponse
    {
        abort_unless($request->user()->allows('clients.delete'), 403);

        $this->clients->delete($this->findOrFail($client));

        return redirect()->route('cabinet.clients.index')->with('success', 'Клиент удалён.');
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

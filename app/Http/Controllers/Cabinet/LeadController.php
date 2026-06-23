<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\DTO\LeadData;
use App\Enums\CrmSource;
use App\Enums\CustomFieldEntity;
use App\Enums\GridEntity;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\Contracts\LeadRepositoryInterface;
use App\Services\CustomFieldService;
use App\Services\GridViewService;
use App\Services\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Входящие лиды в кабинете: список, ручное создание, конвертация в сделку,
 * отклонение. Данные скоупятся текущим тенантом.
 */
final class LeadController extends Controller
{
    public function __construct(
        private readonly LeadService $service,
        private readonly LeadRepositoryInterface $leads,
        private readonly ClientRepositoryInterface $clients,
        private readonly CustomFieldService $fields,
        private readonly GridViewService $views,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Cabinet/Leads/Index', [
            'leads' => $this->leads->forCurrentTenant()->map($this->present(...))->all(),
            'clients' => $this->clients->pickerListForCurrentTenant(),
            'fields' => $this->fields->present(CustomFieldEntity::Lead),
            'views' => $this->views->present((int) $request->user()->id, GridEntity::Lead),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeEdit($request);
        $data = $request->validate([
            'client_id' => ['nullable', 'string', 'exists:clients,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->service->createManual(new LeadData(
            clientId: $data['client_id'] ?? null,
            title: $data['title'] ?? null,
            source: CrmSource::Manual,
            notes: $data['notes'] ?? null,
            custom: $this->fields->sanitize(CustomFieldEntity::Lead, $request->input('custom', [])),
        ));

        return back()->with('success', 'Лид добавлен.');
    }

    public function update(Request $request, string $lead): RedirectResponse
    {
        $this->authorizeEdit($request);
        $model = $this->findOrFail($lead);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'string', 'in:new,working,dismissed'],
        ]);

        // Трогаем только присланные поля: смена статуса (напр. «Отклонить»)
        // не должна затирать название/заметки/кастом-поля.
        $attrs = [];
        if ($request->has('title')) {
            $attrs['title'] = $data['title'] ?? null;
        }
        if ($request->has('notes')) {
            $attrs['notes'] = $data['notes'] ?? null;
        }
        if (isset($data['status'])) {
            $attrs['status'] = LeadStatus::from($data['status']);
        }
        if ($request->has('custom')) {
            $attrs['custom'] = $this->fields->sanitize(CustomFieldEntity::Lead, $request->input('custom', []));
        }
        if ($attrs !== []) {
            $this->leads->update($model, $attrs);
        }

        return back()->with('success', 'Лид обновлён.');
    }

    /** Конвертация лида в сделку: создаёт сделку и связывает. */
    public function convert(Request $request, string $lead): RedirectResponse
    {
        $this->authorizeEdit($request);
        $deal = $this->service->convertToDeal($this->findOrFail($lead));

        return $deal === null
            ? back()->withErrors(['convert' => 'Не удалось создать сделку.'])
            : redirect()->route('cabinet.deals.index')->with('success', 'Лид сконвертирован в сделку.');
    }

    public function destroy(Request $request, string $lead): RedirectResponse
    {
        $this->authorizeEdit($request);
        $this->leads->delete($this->findOrFail($lead));

        return back()->with('success', 'Лид удалён.');
    }

    /** Изменение лидов требует права-действия `leads.edit`. */
    private function authorizeEdit(Request $request): void
    {
        abort_unless($request->user()->allows('leads.edit'), 403);
    }

    private function findOrFail(string $id): Lead
    {
        $lead = $this->leads->find($id);

        abort_if($lead === null, 404);

        return $lead;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'title' => $lead->title,
            'status' => $lead->status->value,
            'statusLabel' => $lead->status->label(),
            'source' => $lead->source->value,
            'notes' => $lead->notes,
            'client' => $lead->client !== null ? ['id' => $lead->client->id, 'name' => $lead->client->name, 'phone' => $lead->client->phone] : null,
            'deal_id' => $lead->deal_id,
            'custom' => $lead->custom ?? new \stdClass,
            'created_at' => $lead->created_at?->toDateString(),
        ];
    }
}

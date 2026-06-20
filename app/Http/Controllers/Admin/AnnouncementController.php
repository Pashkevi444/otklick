<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AnnouncementType;
use App\Http\Controllers\Controller;
use App\Services\AnnouncementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Управление анонсами площадки супер-админом: новости и обновления (патчи) —
 * создание, правка, публикация, удаление. Бизнесы видят опубликованное.
 */
final class AnnouncementController extends Controller
{
    public function __construct(private readonly AnnouncementService $announcements) {}

    public function news(): Response
    {
        return $this->page(AnnouncementType::News);
    }

    public function updates(): Response
    {
        return $this->page(AnnouncementType::Update);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateInput($request, withType: true);

        $type = AnnouncementType::from((string) $data['type']);
        $this->announcements->create($type, $data['title'], $data['body'], (bool) ($data['is_published'] ?? false));

        return back()->with('success', 'Анонс сохранён.');
    }

    public function update(Request $request, string $announcement): RedirectResponse
    {
        $data = $this->validateInput($request, withType: false);

        $updated = $this->announcements->update($announcement, $data['title'], $data['body'], (bool) ($data['is_published'] ?? false));
        abort_if($updated === null, HttpResponse::HTTP_NOT_FOUND);

        return back()->with('success', 'Анонс обновлён.');
    }

    public function destroy(string $announcement): RedirectResponse
    {
        abort_unless($this->announcements->delete($announcement), HttpResponse::HTTP_NOT_FOUND);

        return back()->with('success', 'Анонс удалён.');
    }

    private function page(AnnouncementType $type): Response
    {
        return Inertia::render('Admin/Announcements/Index', [
            'type' => $type->value,
            'title' => $type->label(),
            'items' => $this->announcements->adminList($type),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateInput(Request $request, bool $withType): array
    {
        return $request->validate([
            'type' => [Rule::requiredIf($withType), Rule::in(AnnouncementType::values())],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'is_published' => ['boolean'],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Modules\Platform\Services\AnnouncementService;
use App\Shared\Enums\AnnouncementType;
use App\Shared\Http\Controller;
use App\Shared\Support\AnnouncementImageStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Управление анонсами площадки супер-админом: новости и обновления (патчи) —
 * форматированный текст с картинками, публикация, удаление. Бизнесы видят
 * опубликованное.
 */
final class AnnouncementController extends Controller
{
    public function __construct(
        private readonly AnnouncementService $announcements,
        private readonly AnnouncementImageStorage $images,
    ) {}

    public function news(Request $request): Response
    {
        return $this->page(AnnouncementType::News, $request->query('search'));
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

    /** Загрузка картинки для вставки в текст анонса (редактор). */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate(['image' => ['required', 'image', 'max:5120']]);

        return response()->json(['url' => $this->images->store($request->file('image'))]);
    }

    private function page(AnnouncementType $type, ?string $search = null): Response
    {
        $search = is_string($search) ? trim($search) : null;

        return Inertia::render('Admin/Announcements/Index', [
            'type' => $type->value,
            'title' => $type->label(),
            'search' => $search,
            'page' => $this->announcements->adminPaginated($type, $search !== '' ? $search : null),
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

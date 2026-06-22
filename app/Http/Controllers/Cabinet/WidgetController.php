<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Enums\ChannelType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cabinet\UpdateWidgetAppearanceRequest;
use App\Http\Requests\Cabinet\UpdateWidgetRequest;
use App\Models\Channel;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use App\Services\ChannelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Веб-виджет (чат на сайт) в кабинете тенанта: подключение, код для вставки и
 * список разрешённых доменов. Данные скоупятся текущим тенантом.
 */
final class WidgetController extends Controller
{
    /** Брендовый акцент «Отклик» по умолчанию (если бизнес не выбрал свой). */
    private const DEFAULT_COLOR = '#2E74B5';

    public function __construct(
        private readonly ChannelRepositoryInterface $channels,
        private readonly ChannelService $channelService,
    ) {}

    public function index(): Response
    {
        $channel = $this->webChannel();
        $base = $this->widgetBase();

        return Inertia::render('Cabinet/Widget/Index', [
            'widget' => $channel === null ? null : [
                'id' => $channel->id,
                'isActive' => $channel->is_active,
                'allowedOrigins' => $channel->settings['allowed_origins'] ?? [],
                'color' => $channel->settings['widget_color'] ?? self::DEFAULT_COLOR,
                'scriptUrl' => $base.'/widget/v1/widget.js',
                'snippet' => $this->snippet($base, $channel),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->webChannel() === null) {
            $this->channelService->connectWeb((string) $request->user()?->tenant_id);
        }

        return redirect()->route('cabinet.widget.index')->with('success', 'Виджет подключён.');
    }

    public function update(UpdateWidgetRequest $request, string $channel): RedirectResponse
    {
        $model = $this->requireWebChannel($channel);

        $origins = preg_split('/[\s,]+/', trim((string) $request->string('origins')), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $this->channelService->setWidgetOrigins($model, $origins);

        return redirect()->route('cabinet.widget.index')->with('success', 'Список доменов сохранён.');
    }

    public function appearance(UpdateWidgetAppearanceRequest $request, string $channel): RedirectResponse
    {
        $model = $this->requireWebChannel($channel);

        $color = trim((string) $request->string('color'));

        $this->channelService->setWidgetColor($model, $color !== '' ? $color : null);

        return redirect()->route('cabinet.widget.index')->with('success', 'Оформление виджета сохранено.');
    }

    public function destroy(string $channel): RedirectResponse
    {
        $this->channels->delete($this->requireWebChannel($channel));

        return redirect()->route('cabinet.widget.index')->with('success', 'Виджет отключён.');
    }

    private function webChannel(): ?Channel
    {
        return $this->channels->forCurrentTenant()->firstWhere('type', ChannelType::Web);
    }

    private function requireWebChannel(string $id): Channel
    {
        $model = $this->channels->find($id);

        abort_if($model === null || $model->type !== ChannelType::Web, 404);

        return $model;
    }

    private function widgetBase(): string
    {
        $businessDomain = config('app.business_domain');

        return is_string($businessDomain) && $businessDomain !== ''
            ? 'https://'.$businessDomain
            : rtrim((string) config('app.url'), '/');
    }

    private function snippet(string $base, Channel $channel): string
    {
        return '<script src="'.$base.'/widget/v1/widget.js" '
            .'data-otklik-tenant="'.$channel->tenant_id.'" '
            .'data-otklik-channel="'.$channel->id.'" defer></script>';
    }
}

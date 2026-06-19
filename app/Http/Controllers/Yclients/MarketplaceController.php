<?php

declare(strict_types=1);

namespace App\Http\Controllers\Yclients;

use App\Http\Controllers\Controller;
use App\Services\YclientsMarketplaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Подключение YClients через маркетплейс.
 *
 *  - {@see connect()}  — Registration Redirect: залогиненный бизнес возвращается из
 *    маркетплейса, привязываем его филиал к тенанту (авторизованный кабинет-роут).
 *  - {@see webhook()}  — server-to-server вебхук YClients с salon_id и user-токеном
 *    (публичный, без сессии/CSRF; подлинность — по партнёрскому токену в теле).
 *  - {@see disconnect()} — callback YClients об отключении интеграции (публичный).
 */
final class MarketplaceController extends Controller
{
    public function __construct(
        private readonly YclientsMarketplaceService $marketplace,
    ) {}

    public function connect(Request $request): RedirectResponse
    {
        $salonId = trim((string) $request->query('salon_id', ''));
        $tenantId = (string) $request->user()?->tenant_id;

        if ($salonId === '') {
            return redirect()->route('cabinet.integrations.index')
                ->with('error', 'YClients не передал идентификатор филиала. Подключите интеграцию ещё раз из маркетплейса.');
        }

        $this->marketplace->claimSalon($salonId, $tenantId);

        return redirect()->route('cabinet.integrations.index')
            ->with('success', 'Филиал YClients привязан. Интеграция активируется автоматически.');
    }

    public function webhook(Request $request): Response
    {
        $this->guardPartner($request);

        $this->marketplace->ingestWebhook($request->all());

        return response()->noContent();
    }

    public function disconnect(Request $request): Response
    {
        $this->guardPartner($request);

        $salonId = trim((string) ($request->input('salon_id') ?? $request->input('company_id') ?? ''));

        if ($salonId !== '') {
            $this->marketplace->disconnect($salonId);
        }

        return response()->noContent();
    }

    /**
     * Если в теле есть партнёрский токен — он обязан совпасть с нашим (подлинность
     * вебхука YClients). Когда токена в payload нет — пропускаем: материализация
     * всё равно требует, чтобы тенант сам привязал филиал из кабинета.
     */
    private function guardPartner(Request $request): void
    {
        $expected = config('services.yclients.partner_token');
        $incoming = $request->input('partner_token');

        if (is_string($expected) && $expected !== '' && is_string($incoming) && $incoming !== '') {
            abort_unless(hash_equals($expected, $incoming), Response::HTTP_FORBIDDEN);
        }
    }
}

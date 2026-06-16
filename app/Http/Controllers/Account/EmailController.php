<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ConfirmEmailChangeRequest;
use App\Http\Requests\Account\RequestEmailChangeRequest;
use App\Services\EmailChangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Смена собственного e-mail с подтверждением кодом, отправленным на новый адрес.
 */
final class EmailController extends Controller
{
    public function __construct(private readonly EmailChangeService $emails) {}

    public function edit(Request $request): Response
    {
        return Inertia::render('Account/Email', [
            'currentEmail' => $request->user()->email,
            'pendingEmail' => $this->emails->pendingEmail($request->user()),
        ]);
    }

    public function requestChange(RequestEmailChangeRequest $request): RedirectResponse
    {
        $this->emails->request($request->user(), (string) $request->string('new_email'));

        return back()->with('success', 'Код подтверждения отправлен на новый адрес.');
    }

    public function confirm(ConfirmEmailChangeRequest $request): RedirectResponse
    {
        $ok = $this->emails->confirm($request->user(), (string) $request->string('code'));

        if (! $ok) {
            return back()->withErrors(['code' => 'Неверный или просроченный код.']);
        }

        return back()->with('success', 'Почта изменена.');
    }
}

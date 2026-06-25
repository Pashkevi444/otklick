<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Mail\PasswordResetCodeMail;
use App\Modules\Identity\Repositories\Contracts\PasswordResetCodeRepositoryInterface;
use App\Modules\Identity\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Восстановление пароля по одноразовому коду из письма. Код живёт 6 минут,
 * в БД хранится только его хеш. Существование email не раскрывается.
 */
final readonly class PasswordResetService
{
    public const CODE_TTL_MINUTES = 6;

    public function __construct(
        private UserRepositoryInterface $users,
        private PasswordResetCodeRepositoryInterface $codes,
    ) {}

    /**
     * Сгенерировать код и отправить письмо, если email зарегистрирован.
     */
    public function sendCode(string $email): void
    {
        if ($this->users->findByEmail($email) === null) {
            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->codes->put($email, Hash::make($code));

        Mail::to($email)->send(new PasswordResetCodeMail($code, self::CODE_TTL_MINUTES));
    }

    /**
     * Сменить пароль по коду. Возвращает false при неверном/просроченном коде.
     */
    public function reset(string $email, string $code, string $newPassword): bool
    {
        $record = $this->codes->get($email);

        if ($record === null) {
            return false;
        }

        $expired = $record->createdAt->copy()->addMinutes(self::CODE_TTL_MINUTES)->isPast();

        if ($expired || ! Hash::check($code, $record->hashedCode)) {
            return false;
        }

        $user = $this->users->findByEmail($email);

        if ($user === null) {
            return false;
        }

        $user->update(['password' => $newPassword]); // каст 'hashed' хеширует
        $this->codes->delete($email);

        return true;
    }
}

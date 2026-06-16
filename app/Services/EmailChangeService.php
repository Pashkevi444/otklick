<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\EmailChangeCodeMail;
use App\Models\User;
use App\Repositories\Contracts\EmailChangeCodeRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Смена e-mail с подтверждением: код уходит на НОВЫЙ адрес и живёт 15 минут;
 * почта меняется только после ввода верного кода. В БД — только хеш кода.
 */
final readonly class EmailChangeService
{
    public const CODE_TTL_MINUTES = 15;

    public function __construct(
        private UserRepositoryInterface $users,
        private EmailChangeCodeRepositoryInterface $codes,
    ) {}

    /**
     * Сгенерировать код и отправить на новый адрес.
     */
    public function request(User $user, string $newEmail): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->codes->put((int) $user->id, $newEmail, Hash::make($code));

        Mail::to($newEmail)->send(new EmailChangeCodeMail($code, self::CODE_TTL_MINUTES));
    }

    /**
     * Подтвердить смену по коду. false — нет заявки/просрочен/неверный код/адрес занят.
     */
    public function confirm(User $user, string $code): bool
    {
        $record = $this->codes->get((int) $user->id);

        if ($record === null) {
            return false;
        }

        $expired = $record->createdAt->copy()->addMinutes(self::CODE_TTL_MINUTES)->isPast();

        if ($expired || ! Hash::check($code, $record->hashedCode)) {
            return false;
        }

        // Адрес мог быть занят другим пользователем за время ожидания.
        $taken = $this->users->findByEmail($record->newEmail);
        if ($taken !== null && $taken->id !== $user->id) {
            return false;
        }

        $user->forceFill([
            'email' => $record->newEmail,
            'email_verified_at' => now(),
        ])->save();

        $this->codes->delete((int) $user->id);

        return true;
    }

    public function pendingEmail(User $user): ?string
    {
        return $this->codes->get((int) $user->id)?->newEmail;
    }
}

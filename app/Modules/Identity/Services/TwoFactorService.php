<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Shared\Models\User;
use Illuminate\Support\Str;
use PragmaRX\Google2FAQRCode\Google2FA;

/**
 * Двухфакторная аутентификация по TOTP (RFC 6238) — совместима с Google
 * Authenticator и любыми TOTP-приложениями. Секрет и резервные коды хранятся
 * зашифрованными (касты модели User). Подтверждение 2FA — вводом кода.
 */
final readonly class TwoFactorService
{
    private const string ISSUER = 'Отклик';

    private const int RECOVERY_CODES = 8;

    public function __construct(private Google2FA $google2fa) {}

    /**
     * Начинает подключение: новый секрет + резервные коды, ещё НЕ подтверждено.
     */
    public function generate(User $user): void
    {
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODES; $i++) {
            $codes[] = Str::lower(Str::random(5)).'-'.Str::lower(Str::random(5));
        }

        $user->forceFill([
            'two_factor_secret' => $this->google2fa->generateSecretKey(),
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    /**
     * Подтверждает подключение вводом кода из приложения.
     */
    public function confirm(User $user, string $code): bool
    {
        if ($user->two_factor_secret === null || ! $this->google2fa->verifyKey($user->two_factor_secret, $code)) {
            return false;
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return true;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    /**
     * Проверка при входе: код приложения ИЛИ одноразовый резервный код.
     */
    public function verify(User $user, string $code): bool
    {
        if ($user->two_factor_secret !== null && $this->google2fa->verifyKey($user->two_factor_secret, $code)) {
            return true;
        }

        return $this->consumeRecoveryCode($user, $code);
    }

    /**
     * Инлайн-QR (data-URI) для добавления в приложение-аутентификатор.
     */
    public function qrCodeInline(User $user): string
    {
        return $this->google2fa->getQRCodeInline(self::ISSUER, $user->email, (string) $user->two_factor_secret);
    }

    /**
     * @return array<int, string>
     */
    public function recoveryCodes(User $user): array
    {
        return $user->two_factor_recovery_codes ?? [];
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];
        $code = Str::lower(trim($code));

        if (! in_array($code, $codes, true)) {
            return false;
        }

        $user->forceFill([
            'two_factor_recovery_codes' => array_values(array_filter($codes, fn (string $c): bool => $c !== $code)),
        ])->save();

        return true;
    }
}

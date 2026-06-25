<?php

declare(strict_types=1);

namespace App\Modules\Identity\Repositories\Eloquent;

use App\Modules\Identity\DTO\PasswordResetCodeData;
use App\Modules\Identity\Repositories\Contracts\PasswordResetCodeRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Реализация на штатной таблице Laravel password_reset_tokens
 * (email PK, token, created_at). Хранит хеш кода, не сам код.
 */
final class EloquentPasswordResetCodeRepository implements PasswordResetCodeRepositoryInterface
{
    private const TABLE = 'password_reset_tokens';

    public function put(string $email, string $hashedCode): void
    {
        DB::table(self::TABLE)->updateOrInsert(
            ['email' => $email],
            ['token' => $hashedCode, 'created_at' => Carbon::now()],
        );
    }

    public function get(string $email): ?PasswordResetCodeData
    {
        $row = DB::table(self::TABLE)->where('email', $email)->first();

        if ($row === null) {
            return null;
        }

        return new PasswordResetCodeData(
            hashedCode: (string) $row->token,
            createdAt: Carbon::parse($row->created_at),
        );
    }

    public function delete(string $email): void
    {
        DB::table(self::TABLE)->where('email', $email)->delete();
    }
}

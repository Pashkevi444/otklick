<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTO\EmailChangeCodeData;
use App\Repositories\Contracts\EmailChangeCodeRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class EloquentEmailChangeCodeRepository implements EmailChangeCodeRepositoryInterface
{
    private const TABLE = 'email_change_codes';

    public function put(int $userId, string $newEmail, string $hashedCode): void
    {
        DB::table(self::TABLE)->updateOrInsert(
            ['user_id' => $userId],
            ['new_email' => $newEmail, 'code_hash' => $hashedCode, 'created_at' => Carbon::now()],
        );
    }

    public function get(int $userId): ?EmailChangeCodeData
    {
        $row = DB::table(self::TABLE)->where('user_id', $userId)->first();

        if ($row === null) {
            return null;
        }

        return new EmailChangeCodeData(
            newEmail: (string) $row->new_email,
            hashedCode: (string) $row->code_hash,
            createdAt: Carbon::parse($row->created_at),
        );
    }

    public function delete(int $userId): void
    {
        DB::table(self::TABLE)->where('user_id', $userId)->delete();
    }
}

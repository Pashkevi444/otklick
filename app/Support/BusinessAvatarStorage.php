<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Хранение аватара бизнеса на публичном диске под путём тенанта.
 * Аватар показывается в карточке бизнеса (и может использоваться в виджете).
 */
final class BusinessAvatarStorage
{
    private const string DISK = 'public';

    /**
     * Сохраняет новый аватар и возвращает путь+URL.
     *
     * @return array{path: string, url: string}
     */
    public function store(string $tenantId, UploadedFile $file): array
    {
        $path = $file->store("avatars/{$tenantId}", self::DISK);

        return [
            'path' => $path,
            'url' => Storage::disk(self::DISK)->url($path),
        ];
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}

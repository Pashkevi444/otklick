<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Хранение картинок анонсов (новости/обновления) на публичном диске. Анонсы
 * глобальные и видны всем бизнесам, поэтому без привязки к тенанту.
 */
final class AnnouncementImageStorage
{
    private const string DISK = 'public';

    /** Сохранить картинку, вставляемую в текст анонса, и вернуть её публичный URL. */
    public function store(UploadedFile $file): string
    {
        $path = $file->store('announcements', self::DISK);

        return Storage::disk(self::DISK)->url($path);
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Хранение картинок базы знаний на публичном диске под путём тенанта.
 * «Примеры работ» показываются клиентам, поэтому диск public.
 */
final class KnowledgeImageStorage
{
    private const string DISK = 'public';

    /**
     * @param  list<UploadedFile>  $files
     * @return list<array{path: string, url: string}>
     */
    public function store(string $tenantId, array $files): array
    {
        $stored = [];

        foreach ($files as $file) {
            $path = $file->store("knowledge/{$tenantId}", self::DISK);
            $stored[] = [
                'path' => $path,
                'url' => Storage::disk(self::DISK)->url($path),
            ];
        }

        return $stored;
    }

    /**
     * @param  list<string>  $paths
     */
    public function delete(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk(self::DISK)->delete($paths);
        }
    }
}

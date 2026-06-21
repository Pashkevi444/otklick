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
     * @param  string  $dir  поддиректория диска (knowledge — БЗ, flows — узлы сценариев)
     * @return list<array{path: string, url: string}>
     */
    public function store(string $tenantId, array $files, string $dir = 'knowledge'): array
    {
        $stored = [];

        foreach ($files as $file) {
            $path = $file->store("{$dir}/{$tenantId}", self::DISK);
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

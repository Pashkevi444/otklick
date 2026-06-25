<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Хранение картинок тенанта на публичном диске под путём тенанта. Общее для всех
 * фич, где клиент/бизнес прикрепляет изображения: база знаний («примеры работ»),
 * узлы сценариев, веб-виджет, фото оператора, песочница теста. Поддиректория
 * задаётся аргументом `$dir`. Диск public — картинки показываются клиентам.
 */
final class TenantImageStorage
{
    private const string DISK = 'public';

    /**
     * @param  list<UploadedFile>  $files
     * @param  string  $dir  поддиректория диска (knowledge — БЗ, flows — узлы сценариев,
     *                       widget/operator/sandbox — соответствующие фичи)
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

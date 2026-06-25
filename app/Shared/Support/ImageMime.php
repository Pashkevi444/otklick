<?php

declare(strict_types=1);

namespace App\Shared\Support;

/**
 * Определяет MIME-тип картинки по «магическим» байтам (сигнатуре файла). Нужен
 * для data-URL при отправке фото в vision-модель: тип берём из самих байтов, а не
 * из (часто отсутствующего/неверного) поля канала. Неизвестное → image/jpeg.
 */
final class ImageMime
{
    public static function sniff(string $bytes): string
    {
        return match (true) {
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($bytes, "\x89PNG\x0D\x0A\x1A\x0A") => 'image/png',
            str_starts_with($bytes, 'GIF87a'), str_starts_with($bytes, 'GIF89a') => 'image/gif',
            str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP' => 'image/webp',
            default => 'image/jpeg',
        };
    }
}

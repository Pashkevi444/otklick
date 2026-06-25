<?php

declare(strict_types=1);

namespace App\Vision;

use App\Vision\Contracts\ImageToText;

/**
 * Локальный fake распознавания изображений (по умолчанию, без ключей). Возвращает
 * заранее заданное описание — для тестов и локальной разработки. По умолчанию
 * null: бот «не видит» фото и передаёт администратору (исходное поведение).
 */
final class FakeImageToText implements ImageToText
{
    public function __construct(private ?string $description = null) {}

    public function describe(string $image, string $mimeType = 'image/jpeg', string $caption = ''): ?string
    {
        return $this->description;
    }
}

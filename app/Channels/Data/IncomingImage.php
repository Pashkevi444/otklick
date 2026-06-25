<?php

declare(strict_types=1);

namespace App\Channels\Data;

use App\Channels\Contracts\ReceivesImage;

/**
 * Скачанное из апдейта канала изображение клиента: сырые байты, MIME-тип и
 * подпись (текст рядом с фото, если был). Нормализованный data-контракт порта
 * {@see ReceivesImage} — не зависит от конкретного канала.
 */
final readonly class IncomingImage
{
    public function __construct(
        public string $bytes,
        public string $mimeType = 'image/jpeg',
        public string $caption = '',
    ) {}
}

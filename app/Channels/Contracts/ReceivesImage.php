<?php

declare(strict_types=1);

namespace App\Channels\Contracts;

use App\Channels\Data\IncomingImage;
use App\Models\Channel;
use App\Vision\Contracts\ImageToText;

/**
 * Канал, умеющий отдать изображение из входящего апдейта. Реализуют гейтвеи
 * мессенджеров (Telegram/VK/MAX/WhatsApp); веб-виджет — нет (фото туда приходит
 * напрямую файлом). Скачанная картинка распознаётся через {@see ImageToText}.
 */
interface ReceivesImage
{
    /**
     * Если в апдейте есть фото — скачивает байты и возвращает их вместе с MIME и
     * подписью; иначе (или при ошибке скачивания) — null. $update — сырой апдейт канала.
     *
     * @param  array<string, mixed>  $update
     */
    public function downloadImage(Channel $channel, array $update): ?IncomingImage;
}

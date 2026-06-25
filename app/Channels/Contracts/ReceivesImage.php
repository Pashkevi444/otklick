<?php

declare(strict_types=1);

namespace App\Channels\Contracts;

use App\Channels\Data\IncomingImage;
use App\Models\Channel;
use App\Vision\Contracts\ImageToText;

/**
 * Канал, умеющий отдать изображения из входящего апдейта. Реализуют гейтвеи
 * мессенджеров (Telegram/VK/MAX/WhatsApp); веб-виджет — нет (фото туда приходит
 * напрямую файлом). Скачанные картинки распознаются через {@see ImageToText}.
 */
interface ReceivesImage
{
    /**
     * Скачивает ВСЕ фото из апдейта (VK/MAX кладут несколько в одно сообщение —
     * тогда вернётся список; Telegram/WhatsApp — 0..1). Пусто — нет фото / ошибка
     * скачивания. $update — сырой апдейт канала.
     *
     * @param  array<string, mixed>  $update
     * @return list<IncomingImage>
     */
    public function downloadImages(Channel $channel, array $update): array;
}

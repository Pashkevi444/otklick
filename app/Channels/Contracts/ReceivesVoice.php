<?php

declare(strict_types=1);

namespace App\Channels\Contracts;

use App\Models\Channel;
use App\Speech\Contracts\SpeechToText;

/**
 * Канал, умеющий отдать аудио голосового сообщения из входящего апдейта.
 * Реализуют гейтвеи мессенджеров (Telegram/VK/MAX); веб-виджет — нет.
 * Скачанное аудио (OGG/Opus) распознаётся через {@see SpeechToText}.
 */
interface ReceivesVoice
{
    /**
     * Если в апдейте есть голосовое — скачивает аудио (сырые байты) и возвращает
     * его; иначе (или при ошибке скачивания) — null. $update — сырой апдейт канала.
     *
     * @param  array<string, mixed>  $update
     */
    public function downloadVoice(Channel $channel, array $update): ?string;
}

<?php

declare(strict_types=1);

namespace App\Speech\Contracts;

/**
 * Порт распознавания речи (голосовое сообщение → текст). Бизнес-логика зависит от
 * этого контракта, а не от провайдера (Yandex SpeechKit / локальный fake).
 */
interface SpeechToText
{
    /**
     * Распознаёт аудио (сырые байты) в текст. Возвращает null, если распознать
     * не удалось (пусто/ошибка) — вызывающий решает, что делать (попросить
     * повторить и т.п.). $format — контейнер аудио (oggopus для Telegram/VK-войсов).
     */
    public function transcribe(string $audio, string $format = 'oggopus', string $lang = 'ru-RU'): ?string;
}

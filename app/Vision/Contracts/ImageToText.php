<?php

declare(strict_types=1);

namespace App\Vision\Contracts;

/**
 * Порт распознавания изображений (фото → текстовое описание). Бизнес-логика
 * зависит от этого контракта, а не от провайдера (vision-модель Yandex Cloud /
 * локальный fake). Описание подставляется как «сообщение клиента» — бот отвечает
 * по базе знаний, как на обычный текст (см. {@see SpeechToText} для голоса).
 */
interface ImageToText
{
    /**
     * Описывает изображение (сырые байты) на русском. Возвращает null, если
     * описать не удалось (пусто/ошибка) — вызывающий решает, что делать (передать
     * администратору и т.п.). $mimeType — тип картинки (image/jpeg, image/png…),
     * $caption — подпись клиента к фото (контекст для модели, может быть пустой).
     */
    public function describe(string $image, string $mimeType = 'image/jpeg', string $caption = ''): ?string;
}

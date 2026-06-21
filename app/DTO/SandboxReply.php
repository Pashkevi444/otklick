<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Ответ бота в тестовом прогоне (песочница) для отрисовки в кабинете: текст,
 * кнопки-подсказки и флаги исхода. `note` — пояснение для тестирующего (напр.
 * «запись тестовая, в YClients не отправлена»).
 */
final readonly class SandboxReply
{
    /**
     * @param  list<string>  $buttons  подписи кнопок-подсказок (плоским списком)
     * @param  list<string>  $images  URL фото примеров работ (рендерятся картинками)
     */
    public function __construct(
        public string $text,
        public array $buttons = [],
        public bool $escalate = false,
        public bool $booked = false,
        public bool $cancelled = false,
        public ?string $note = null,
        public array $images = [],
    ) {}

    /**
     * @return array{text: string, buttons: list<string>, escalate: bool, booked: bool, cancelled: bool, note: ?string, images: list<string>}
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'buttons' => $this->buttons,
            'escalate' => $this->escalate,
            'booked' => $this->booked,
            'cancelled' => $this->cancelled,
            'note' => $this->note,
            'images' => $this->images,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Shared\DTO;

/**
 * Ответ бота: текст для отправки, нужно ли эскалировать на администратора,
 * оформлена ли запись, отменил ли клиент запись (запись/отмена закрывают диалог)
 * и нужно ли запустить пошаговую запись в CRM (startBooking — обрабатывает
 * BotResponder через BookingFlow).
 *
 * knowledgeGap — эскалация именно из-за того, что бот НЕ нашёл ответа в базе
 * знаний (а не из booking-флоу): такой вопрос фиксируется в «пробелах бота»,
 * чтобы бизнес дополнил базу знаний.
 */
final readonly class BotReply
{
    /**
     * Префикс ответа, когда диалог уже эскалирован на оператора, но бот всё равно
     * отвечает на вопросы клиента (пока оператор не подключился). Ставится перед
     * текстом ответа единообразно во всех каналах (IncomingMessageService /
     * WebWidgetService / TelegramRelayService).
     */
    public const string ESCALATED_NOTE = 'Диалог уже передан администратору, но я могу ответить на ваши вопросы.';

    public function __construct(
        public string $text,
        public bool $escalate,
        public bool $booked = false,
        public bool $cancelled = false,
        public bool $startBooking = false,
        public bool $knowledgeGap = false,
        // Клавиатура-подсказка под сообщением (кликабельные дата/время/услуга/мастер
        // в мастере записи). null — обычный текст без кнопок.
        public ?ReplyKeyboard $keyboard = null,
        // URL картинок (примеры работ из базы знаний), которые канал отправит как
        // НАСТОЯЩИЕ фото, а не ссылкой в тексте.
        /** @var list<string> */
        public array $images = [],
    ) {}

    /** Тот же ответ с добавленной клавиатурой (напр. кнопка возврата в меню). */
    public function withKeyboard(ReplyKeyboard $keyboard): self
    {
        return new self($this->text, $this->escalate, $this->booked, $this->cancelled, $this->startBooking, $this->knowledgeGap, $keyboard, $this->images);
    }
}

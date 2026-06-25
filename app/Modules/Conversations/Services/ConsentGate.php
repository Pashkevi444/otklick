<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Services;

use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Repositories\Contracts\ConversationRepositoryInterface;
use App\Shared\DTO\BotReply;
use App\Shared\DTO\ReplyKeyboard;
use App\Shared\Models\Tenant;

/**
 * Согласие на обработку персональных данных (152-ФЗ) — самый первый рубеж бота.
 * Пока клиент не подтвердил согласие, бот не ведёт диалог: первым сообщением в
 * любом мессенджере показывает форму согласия с кнопками «Да»/«Нет». «Да» —
 * фиксируем согласие и пропускаем дальше; «Нет» — вежливо прощаемся.
 *
 * Веб-виджет даёт согласие галочкой при первом открытии и помечает его через
 * {@see WebWidgetService} ДО ответа — поэтому до этого рубежа в виджете дело
 * обычно не доходит (а если дойдёт — сработает как фолбэк).
 */
final readonly class ConsentGate
{
    private const array YES = ['да', 'ага', 'согласен', 'согласна', 'соглашаюсь', 'подтверждаю', 'принимаю', 'ок', 'окей', 'yes', 'ok'];

    private const array NO = ['нет', 'не', 'не согласен', 'не согласна', 'отказываюсь', 'no'];

    public function __construct(
        private ConversationRepositoryInterface $conversations,
    ) {}

    public function handle(Tenant $tenant, Conversation $conversation, string $text): ?BotReply
    {
        if ($conversation->consent_agreed) {
            return null;
        }

        $answer = mb_strtolower(trim($text));

        if (in_array($answer, self::YES, true)) {
            $this->conversations->markConsentGiven($conversation);

            return null; // согласие получено — пропускаем дальше (контактная форма и т.д.)
        }

        if (in_array($answer, self::NO, true)) {
            return new BotReply(
                'Понимаю. Без согласия на обработку персональных данных я не могу продолжить. '
                .'Будем рады помочь, если передумаете — обращайтесь! 👋',
                escalate: false,
            );
        }

        // Первое сообщение (или непонятный ответ) — показываем форму согласия.
        $consentUrl = route('site.consent', absolute: true);
        $privacyUrl = route('site.privacy', absolute: true);

        return new BotReply(
            "Здравствуйте! 👋 Я виртуальный администратор.\n\n"
            .'Чтобы продолжить, подтвердите, пожалуйста, согласие на обработку персональных данных '
            ."в соответствии с Политикой конфиденциальности.\n\n"
            ."• Согласие на обработку персональных данных: {$consentUrl}\n"
            ."• Политика конфиденциальности: {$privacyUrl}",
            escalate: false,
            keyboard: ReplyKeyboard::grid(['Да', 'Нет'], 2),
        );
    }
}

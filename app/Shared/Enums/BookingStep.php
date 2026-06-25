<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * Шаг пошаговой записи клиента в CRM (BookingFlow). Хранится в
 * conversations.booking_state.step.
 */
enum BookingStep: string
{
    /** Выбор услуги из каталога CRM. */
    case Service = 'service';

    /** Выбор мастера (или «любой свободный»). */
    case Staff = 'staff';

    /** Ввод желаемого дня. */
    case Date = 'date';

    /** Выбор конкретного свободного времени. */
    case Slot = 'slot';

    /** Сбор телефона, если его ещё нет. */
    case Contact = 'contact';

    /** Подтверждение телефона у вернувшегося клиента (вдруг сменился). */
    case ConfirmContact = 'confirm_contact';
}

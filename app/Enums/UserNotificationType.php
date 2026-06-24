<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasLabel;

/**
 * Тип in-app уведомления (колокольчик + бейджи плашек). Каждый тип несёт:
 * - требуемое право (`MemberPermission`) — уведомление получают ТОЛЬКО сотрудники
 *   с доступом к соответствующему разделу (матрица доступа);
 * - раздел-плашку дашборда (`section`) — для группировки бейджа-кружочка.
 */
enum UserNotificationType: string implements HasLabel
{
    case NewLead = 'new_lead';
    case Escalation = 'escalation';
    case Booked = 'booked';
    case KnowledgeGap = 'knowledge_gap';
    case NewClient = 'new_client';

    public function label(): string
    {
        return match ($this) {
            self::NewLead => 'Новый лид',
            self::Escalation => 'Нужен администратор',
            self::Booked => 'Запись оформлена',
            self::KnowledgeGap => 'Вопрос без ответа',
            self::NewClient => 'Новый клиент',
        };
    }

    /** Право, которое должно быть у сотрудника, чтобы видеть это уведомление. */
    public function requiredPermission(): MemberPermission
    {
        return match ($this) {
            self::NewLead, self::Escalation, self::Booked => MemberPermission::Conversations,
            self::KnowledgeGap => MemberPermission::Knowledge,
            self::NewClient => MemberPermission::Clients,
        };
    }

    /** Ключ плашки дашборда, чей бейдж-кружочек копит этот тип. */
    public function section(): string
    {
        return match ($this) {
            self::NewLead, self::Escalation, self::Booked => 'conversations',
            self::KnowledgeGap => 'knowledge',
            self::NewClient => 'clients',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::NewLead => '🎯',
            self::Escalation => '🆘',
            self::Booked => '✅',
            self::KnowledgeGap => '❓',
            self::NewClient => '👤',
        };
    }
}

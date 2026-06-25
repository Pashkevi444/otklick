<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Shared\Enums\ChannelType;
use App\Shared\Enums\ConversationStatus;
use App\Shared\Enums\MessageDirection;
use App\Shared\Enums\MessageStatus;
use PHPUnit\Framework\TestCase;

final class MessagingEnumsTest extends TestCase
{
    public function test_every_case_has_a_non_empty_label(): void
    {
        foreach (
            [
                ...ChannelType::cases(),
                ...MessageDirection::cases(),
                ...MessageStatus::cases(),
                ...ConversationStatus::cases(),
            ] as $case
        ) {
            $this->assertNotSame('', $case->label());
        }
    }

    public function test_channel_type_is_backed_by_string(): void
    {
        $this->assertSame('telegram', ChannelType::Telegram->value);
        $this->assertSame(ChannelType::WhatsApp, ChannelType::from('whatsapp'));
    }

    public function test_message_direction_values(): void
    {
        $this->assertSame('inbound', MessageDirection::Inbound->value);
        $this->assertSame('outbound', MessageDirection::Outbound->value);
    }

    public function test_message_status_values(): void
    {
        $this->assertSame('received', MessageStatus::Received->value);
        $this->assertSame('sent', MessageStatus::Sent->value);
        $this->assertSame('failed', MessageStatus::Failed->value);
    }

    public function test_default_conversation_status_is_open(): void
    {
        $this->assertSame(ConversationStatus::Open, ConversationStatus::default());
    }
}

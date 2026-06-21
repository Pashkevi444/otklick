<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Tenant;
use App\Support\BotMenu;
use PHPUnit\Framework\TestCase;

final class BotMenuTest extends TestCase
{
    private function tenant(array $menu): Tenant
    {
        return new Tenant(['settings' => ['bot_menu' => $menu]]);
    }

    public function test_empty_menu_is_empty_without_booking(): void
    {
        $this->assertSame([], BotMenu::effective($this->tenant([]), false));
    }

    public function test_business_buttons_kept_in_order(): void
    {
        $this->assertSame(['Цены', 'Адрес'], BotMenu::effective($this->tenant(['Цены', 'Адрес']), false));
    }

    public function test_booking_button_auto_prepended_when_available(): void
    {
        $this->assertSame(['Записаться', 'Цены'], BotMenu::effective($this->tenant(['Цены']), true));
    }

    public function test_booking_button_added_even_if_business_has_duplicate(): void
    {
        // Дубль остаётся — это на усмотрение бизнеса (его и убирать).
        $this->assertSame(['Записаться', 'Записаться'], BotMenu::effective($this->tenant(['Записаться']), true));
    }

    public function test_blank_buttons_are_dropped(): void
    {
        $this->assertSame(['Цены'], BotMenu::effective($this->tenant(['Цены', '  ', '']), false));
    }

    public function test_recognizes_return_phrases(): void
    {
        $this->assertTrue(BotMenu::isReturn('Главное меню'));
        $this->assertTrue(BotMenu::isReturn('  вернуться в главное меню '));
        $this->assertTrue(BotMenu::isReturn(BotMenu::RETURN_BUTTON));
        $this->assertFalse(BotMenu::isReturn('записаться'));
    }
}

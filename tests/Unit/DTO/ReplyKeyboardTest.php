<?php

declare(strict_types=1);

namespace Tests\Unit\DTO;

use App\Shared\DTO\ReplyKeyboard;
use PHPUnit\Framework\TestCase;

final class ReplyKeyboardTest extends TestCase
{
    public function test_grid_chunks_labels_into_rows(): void
    {
        $kb = ReplyKeyboard::grid(['a', 'b', 'c', 'd', 'e'], 2);

        $this->assertSame([['a', 'b'], ['c', 'd'], ['e']], $kb->rows);
        $this->assertSame(['a', 'b', 'c', 'd', 'e'], $kb->labels());
        $this->assertFalse($kb->isEmpty());
    }

    public function test_empty_labels_make_empty_keyboard(): void
    {
        $kb = ReplyKeyboard::grid([], 3);

        $this->assertTrue($kb->isEmpty());
        $this->assertSame([], $kb->labels());
    }

    public function test_per_row_is_at_least_one(): void
    {
        $kb = ReplyKeyboard::grid(['a', 'b'], 0);

        $this->assertSame([['a'], ['b']], $kb->rows);
    }
}

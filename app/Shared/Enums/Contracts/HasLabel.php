<?php

declare(strict_types=1);

namespace App\Shared\Enums\Contracts;

/**
 * Доменный enum с человекочитаемой подписью для UI. PHP-enum нельзя наследовать
 * от абстрактного класса, поэтому общий метод выносится в интерфейс.
 */
interface HasLabel
{
    public function label(): string;
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Контент публичного сайта (singleton-строка). Общий для площадки, не скоупится
 * по тенанту.
 *
 * @property string $hero_title
 * @property string $hero_subtitle
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $telegram
 * @property string|null $legal_name
 * @property string|null $inn
 * @property string|null $ogrnip
 * @property string $access_note
 */
class SiteSetting extends Model
{
    protected $fillable = [
        'hero_title',
        'hero_subtitle',
        'phone',
        'email',
        'telegram',
        'legal_name',
        'inn',
        'ogrnip',
        'access_note',
    ];
}

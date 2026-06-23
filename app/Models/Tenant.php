<?php

declare(strict_types=1);

namespace App\Models;

use App\DTO\BusinessProfile;
use App\DTO\PlanFeatures;
use App\Enums\TenantPlan;
use App\Enums\UserRole;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Клиент-бизнес (тенант). Корневая сущность изоляции данных.
 *
 * Сама таблица tenants не скоупится по тенанту — она и есть реестр тенантов.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property TenantPlan $plan
 * @property string|null $business_type
 * @property Carbon|null $access_expires_at
 * @property bool $is_blocked
 * @property array<string, mixed> $settings
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'plan',
        'business_type',
        'access_expires_at',
        'is_blocked',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'plan' => TenantPlan::class,
            'access_expires_at' => 'datetime',
            'is_blocked' => 'boolean',
            'settings' => 'array',
        ];
    }

    /**
     * Эффективные возможности бизнеса: базовые из тарифа + индивидуальные
     * оверрайды супер-админа (settings['overrides']). Источник истины для
     * гейтинга и лимитов на уровне конкретного бизнеса.
     */
    public function features(): PlanFeatures
    {
        $overrides = $this->settings['overrides'] ?? [];

        return ($this->plan ?? TenantPlan::default())->features()->merge(is_array($overrides) ? $overrides : []);
    }

    /**
     * Текст, который бот отправляет забаненному клиенту (без LLM). Добавляем
     * контакты бизнеса (телефон/почта из профиля, если заданы), чтобы клиент мог
     * обратиться за разблокировкой.
     */
    public function banNotice(): string
    {
        $profile = BusinessProfile::fromArray(is_array($this->settings['profile'] ?? null) ? $this->settings['profile'] : []);

        $notice = 'Вы заблокированы и не можете писать. Чтобы снять блокировку, обратитесь к администрации.';

        $email = $profile->email !== null && trim($profile->email) !== ''
            ? trim($profile->email)
            : $this->ownerEmail();

        $contacts = [];
        if ($profile->phone !== null && trim($profile->phone) !== '') {
            $contacts[] = 'тел. '.trim($profile->phone);
        }
        if ($email !== null && trim($email) !== '') {
            $contacts[] = trim($email);
        }

        return $contacts === [] ? $notice : $notice.' Контакты: '.implode(', ', $contacts).'.';
    }

    /** Почта владельца — дефолт для контактов бизнеса, если своя не задана. */
    public function ownerEmail(): ?string
    {
        $email = $this->users()->where('role', UserRole::Owner->value)->value('email');

        return is_string($email) ? $email : null;
    }

    /**
     * Кабинет доступен, пока бизнес не заблокирован и срок доступа не истёк.
     */
    public function hasActiveAccess(): bool
    {
        if ($this->is_blocked) {
            return false;
        }

        return $this->access_expires_at === null || $this->access_expires_at->isFuture();
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Тип бизнеса из справочника (null = не задан).
     *
     * @return BelongsTo<BusinessType, $this>
     */
    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class, 'business_type', 'key');
    }
}

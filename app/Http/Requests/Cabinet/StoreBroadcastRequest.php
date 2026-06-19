<?php

declare(strict_types=1);

namespace App\Http\Requests\Cabinet;

use App\DTO\BroadcastData;
use App\Enums\BroadcastRecurrence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Валидация создания рассылки. channels — подмножество доступных целей; режим
 * «сейчас» или «по расписанию» определяет, нужна ли дата старта.
 */
final class StoreBroadcastRequest extends FormRequest
{
    /** Доступные цели рассылки (мессенджеры с проактивной отправкой + почта). */
    public const CHANNELS = ['telegram', 'vk', 'max', 'email'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:4000'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['string', Rule::in(self::CHANNELS)],
            'mode' => ['required', Rule::in(['now', 'schedule'])],
            'scheduled_at' => ['required_if:mode,schedule', 'nullable', 'date', 'after:now'],
            'recurrence' => ['nullable', Rule::in(array_map(fn (BroadcastRecurrence $r): string => $r->value, BroadcastRecurrence::cases()))],
            'audience' => ['required', Rule::in(['all', 'selected'])],
            'client_ids' => ['required_if:audience,selected', 'array'],
            'client_ids.*' => ['string', 'uuid'],
        ];
    }

    public function toData(): BroadcastData
    {
        /** @var list<string> $channels */
        $channels = array_values(array_unique(array_map('strval', (array) $this->input('channels', []))));

        $isSchedule = $this->input('mode') === 'schedule';
        $scheduledAt = $isSchedule && $this->filled('scheduled_at')
            ? Carbon::parse((string) $this->input('scheduled_at'))
            : null;

        $clientIds = $this->input('audience') === 'selected'
            ? array_values(array_unique(array_map('strval', (array) $this->input('client_ids', []))))
            : null;

        return new BroadcastData(
            title: (string) $this->input('title'),
            body: (string) $this->input('body'),
            channels: $channels,
            recurrence: BroadcastRecurrence::from((string) ($this->input('recurrence') ?? 'none')),
            scheduledAt: $scheduledAt,
            clientIds: $clientIds,
        );
    }

    public function isScheduled(): bool
    {
        return $this->input('mode') === 'schedule';
    }
}

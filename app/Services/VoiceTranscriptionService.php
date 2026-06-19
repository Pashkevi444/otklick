<?php

declare(strict_types=1);

namespace App\Services;

use App\Channels\ChannelGatewayResolver;
use App\Channels\Contracts\ReceivesVoice;
use App\Models\Channel;
use App\Speech\Contracts\SpeechToText;
use Illuminate\Support\Facades\Log;

/**
 * Распознавание голосового сообщения в текст: резолвит гейтвей канала, просит его
 * скачать аудио из апдейта и прогоняет через STT-порт. Провайдер-агностично —
 * джобы каналов зовут это при пустом тексте и подставляют расшифровку как ввод.
 */
final readonly class VoiceTranscriptionService
{
    public function __construct(
        private ChannelGatewayResolver $gateways,
        private SpeechToText $speech,
    ) {}

    /**
     * @param  array<string, mixed>  $update  сырой апдейт канала
     * @return string|null распознанный текст или null (не голос / не распозналось)
     */
    public function transcribe(Channel $channel, array $update): ?string
    {
        if (! $this->gateways->has($channel->type)) {
            return null;
        }

        $gateway = $this->gateways->for($channel->type);

        if (! $gateway instanceof ReceivesVoice) {
            return null;
        }

        $audio = $gateway->downloadVoice($channel, $update);

        if ($audio === null) {
            return null;
        }

        $text = $this->speech->transcribe($audio);

        Log::info('speech.voice', [
            'channel' => $channel->type->value,
            'tenant_id' => $channel->tenant_id,
            'recognized' => $text !== null && trim($text) !== '',
        ]);

        return $text !== null && trim($text) !== '' ? trim($text) : null;
    }
}

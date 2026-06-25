<?php

declare(strict_types=1);

namespace App\Services;

use App\Channels\ChannelGatewayResolver;
use App\Channels\Contracts\ReceivesImage;
use App\Models\Channel;
use App\Vision\Contracts\ImageToText;
use Illuminate\Support\Facades\Log;

/**
 * Распознавание изображения в текст: резолвит гейтвей канала, просит его скачать
 * фото из апдейта и прогоняет через vision-порт. Провайдер-агностично — джобы
 * каналов зовут это при пустом тексте (после голоса) и подставляют описание как
 * ввод клиента. Зеркало {@see VoiceTranscriptionService} для картинок.
 */
final readonly class ImageRecognitionService
{
    public function __construct(
        private ChannelGatewayResolver $gateways,
        private ImageToText $vision,
    ) {}

    /**
     * @param  array<string, mixed>  $update  сырой апдейт канала
     * @return string|null готовый текст-ввод (подпись + описание фото) или null
     *                     (не фото / не распозналось)
     */
    public function recognize(Channel $channel, array $update): ?string
    {
        if (! $this->gateways->has($channel->type)) {
            return null;
        }

        $gateway = $this->gateways->for($channel->type);

        if (! $gateway instanceof ReceivesImage) {
            return null;
        }

        $image = $gateway->downloadImage($channel, $update);

        if ($image === null) {
            return null;
        }

        $description = $this->vision->describe($image->bytes, $image->mimeType, $image->caption);

        Log::info('vision.image', [
            'channel' => $channel->type->value,
            'tenant_id' => $channel->tenant_id,
            'recognized' => $description !== null && trim($description) !== '',
        ]);

        if ($description === null || trim($description) === '') {
            return null;
        }

        return self::compose($image->caption, trim($description));
    }

    /**
     * Складывает ввод клиента из подписи к фото и распознанного описания: подпись
     * (слова клиента) + маркер с описанием, чтобы бот «понимал», что прислали фото.
     */
    public static function compose(string $caption, string $description): string
    {
        $parts = [];

        if (trim($caption) !== '') {
            $parts[] = trim($caption);
        }

        $parts[] = "[Клиент прислал фото. На фото: {$description}]";

        return implode("\n", $parts);
    }
}

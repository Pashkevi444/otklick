<?php

declare(strict_types=1);

namespace App\Services;

use App\Channels\ChannelGatewayResolver;
use App\Channels\Contracts\ReceivesImage;
use App\Channels\Data\IncomingImage;
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

        return $this->describeAll($channel, $gateway->downloadImages($channel, $update));
    }

    /**
     * Описывает все скачанные картинки и складывает их в ОДИН ввод клиента (подпись
     * + объединённое описание). Несколько фото из одного сообщения (VK/MAX) или
     * собранный альбом (Telegram) → один ответ бота. null — описать не удалось.
     *
     * @param  list<IncomingImage>  $images
     */
    public function describeAll(Channel $channel, array $images): ?string
    {
        if ($images === []) {
            return null;
        }

        $caption = '';
        $descriptions = [];

        foreach ($images as $image) {
            if ($caption === '' && trim($image->caption) !== '') {
                $caption = trim($image->caption);
            }

            $description = $this->vision->describe($image->bytes, $image->mimeType, $image->caption);
            if ($description !== null && trim($description) !== '') {
                $descriptions[] = trim($description);
            }
        }

        Log::info('vision.image', [
            'channel' => $channel->type->value,
            'tenant_id' => $channel->tenant_id,
            'photos' => count($images),
            'recognized' => count($descriptions),
        ]);

        if ($descriptions === []) {
            return null;
        }

        return self::compose($caption, $descriptions);
    }

    /**
     * Складывает ввод клиента из подписи к фото и распознанных описаний: подпись
     * (слова клиента) + маркер с описанием(ями), чтобы бот «понимал», что прислали
     * фото. Несколько описаний объединяются через «; ».
     *
     * @param  list<string>  $descriptions
     */
    public static function compose(string $caption, array $descriptions): string
    {
        $parts = [];

        if (trim($caption) !== '') {
            $parts[] = trim($caption);
        }

        $parts[] = '[Клиент прислал фото. На фото: '.implode('; ', $descriptions).']';

        return implode("\n", $parts);
    }
}

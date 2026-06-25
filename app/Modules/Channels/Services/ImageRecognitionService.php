<?php

declare(strict_types=1);

namespace App\Modules\Channels\Services;

use App\Modules\Channels\ChannelGatewayResolver;
use App\Modules\Channels\Contracts\ReceivesImage;
use App\Modules\Channels\Data\IncomingImage;
use App\Modules\Channels\Models\Channel;
use App\Shared\Vision\Contracts\ImageToText;
use Illuminate\Support\Facades\Log;

/**
 * Распознавание изображения в текст: резолвит гейтвей канала, просит его скачать
 * фото из апдейта и прогоняет через vision-порт. Провайдер-агностично — джобы
 * каналов зовут {@see augment} после голоса: описание фото приклеивается к тексту
 * /подписи клиента (текст обрабатывается ВСЕГДА, даже вместе с фото). Нет фото в
 * апдейте — шаг пропускается, vision не дёргаем. Зеркало {@see VoiceTranscriptionService}.
 */
final readonly class ImageRecognitionService
{
    public function __construct(
        private ChannelGatewayResolver $gateways,
        private ImageToText $vision,
    ) {}

    /**
     * Фото-ввод без уже распознанного текста (фото-only). Тонкая обёртка над
     * {@see augment} с пустым текстом — null, если фото нет или vision не распознал.
     *
     * @param  array<string, mixed>  $update  сырой апдейт канала
     */
    public function recognize(Channel $channel, array $update): ?string
    {
        $text = $this->augment($channel, $update, '');

        return $text === '' ? null : $text;
    }

    /**
     * Приклеивает описание фото из апдейта к уже распознанному тексту/подписи
     * клиента. Нет фото в апдейте — возвращает $text как есть (шаг с картинками
     * пропускается, vision не дёргаем). Есть фото — текст клиента обрабатывается
     * ВМЕСТЕ с описанием: подпись (слова клиента) + маркер «[На фото: …]».
     *
     * @param  array<string, mixed>  $update  сырой апдейт канала
     */
    public function augment(Channel $channel, array $update, string $text): string
    {
        if (! $this->gateways->has($channel->type)) {
            return $text;
        }

        $gateway = $this->gateways->for($channel->type);

        if (! $gateway instanceof ReceivesImage) {
            return $text;
        }

        $images = $gateway->downloadImages($channel, $update);

        if ($images === []) {
            return $text;
        }

        // Подпись клиента: уже распознанный текст апдейта (VK/MAX/WhatsApp кладут
        // подпись в text), иначе — подпись из самой картинки (Telegram отдаёт её
        // отдельным полем caption). Vision не теряет слова клиента → описать не
        // удалось: оставляем исходный текст, а не роняем сообщение в null.
        $caption = $text !== '' ? $text : $this->captionOf($images);

        return $this->describeImages($channel, $images, $caption) ?? $text;
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

        return $this->describeImages($channel, $images, $this->captionOf($images));
    }

    /**
     * Прогоняет картинки через vision и складывает ввод клиента (подпись + описания).
     * null — ни одну распознать не удалось (vision выключен/ошибка).
     *
     * @param  list<IncomingImage>  $images
     */
    private function describeImages(Channel $channel, array $images, string $caption): ?string
    {
        $descriptions = [];

        foreach ($images as $image) {
            $hint = $caption !== '' ? $caption : $image->caption;

            $description = $this->vision->describe($image->bytes, $image->mimeType, $hint);
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
     * Первая непустая подпись среди картинок (Telegram/WhatsApp кладут её к фото).
     *
     * @param  list<IncomingImage>  $images
     */
    private function captionOf(array $images): string
    {
        foreach ($images as $image) {
            if (trim($image->caption) !== '') {
                return trim($image->caption);
            }
        }

        return '';
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

<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Channels\ChannelGatewayResolver;
use App\Channels\Contracts\ChannelGateway;
use App\Channels\Contracts\ReceivesImage;
use App\Channels\Data\IncomingImage;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Services\ImageRecognitionService;
use App\Vision\Contracts\ImageToText;
use App\Vision\FakeImageToText;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

final class ImageRecognitionServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function channel(): Channel
    {
        $channel = new Channel;
        $channel->type = ChannelType::Telegram;
        $channel->tenant_id = 'tenant-1';

        return $channel;
    }

    public function test_composes_caption_and_description_as_client_input(): void
    {
        $gateway = $this->imageGateway();
        $gateway->shouldReceive('downloadImages')->once()
            ->andReturn([new IncomingImage('JPEG', 'image/jpeg', 'хочу такую стрижку')]);

        $service = new ImageRecognitionService(
            new ChannelGatewayResolver([$gateway]),
            new FakeImageToText('Мужская стрижка андеркат.'),
        );

        $text = $service->recognize($this->channel(), ['message' => []]);

        $this->assertSame("хочу такую стрижку\n[Клиент прислал фото. На фото: Мужская стрижка андеркат.]", $text);
    }

    public function test_combines_several_photos_into_one_input(): void
    {
        // VK/MAX кладут несколько фото в одно сообщение → одно описание, один ответ.
        $gateway = $this->imageGateway();
        $gateway->shouldReceive('downloadImages')->once()->andReturn([
            new IncomingImage('JPEG1', 'image/jpeg', 'примеры'),
            new IncomingImage('JPEG2'),
        ]);

        $service = new ImageRecognitionService(new ChannelGatewayResolver([$gateway]), new FakeImageToText('стрижка'));

        $text = $service->recognize($this->channel(), []);

        $this->assertSame("примеры\n[Клиент прислал фото. На фото: стрижка; стрижка]", $text);
    }

    public function test_returns_null_when_gateway_has_no_image(): void
    {
        $gateway = $this->imageGateway();
        $gateway->shouldReceive('downloadImages')->once()->andReturn([]);

        $service = new ImageRecognitionService(new ChannelGatewayResolver([$gateway]), new FakeImageToText('что-то'));

        $this->assertNull($service->recognize($this->channel(), []));
    }

    public function test_returns_null_when_vision_cannot_describe(): void
    {
        $gateway = $this->imageGateway();
        $gateway->shouldReceive('downloadImages')->once()->andReturn([new IncomingImage('JPEG')]);

        // FakeImageToText без описания возвращает null — фолбэк на администратора.
        $service = new ImageRecognitionService(new ChannelGatewayResolver([$gateway]), new FakeImageToText);

        $this->assertNull($service->recognize($this->channel(), []));
    }

    public function test_augment_processes_caption_text_together_with_photo(): void
    {
        // Фото с подписью: каналы (VK/MAX/WhatsApp) кладут подпись в text — она НЕ
        // должна теряться, текст клиента обрабатывается ВМЕСТЕ с описанием фото.
        $gateway = $this->imageGateway();
        $gateway->shouldReceive('downloadImages')->once()
            ->andReturn([new IncomingImage('JPEG', 'image/jpeg', 'сколько стоит?')]);

        $service = new ImageRecognitionService(new ChannelGatewayResolver([$gateway]), new FakeImageToText('Татуировка дракона.'));

        $text = $service->augment($this->channel(), [], 'сколько стоит?');

        // Подпись из text — один раз (без дублирования с image->caption) + описание.
        $this->assertSame("сколько стоит?\n[Клиент прислал фото. На фото: Татуировка дракона.]", $text);
    }

    public function test_augment_skips_image_step_and_keeps_text_when_no_photo(): void
    {
        // Нет фото в апдейте — шаг с картинками пропускается, vision не дёргаем,
        // текст клиента возвращается как есть.
        $gateway = $this->imageGateway();
        $gateway->shouldReceive('downloadImages')->once()->andReturn([]);

        $vision = Mockery::mock(ImageToText::class);
        $vision->shouldNotReceive('describe');

        $service = new ImageRecognitionService(new ChannelGatewayResolver([$gateway]), $vision);

        $this->assertSame('просто текст', $service->augment($this->channel(), [], 'просто текст'));
    }

    public function test_augment_keeps_text_when_vision_fails_on_photo(): void
    {
        // Фото есть, но vision не распознал — не роняем сообщение, оставляем подпись.
        $gateway = $this->imageGateway();
        $gateway->shouldReceive('downloadImages')->once()->andReturn([new IncomingImage('JPEG')]);

        $service = new ImageRecognitionService(new ChannelGatewayResolver([$gateway]), new FakeImageToText);

        $this->assertSame('есть фейд?', $service->augment($this->channel(), [], 'есть фейд?'));
    }

    public function test_returns_null_when_gateway_does_not_receive_images(): void
    {
        // Гейтвей без ReceivesImage — распознавание невозможно.
        $gateway = Mockery::mock(ChannelGateway::class);
        $gateway->shouldReceive('provider')->andReturn(ChannelType::Telegram);

        $vision = Mockery::mock(ImageToText::class);
        $vision->shouldNotReceive('describe');

        $service = new ImageRecognitionService(new ChannelGatewayResolver([$gateway]), $vision);

        $this->assertNull($service->recognize($this->channel(), []));
    }

    /**
     * Мок шлюза, реализующего и ChannelGateway (для реестра), и ReceivesImage.
     */
    private function imageGateway(): Mockery\MockInterface
    {
        $gateway = Mockery::mock(ChannelGateway::class, ReceivesImage::class);
        $gateway->shouldReceive('provider')->andReturn(ChannelType::Telegram);

        return $gateway;
    }
}

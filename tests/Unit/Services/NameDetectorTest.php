<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Llm\Contracts\LlmClient;
use App\Services\NameDetector;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

final class NameDetectorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_returns_name_when_bot_asked_and_model_extracts_it(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()->andReturn('Павел');

        $name = (new NameDetector($llm))->fromReply('Подскажите, как вас зовут?', 'Павел');

        $this->assertSame('Павел', $name);
    }

    public function test_normalizes_lowercase_and_surname(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()->andReturn('павел иванов');

        $name = (new NameDetector($llm))->fromReply('Как к вам обращаться?', 'павел иванов');

        $this->assertSame('Павел Иванов', $name);
    }

    public function test_returns_null_when_model_says_none(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()->andReturn('NONE');

        $name = (new NameDetector($llm))->fromReply('Как вас зовут?', 'не хочу говорить');

        $this->assertNull($name);
    }

    public function test_does_not_call_model_when_bot_did_not_ask_for_name(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldNotReceive('generate');

        $name = (new NameDetector($llm))->fromReply('Чем могу помочь?', 'Павел');

        $this->assertNull($name);
    }

    public function test_extracts_name_from_explicit_introduction_without_llm(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldNotReceive('generate');

        $detector = new NameDetector($llm);

        $this->assertSame('Паша', $detector->fromText('привет запиши меня на завтра, меня зовут Паша и телефон 89990000000'));
        $this->assertSame('Павел', $detector->fromText('Моё имя Павел'));
    }

    public function test_from_text_returns_null_without_introduction(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldNotReceive('generate');

        $this->assertNull((new NameDetector($llm))->fromText('хочу записаться на завтра в 15:00'));
        $this->assertNull((new NameDetector($llm))->fromText('зовут как у вас барбера?'));
    }

    public function test_rejects_non_name_answer_from_model(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()->andReturn('Хочу записаться на завтра в 15:00');

        $name = (new NameDetector($llm))->fromReply('Ваше имя?', 'хочу записаться на завтра');

        $this->assertNull($name);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Llm\Contracts\LlmClient;
use App\Models\Conversation;
use App\Models\KnowledgeEntry;
use App\Models\Tenant;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\CrmKnowledgeRepositoryInterface;
use App\Repositories\Contracts\KnowledgeEntryRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\KnowledgeRetriever;
use App\Services\PromptBuilder;
use App\Services\ReplyComposer;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;

final class ReplyComposerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function composer(LlmClient $llm, ?ConversationRepositoryInterface $conversations = null): ReplyComposer
    {
        $knowledge = Mockery::mock(KnowledgeEntryRepositoryInterface::class);
        $knowledge->shouldReceive('publishedForCurrentTenant')->andReturn(new Collection);

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recentForChat')->andReturn(new Collection);

        $crmKnowledge = Mockery::mock(CrmKnowledgeRepositoryInterface::class);
        $crmKnowledge->shouldReceive('forCurrentTenant')->andReturn(new Collection);

        // По умолчанию ретривер не находит индекс → фолбэк на всю базу (текущее поведение).
        $retriever = Mockery::mock(KnowledgeRetriever::class);
        $retriever->shouldReceive('retrieve')->andReturn(null)->byDefault();

        return new ReplyComposer($llm, new PromptBuilder, $knowledge, $messages, $conversations ?? $this->conversations(), $crmKnowledge, $retriever);
    }

    /**
     * Репозиторий диалогов по умолчанию терпит любые вызовы счётчика уточнений.
     */
    private function conversations(): ConversationRepositoryInterface&MockInterface
    {
        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('bumpClarificationAttempts')->andReturn(1)->byDefault();
        $conversations->shouldReceive('resetClarificationAttempts')->byDefault();

        return $conversations;
    }

    public function test_returns_model_answer_when_available(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()->andReturn('Работаем с 9 до 21.');

        $reply = $this->composer($llm)->compose(new Tenant(['name' => 'Бизнес']), new Conversation);

        $this->assertSame('Работаем с 9 до 21.', $reply->text);
        $this->assertFalse($reply->escalate);
    }

    public function test_escalates_with_fallback_including_phone(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()->andReturn(PromptBuilder::ESCALATE);

        $tenant = new Tenant(['name' => 'Бизнес', 'settings' => ['profile' => ['phone' => '+7 900']]]);

        $reply = $this->composer($llm)->compose($tenant, new Conversation);

        $this->assertTrue($reply->escalate);
        $this->assertStringContainsString('администратору', $reply->text);
        $this->assertStringContainsString('+7 900', $reply->text);
    }

    public function test_clarifies_instead_of_escalating_below_limit(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()
            ->andReturn(PromptBuilder::CLARIFY.' Подскажите, какая услуга вас интересует?');

        $conversation = new Conversation;

        $conversations = $this->conversations();
        $conversations->shouldReceive('bumpClarificationAttempts')->once()->with($conversation)->andReturn(1);
        $conversations->shouldNotReceive('resetClarificationAttempts');

        $reply = $this->composer($llm, $conversations)->compose(new Tenant(['name' => 'Бизнес']), $conversation);

        $this->assertFalse($reply->escalate);
        $this->assertSame('Подскажите, какая услуга вас интересует?', $reply->text);
    }

    public function test_escalates_after_third_clarification(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()
            ->andReturn(PromptBuilder::CLARIFY.' Не совсем понял вопрос.');

        $conversation = new Conversation(['clarification_attempts' => 2]);

        $conversations = $this->conversations();
        // Третья подряд непонятка → счётчик доходит до лимита.
        $conversations->shouldReceive('bumpClarificationAttempts')->once()->with($conversation)->andReturn(3);
        $conversations->shouldReceive('resetClarificationAttempts')->once()->with($conversation);

        $reply = $this->composer($llm, $conversations)->compose(new Tenant(['name' => 'Бизнес']), $conversation);

        $this->assertTrue($reply->escalate);
        $this->assertStringContainsString('администратору', $reply->text);
    }

    public function test_booked_sentinel_closes_dialog(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()
            ->andReturn(PromptBuilder::BOOKED.' Записал вас на завтра в 15:00, ждём!');

        $reply = $this->composer($llm)->compose(new Tenant(['name' => 'Бизнес']), new Conversation);

        $this->assertTrue($reply->booked);
        $this->assertFalse($reply->escalate);
        $this->assertSame('Записал вас на завтра в 15:00, ждём!', $reply->text);
    }

    public function test_booked_sentinel_in_the_middle_is_stripped(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()
            ->andReturn('Хорошо, Паша, записываю на 14:00. '.PromptBuilder::BOOKED.' Подтверждаю запись. Ждём!');

        $reply = $this->composer($llm)->compose(new Tenant(['name' => 'Бизнес']), new Conversation);

        $this->assertTrue($reply->booked);
        $this->assertFalse($reply->escalate);
        $this->assertStringNotContainsString('[[BOOKED]]', $reply->text);
        $this->assertStringContainsString('Подтверждаю запись', $reply->text);
        $this->assertStringContainsString('Хорошо, Паша', $reply->text);
    }

    public function test_book_sentinel_starts_booking(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()->andReturn(PromptBuilder::BOOK);

        $reply = $this->composer($llm)->compose(new Tenant(['name' => 'Бизнес']), new Conversation, bookingEnabled: true);

        $this->assertTrue($reply->startBooking);
        $this->assertFalse($reply->escalate);
        $this->assertFalse($reply->booked);
        $this->assertStringNotContainsString('[[BOOK]]', $reply->text);
    }

    public function test_cancellation_sentinel_marks_cancelled(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()
            ->andReturn('Отменил вашу запись на завтра. '.PromptBuilder::CANCELLED.' Ждём вас снова!');

        $reply = $this->composer($llm)->compose(new Tenant(['name' => 'Бизнес']), new Conversation);

        $this->assertTrue($reply->cancelled);
        $this->assertFalse($reply->booked);
        $this->assertFalse($reply->escalate);
        $this->assertStringNotContainsString('[[CANCELLED]]', $reply->text);
        $this->assertStringContainsString('Отменил вашу запись', $reply->text);
    }

    public function test_resets_streak_when_model_answers(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()->andReturn('Да, конечно, записать вас?');

        $conversation = new Conversation(['clarification_attempts' => 2]);

        $conversations = $this->conversations();
        $conversations->shouldReceive('resetClarificationAttempts')->once()->with($conversation);
        $conversations->shouldNotReceive('bumpClarificationAttempts');

        $reply = $this->composer($llm, $conversations)->compose(new Tenant(['name' => 'Бизнес']), $conversation);

        $this->assertFalse($reply->escalate);
        $this->assertSame('Да, конечно, записать вас?', $reply->text);
    }

    /**
     * @param  list<array{path: string, url: string}>  $images
     */
    private function composerWithEntryImages(LlmClient $llm, array $images): ReplyComposer
    {
        $entry = new KnowledgeEntry(['title' => 'Стрижки', 'content' => 'Делаем фейды.', 'is_published' => true, 'links' => [], 'images' => $images]);
        $entry->id = 'e1';

        $knowledge = Mockery::mock(KnowledgeEntryRepositoryInterface::class);
        $knowledge->shouldReceive('publishedForCurrentTenant')->andReturn(new Collection([$entry]));
        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recentForChat')->andReturn(new Collection);
        $crmKnowledge = Mockery::mock(CrmKnowledgeRepositoryInterface::class);
        $crmKnowledge->shouldReceive('forCurrentTenant')->andReturn(new Collection);
        // Запись — релевантный хит RAG: медиа берётся из неё (одна целевая запись).
        $retriever = Mockery::mock(KnowledgeRetriever::class);
        $retriever->shouldReceive('retrieve')->andReturn(['manual' => ['e1'], 'crm' => []]);

        return new ReplyComposer($llm, new PromptBuilder, $knowledge, $messages, $this->conversations(), $crmKnowledge, $retriever);
    }

    public function test_photos_marker_attaches_real_image_urls_and_strips_marker(): void
    {
        // Метка [[PHOTOS]] → прикрепляем ТОЧНЫЙ URL из данных (не из текста LLM),
        // а саму метку убираем из видимого текста.
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()->andReturn('Вот примеры наших работ! [[PHOTOS]]');

        $reply = $this->composerWithEntryImages($llm, [['path' => 'knowledge/x.jpg', 'url' => 'https://otcl1ck.ru/storage/knowledge/x.jpg']])
            ->compose(new Tenant(['name' => 'Бизнес', 'settings' => ['overrides' => ['rag' => true]]]), new Conversation);

        $this->assertSame(['https://otcl1ck.ru/storage/knowledge/x.jpg'], $reply->images);
        $this->assertStringNotContainsString('PHOTOS', $reply->text);
        $this->assertStringContainsString('примеры', $reply->text);
    }

    public function test_links_of_retrieved_entry_are_appended_to_answer(): void
    {
        // Если RAG отобрал релевантную запись со ссылкой — ссылка ВСЕГДА попадает
        // в ответ отдельным блоком (URL берём из данных, не из текста LLM).
        $entry = new KnowledgeEntry([
            'title' => 'Прайс',
            'content' => 'Стрижка 1500 ₽.',
            'is_published' => true,
            'links' => [['label' => 'Прайс-лист', 'url' => 'https://otcl1ck.ru/price.pdf']],
            'images' => [],
        ]);
        $entry->id = 'k1';

        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()->andReturn('Мужская стрижка стоит 1500 ₽.');

        $knowledge = Mockery::mock(KnowledgeEntryRepositoryInterface::class);
        $knowledge->shouldReceive('publishedForCurrentTenant')->andReturn(new Collection([$entry]));
        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recentForChat')->andReturn(new Collection);
        $crmKnowledge = Mockery::mock(CrmKnowledgeRepositoryInterface::class);
        $crmKnowledge->shouldReceive('forCurrentTenant')->andReturn(new Collection);
        $retriever = Mockery::mock(KnowledgeRetriever::class);
        $retriever->shouldReceive('retrieve')->once()->andReturn(['manual' => ['k1'], 'crm' => []]);

        $composer = new ReplyComposer($llm, new PromptBuilder, $knowledge, $messages, $this->conversations(), $crmKnowledge, $retriever);

        // RAG включаем оверрайдом, иначе ретривер не вызывается (фолбэк на всю базу).
        $tenant = new Tenant(['name' => 'Бизнес', 'settings' => ['overrides' => ['rag' => true]]]);
        $reply = $composer->compose($tenant, new Conversation);

        $this->assertStringContainsString('Мужская стрижка стоит 1500', $reply->text);
        $this->assertStringContainsString('Прайс-лист', $reply->text);
        $this->assertStringContainsString('https://otcl1ck.ru/price.pdf', $reply->text);
    }

    public function test_only_top_relevant_entry_links_are_appended(): void
    {
        // Регресс: при вопросе про стрижки бот не должен прилеплять ссылку из
        // соседней (менее релевантной) записи — например, инстаграм мастера.
        $top = new KnowledgeEntry([
            'title' => 'Виды стрижек',
            'content' => 'Фейд, андеркат, классика.',
            'is_published' => true,
            'links' => [['label' => 'Прайс', 'url' => 'https://otcl1ck.ru/price.pdf']],
            'images' => [],
        ]);
        $top->id = 'top';
        $other = new KnowledgeEntry([
            'title' => 'Барбер Никита',
            'content' => 'Мастер Никита.',
            'is_published' => true,
            'links' => [['label' => 'Instagram Никиты', 'url' => 'https://instagram.com/nikita']],
            'images' => [],
        ]);
        $other->id = 'barber';

        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()->andReturn('Делаем фейд, андеркат и классику.');

        $knowledge = Mockery::mock(KnowledgeEntryRepositoryInterface::class);
        $knowledge->shouldReceive('publishedForCurrentTenant')->andReturn(new Collection([$top, $other]));
        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recentForChat')->andReturn(new Collection);
        $crmKnowledge = Mockery::mock(CrmKnowledgeRepositoryInterface::class);
        $crmKnowledge->shouldReceive('forCurrentTenant')->andReturn(new Collection);
        $retriever = Mockery::mock(KnowledgeRetriever::class);
        // Порядок = релевантность: «Виды стрижек» сверху, «Барбер Никита» ниже.
        $retriever->shouldReceive('retrieve')->once()->andReturn(['manual' => ['top', 'barber'], 'crm' => []]);

        $composer = new ReplyComposer($llm, new PromptBuilder, $knowledge, $messages, $this->conversations(), $crmKnowledge, $retriever);

        $tenant = new Tenant(['name' => 'Бизнес', 'settings' => ['overrides' => ['rag' => true]]]);
        $reply = $composer->compose($tenant, new Conversation);

        // Ссылка верхней записи — есть; инстаграм соседней — нет.
        $this->assertStringContainsString('https://otcl1ck.ru/price.pdf', $reply->text);
        $this->assertStringNotContainsString('instagram.com/nikita', $reply->text);
    }

    public function test_only_top_relevant_entry_images_are_attached(): void
    {
        // Регресс: фото прикрепляются только из самой релевантной записи, а не из
        // соседней (картинки из одного элемента БЗ не примешиваются в другой).
        $top = new KnowledgeEntry([
            'title' => 'Стрижки', 'content' => 'Фейды.', 'is_published' => true, 'links' => [],
            'images' => [['path' => 'k/a.jpg', 'url' => 'https://otcl1ck.ru/a.jpg']],
        ]);
        $top->id = 'top';
        $other = new KnowledgeEntry([
            'title' => 'Маникюр', 'content' => 'Покрытие.', 'is_published' => true, 'links' => [],
            'images' => [['path' => 'k/b.jpg', 'url' => 'https://otcl1ck.ru/b.jpg']],
        ]);
        $other->id = 'other';

        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()->andReturn('Вот примеры стрижек! [[PHOTOS]]');

        $knowledge = Mockery::mock(KnowledgeEntryRepositoryInterface::class);
        $knowledge->shouldReceive('publishedForCurrentTenant')->andReturn(new Collection([$top, $other]));
        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recentForChat')->andReturn(new Collection);
        $crmKnowledge = Mockery::mock(CrmKnowledgeRepositoryInterface::class);
        $crmKnowledge->shouldReceive('forCurrentTenant')->andReturn(new Collection);
        $retriever = Mockery::mock(KnowledgeRetriever::class);
        $retriever->shouldReceive('retrieve')->once()->andReturn(['manual' => ['top', 'other'], 'crm' => []]);

        $composer = new ReplyComposer($llm, new PromptBuilder, $knowledge, $messages, $this->conversations(), $crmKnowledge, $retriever);
        $tenant = new Tenant(['name' => 'Бизнес', 'settings' => ['overrides' => ['rag' => true]]]);
        $reply = $composer->compose($tenant, new Conversation);

        $this->assertSame(['https://otcl1ck.ru/a.jpg'], $reply->images); // только фото верхней записи
    }

    public function test_no_photos_marker_means_no_images(): void
    {
        // Без метки [[PHOTOS]] фото не прикрепляются, даже если у записи они есть.
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()->andReturn('Мужская стрижка — 1500 ₽.');

        $reply = $this->composerWithEntryImages($llm, [['path' => 'knowledge/x.jpg', 'url' => 'https://otcl1ck.ru/storage/knowledge/x.jpg']])
            ->compose(new Tenant(['name' => 'Бизнес', 'settings' => ['overrides' => ['rag' => true]]]), new Conversation);

        $this->assertSame([], $reply->images);
        $this->assertSame('Мужская стрижка — 1500 ₽.', $reply->text);
    }

    public function test_attaches_only_target_entry_images_not_other_entries(): void
    {
        // Прод-баг: на вопрос про «mod cut» приходило 1 фото mod cut + чужие стрижки.
        // Медиа — ТОЛЬКО из релевантной записи (в полном объёме), без примешивания.
        $modcut = new KnowledgeEntry(['title' => 'mod cut', 'content' => 'стрижка', 'is_published' => true, 'links' => [], 'images' => [
            ['path' => 'k/m1.jpg', 'url' => 'https://otcl1ck.ru/storage/k/m1.jpg'],
            ['path' => 'k/m2.jpg', 'url' => 'https://otcl1ck.ru/storage/k/m2.jpg'],
        ]]);
        $modcut->id = 'mod';
        $crop = new KnowledgeEntry(['title' => 'Crop', 'content' => 'стрижка', 'is_published' => true, 'links' => [], 'images' => [
            ['path' => 'k/c1.jpg', 'url' => 'https://otcl1ck.ru/storage/k/c1.jpg'],
        ]]);
        $crop->id = 'crop';

        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()->andReturn('Вот примеры! [[PHOTOS]]');

        $knowledge = Mockery::mock(KnowledgeEntryRepositoryInterface::class);
        $knowledge->shouldReceive('publishedForCurrentTenant')->andReturn(new Collection([$modcut, $crop]));
        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recentForChat')->andReturn(new Collection);
        $crmKnowledge = Mockery::mock(CrmKnowledgeRepositoryInterface::class);
        $crmKnowledge->shouldReceive('forCurrentTenant')->andReturn(new Collection);
        $retriever = Mockery::mock(KnowledgeRetriever::class);
        $retriever->shouldReceive('retrieve')->andReturn(['manual' => ['mod'], 'crm' => []]);

        $composer = new ReplyComposer($llm, new PromptBuilder, $knowledge, $messages, $this->conversations(), $crmKnowledge, $retriever);
        $reply = $composer->compose(new Tenant(['name' => 'Бизнес', 'settings' => ['overrides' => ['rag' => true]]]), new Conversation);

        // Оба фото mod cut и НИ одного фото Crop.
        $this->assertSame([
            'https://otcl1ck.ru/storage/k/m1.jpg',
            'https://otcl1ck.ru/storage/k/m2.jpg',
        ], $reply->images);
    }
}

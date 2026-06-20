<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\RussianStem;
use PHPUnit\Framework\TestCase;

final class RussianStemTest extends TestCase
{
    /**
     * Главное требование: словоформы одного слова сводятся к основам с общим
     * префиксом (≥3 символов) — тогда триггер «акция» поймает «акции/акцию/акций»
     * в сообщении клиента (матчинг по префикс-вхождению основ, см. FlowEngine).
     * Точное равенство основ не гарантируется (особенность Snowball на -сь/-ок).
     */
    public function test_word_forms_share_a_stem_prefix(): void
    {
        foreach ([
            ['акция', 'акции', 'акцию', 'акций', 'акциями'],
            ['стрижка', 'стрижки', 'стрижку', 'стрижке'],
            ['цена', 'цены', 'цену', 'ценой'],
            ['услуга', 'услуги', 'услугу', 'услугами'],
            ['запись', 'записи', 'записью'],
            ['скидка', 'скидки', 'скидку', 'скидками'],
        ] as $forms) {
            $stems = array_map(RussianStem::stem(...), $forms);
            $shortest = array_reduce($stems, fn (string $c, string $s): string => mb_strlen($s) < mb_strlen($c) ? $s : $c, $stems[0]);
            $this->assertGreaterThanOrEqual(3, mb_strlen($shortest), 'Слишком короткая основа: '.implode(',', $stems));
            foreach ($stems as $stem) {
                $this->assertStringStartsWith($shortest, $stem, 'Основы без общего префикса: '.implode(',', $stems));
            }
        }
    }

    public function test_distinct_words_have_distinct_stems(): void
    {
        $this->assertNotSame(RussianStem::stem('акция'), RussianStem::stem('стрижка'));
        $this->assertNotSame(RussianStem::stem('цена'), RussianStem::stem('запись'));
    }

    public function test_non_cyrillic_and_empty_pass_through(): void
    {
        $this->assertSame('', RussianStem::stem(''));
        $this->assertSame('promo', RussianStem::stem('Promo'));
    }
}

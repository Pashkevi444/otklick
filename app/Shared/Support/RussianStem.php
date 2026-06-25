<?php

declare(strict_types=1);

namespace App\Shared\Support;

use App\Modules\Flows\Services\FlowEngine;

/**
 * Стемминг русского слова (алгоритм Snowball Russian) — приводит словоформы к
 * общей основе: «акция/акции/акцию/акций» → «акц», «стрижка/стрижки/стрижку» →
 * «стрижк». Нужен для матчинга триггеров сценариев по смыслу слова, а не по
 * точному совпадению (см. {@see FlowEngine}).
 *
 * Детерминированный, без внешних зависимостей и без обращений к сети/БД.
 */
final class RussianStem
{
    private const string VOWELS = 'аеиоуыэюя';

    /** @var list<string> Деепричастие, группа 1 (после а/я). */
    private const array PERFECTIVE_GERUND_1 = ['вшись', 'вши', 'в'];

    /** @var list<string> Деепричастие, группа 2. */
    private const array PERFECTIVE_GERUND_2 = ['ывшись', 'ившись', 'ывши', 'ивши', 'ыв', 'ив'];

    /** @var list<string> */
    private const array ADJECTIVE = [
        'ими', 'ыми', 'его', 'ого', 'ему', 'ому', 'ее', 'ие', 'ые', 'ое', 'ей',
        'ий', 'ый', 'ой', 'ем', 'им', 'ым', 'ом', 'их', 'ых', 'ую', 'юю', 'ая',
        'яя', 'ою', 'ею',
    ];

    /** @var list<string> Причастие, группа 1 (после а/я). */
    private const array PARTICIPLE_1 = ['ющ', 'ем', 'нн', 'вш', 'щ'];

    /** @var list<string> Причастие, группа 2. */
    private const array PARTICIPLE_2 = ['ивш', 'ывш', 'ующ'];

    /** @var list<string> */
    private const array REFLEXIVE = ['ся', 'сь'];

    /** @var list<string> Глагол, группа 1 (после а/я). */
    private const array VERB_1 = [
        'ете', 'йте', 'ешь', 'нно', 'ла', 'на', 'ли', 'ем', 'ло', 'но', 'ет',
        'ют', 'ны', 'ть', 'й', 'л', 'н',
    ];

    /** @var list<string> Глагол, группа 2. */
    private const array VERB_2 = [
        'ейте', 'уйте', 'ила', 'ыла', 'ена', 'ите', 'или', 'ыли', 'ило', 'ыло',
        'ено', 'ует', 'уют', 'ишь', 'ить', 'ыть', 'ены', 'ей', 'уй', 'ил', 'ыл',
        'им', 'ым', 'ен', 'ят', 'ит', 'ыт', 'ую', 'ю',
    ];

    /** @var list<string> */
    private const array NOUN = [
        'иями', 'ями', 'ами', 'ией', 'иям', 'ием', 'иях', 'ев', 'ов', 'ие', 'ье',
        'еи', 'ии', 'ей', 'ой', 'ий', 'иям', 'ям', 'ем', 'ам', 'ом', 'ах', 'ях',
        'ию', 'ью', 'ия', 'ья', 'а', 'е', 'и', 'й', 'о', 'у', 'ы', 'ь', 'ю', 'я',
    ];

    /** @var list<string> */
    private const array SUPERLATIVE = ['ейше', 'ейш'];

    /** @var list<string> */
    private const array DERIVATIONAL = ['ость', 'ост'];

    public static function stem(string $word): string
    {
        $word = str_replace('ё', 'е', mb_strtolower(trim($word)));

        if ($word === '' || preg_match('/[а-я]/u', $word) !== 1) {
            return $word;
        }

        $rv = self::rvStart($word);
        $r2 = self::r2Start($word);

        $word = self::step1($word, $rv);
        $word = self::step2($word, $rv);
        $word = self::step3($word, $r2);
        $word = self::step4($word, $rv);

        return $word;
    }

    /** Шаг 1: деепричастие → (рефлексив + прил./глагол/сущ.). */
    private static function step1(string $word, int $rv): string
    {
        $gerund = self::removePrecededByAYa($word, $rv, self::PERFECTIVE_GERUND_1)
            ?? self::removeEnding($word, $rv, self::PERFECTIVE_GERUND_2);
        if ($gerund !== null) {
            return $gerund;
        }

        $word = self::removeEnding($word, $rv, self::REFLEXIVE) ?? $word;

        $adj = self::removeEnding($word, $rv, self::ADJECTIVE);
        if ($adj !== null) {
            // Прилагательное может предваряться причастием — снимаем и его.
            return self::removePrecededByAYa($adj, $rv, self::PARTICIPLE_1)
                ?? self::removeEnding($adj, $rv, self::PARTICIPLE_2)
                ?? $adj;
        }

        $verb = self::removePrecededByAYa($word, $rv, self::VERB_1)
            ?? self::removeEnding($word, $rv, self::VERB_2);
        if ($verb !== null) {
            return $verb;
        }

        return self::removeEnding($word, $rv, self::NOUN) ?? $word;
    }

    /** Шаг 2: снять конечную «и» в RV. */
    private static function step2(string $word, int $rv): string
    {
        return self::removeEnding($word, $rv, ['и']) ?? $word;
    }

    /** Шаг 3: снять словообразовательный суффикс (ость/ост) в R2. */
    private static function step3(string $word, int $r2): string
    {
        return self::removeEnding($word, $r2, self::DERIVATIONAL) ?? $word;
    }

    /** Шаг 4: удвоенная «н», превосходная степень, мягкий знак. */
    private static function step4(string $word, int $rv): string
    {
        if (self::endsInRv($word, $rv, 'нн')) {
            return self::cut($word, 1);
        }

        $sup = self::removeEnding($word, $rv, self::SUPERLATIVE);
        if ($sup !== null) {
            return self::endsInRv($sup, $rv, 'нн') ? self::cut($sup, 1) : $sup;
        }

        return self::removeEnding($word, $rv, ['ь']) ?? $word;
    }

    /**
     * Снимает первое из подходящих окончаний (длиннейшее), если оно целиком в
     * регионе [start..]. Возвращает null, если ничего не снято.
     *
     * @param  list<string>  $endings
     */
    private static function removeEnding(string $word, int $start, array $endings): ?string
    {
        $best = null;
        foreach ($endings as $ending) {
            if (self::endsInRv($word, $start, $ending) && ($best === null || mb_strlen($ending) > mb_strlen($best))) {
                $best = $ending;
            }
        }

        return $best === null ? null : self::cut($word, mb_strlen($best));
    }

    /**
     * Как {@see removeEnding}, но окончанию должна предшествовать «а» или «я»
     * (требование групп 1 для деепричастий/причастий/глаголов).
     *
     * @param  list<string>  $endings
     */
    private static function removePrecededByAYa(string $word, int $start, array $endings): ?string
    {
        $best = null;
        foreach ($endings as $ending) {
            $at = mb_strlen($word) - mb_strlen($ending);
            if ($at - 1 < $start || ! self::endsInRv($word, $start, $ending)) {
                continue;
            }
            $prev = mb_substr($word, $at - 1, 1);
            if (($prev === 'а' || $prev === 'я') && ($best === null || mb_strlen($ending) > mb_strlen($best))) {
                $best = $ending;
            }
        }

        return $best === null ? null : self::cut($word, mb_strlen($best));
    }

    private static function endsInRv(string $word, int $start, string $suffix): bool
    {
        $at = mb_strlen($word) - mb_strlen($suffix);

        return $at >= $start && mb_substr($word, $at) === $suffix;
    }

    private static function cut(string $word, int $n): string
    {
        return mb_substr($word, 0, mb_strlen($word) - $n);
    }

    /** Начало региона RV — после первой гласной. */
    private static function rvStart(string $word): int
    {
        $len = mb_strlen($word);
        for ($i = 0; $i < $len; $i++) {
            if (mb_strpos(self::VOWELS, mb_substr($word, $i, 1)) !== false) {
                return $i + 1;
            }
        }

        return $len;
    }

    /** Начало региона R2 (после второй гласной+согласной пары). */
    private static function r2Start(string $word): int
    {
        return self::region($word, self::region($word, 0));
    }

    /** Регион: после первой «гласная→согласная» начиная со start. */
    private static function region(string $word, int $start): int
    {
        $len = mb_strlen($word);
        $i = $start;
        while ($i < $len && mb_strpos(self::VOWELS, mb_substr($word, $i, 1)) === false) {
            $i++;
        }
        while ($i < $len && mb_strpos(self::VOWELS, mb_substr($word, $i, 1)) !== false) {
            $i++;
        }

        return $i < $len ? $i + 1 : $len;
    }
}

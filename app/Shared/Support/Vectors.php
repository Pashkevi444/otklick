<?php

declare(strict_types=1);

namespace App\Shared\Support;

/**
 * Операции над эмбеддинг-векторами. Косинусная близость — мера семантического
 * сходства (1 — совпадают по смыслу, 0 — не связаны). Используется и RAG-поиском
 * по базе знаний, и семантическим матчингом триггеров сценариев.
 */
final class Vectors
{
    /**
     * Косинус угла между векторами. Разная длина допустима (недостающие
     * координаты считаются нулевыми). 0, если любой из векторов нулевой.
     *
     * @param  array<int, float|int>  $a
     * @param  array<int, float|int>  $b
     */
    public static function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;

        foreach ($a as $i => $va) {
            $vb = (float) ($b[$i] ?? 0.0);
            $dot += (float) $va * $vb;
            $na += (float) $va * (float) $va;
            $nb += $vb * $vb;
        }

        $denom = sqrt($na) * sqrt($nb);

        return $denom > 0.0 ? $dot / $denom : 0.0;
    }
}

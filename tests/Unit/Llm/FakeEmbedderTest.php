<?php

declare(strict_types=1);

namespace Tests\Unit\Llm;

use App\Llm\FakeEmbedder;
use PHPUnit\Framework\TestCase;

final class FakeEmbedderTest extends TestCase
{
    private function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        foreach ($a as $i => $v) {
            $dot += $v * $b[$i];
        }

        return $dot; // вектора нормированы
    }

    public function test_dimension_and_normalization(): void
    {
        $vec = (new FakeEmbedder(64))->embed('стрижка бороды');

        $this->assertCount(64, $vec);
        $this->assertEqualsWithDelta(1.0, sqrt(array_sum(array_map(fn (float $v): float => $v * $v, $vec))), 0.0001);
    }

    public function test_similar_text_is_closer_than_unrelated(): void
    {
        $embedder = new FakeEmbedder(256);
        $query = $embedder->embed('сколько стоит стрижка');
        $related = $embedder->embed('мужская стрижка стоит 1500');
        $unrelated = $embedder->embed('доставка пиццы бесплатно');

        $this->assertGreaterThan($this->cosine($query, $unrelated), $this->cosine($query, $related));
    }
}

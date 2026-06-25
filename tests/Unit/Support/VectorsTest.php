<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Shared\Support\Vectors;
use PHPUnit\Framework\TestCase;

final class VectorsTest extends TestCase
{
    public function test_identical_vectors_have_cosine_one(): void
    {
        $this->assertEqualsWithDelta(1.0, Vectors::cosine([1.0, 2.0, 3.0], [1.0, 2.0, 3.0]), 1e-9);
    }

    public function test_orthogonal_vectors_have_cosine_zero(): void
    {
        $this->assertEqualsWithDelta(0.0, Vectors::cosine([1.0, 0.0], [0.0, 1.0]), 1e-9);
    }

    public function test_zero_vector_is_safe(): void
    {
        $this->assertSame(0.0, Vectors::cosine([0.0, 0.0], [1.0, 1.0]));
    }

    public function test_tolerates_different_lengths(): void
    {
        $this->assertEqualsWithDelta(1.0, Vectors::cosine([1.0, 0.0], [1.0]), 1e-9);
    }
}

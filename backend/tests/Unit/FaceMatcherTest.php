<?php

namespace Tests\Unit;

use App\Services\FaceMatcher;
use PHPUnit\Framework\TestCase;

class FaceMatcherTest extends TestCase
{
    public function test_identical_vectors_have_cosine_one(): void
    {
        $this->assertEqualsWithDelta(1.0, FaceMatcher::cosine([1, 2, 3], [1, 2, 3]), 1e-9);
    }

    public function test_orthogonal_vectors_have_cosine_zero(): void
    {
        $this->assertEqualsWithDelta(0.0, FaceMatcher::cosine([1, 0], [0, 1]), 1e-9);
    }

    public function test_mismatched_length_returns_negative(): void
    {
        $this->assertSame(-1.0, FaceMatcher::cosine([1, 0, 0], [1, 0]));
    }

    public function test_empty_returns_negative(): void
    {
        $this->assertSame(-1.0, FaceMatcher::cosine([], []));
    }
}

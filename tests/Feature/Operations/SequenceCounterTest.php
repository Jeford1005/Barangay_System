<?php

namespace Tests\Feature\Operations;

use App\Services\SequenceCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SequenceCounterTest extends TestCase
{
    use RefreshDatabase;

    public function test_counter_reserves_incrementing_numbers(): void
    {
        $counter = app(SequenceCounter::class);

        $this->assertSame(1, $counter->reserve('test', 2026, 0));
        $this->assertSame(2, $counter->reserve('test', 2026, 0));
        $this->assertSame(1, $counter->reserve('test', 2027, 0));
    }

    public function test_counter_never_reuses_a_number_after_existing_rows_are_backfilled(): void
    {
        $counter = app(SequenceCounter::class);

        $this->assertSame(8, $counter->reserve('test', 2026, 7));
        $this->assertSame(9, $counter->reserve('test', 2026, 7));
    }

    public function test_counter_rejects_overflow(): void
    {
        $this->expectException(\RuntimeException::class);

        app(SequenceCounter::class)->reserve('test', 2026, 9999);
    }
}

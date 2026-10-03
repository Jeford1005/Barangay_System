<?php

namespace Tests\Feature\Operations;

use App\Services\SequenceCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_two_rapid_reserves_are_never_equal_and_increase_monotonically(): void
    {
        $counter = app(SequenceCounter::class);

        $numbers = [];
        for ($i = 0; $i < 10; $i++) {
            $numbers[] = $counter->reserve('rapid', 2026, 0);
        }

        $this->assertCount(10, array_unique($numbers), 'rapid reserves must never hand out a duplicate');
        $sorted = $numbers;
        sort($sorted);
        $this->assertSame($sorted, $numbers, 'rapid reserves must increase monotonically');
    }

    public function test_counter_honors_a_backfilled_maximum_when_the_counter_row_lags(): void
    {
        DB::table('sequence_counters')->insert([
            'scope' => 'lagging',
            'year' => 2026,
            'next_value' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $counter = app(SequenceCounter::class);

        // The counter row says 5 but the ledger already holds numbers up to
        // 20: the next reservation must jump past the backfilled maximum,
        // never reuse 5.
        $this->assertSame(21, $counter->reserve('lagging', 2026, 20));
        $this->assertSame(22, $counter->reserve('lagging', 2026, 20));
    }

    public function test_insert_race_fallback_claims_a_unique_number_honoring_the_maximum(): void
    {
        // Simulate the insertOrIgnore loser: a conflicting row appears after
        // this reservation's first read missed it, so the fallback
        // compare-and-swap path claims the number instead of guessing one.
        $armed = true;
        DB::listen(function ($query) use (&$armed): void {
            if (! $armed) {
                return;
            }

            $sql = strtolower($query->sql);

            if (str_contains($sql, 'sequence_counters')
                && str_starts_with(ltrim($sql), 'select')
                && ! str_contains($sql, 'next_value')
            ) {
                $armed = false;

                DB::table('sequence_counters')->insert([
                    'scope' => 'race',
                    'year' => 2026,
                    'next_value' => 8,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $counter = app(SequenceCounter::class);

        // The race row says 8 but the ledger already holds numbers up to 10:
        // the fallback must honor currentMaximum (11), not return 8.
        $this->assertSame(11, $counter->reserve('race', 2026, 10));
        $this->assertSame(12, $counter->reserve('race', 2026, 10));
    }

    public function test_fallback_contention_throws_instead_of_returning_a_duplicate(): void
    {
        // Rig a lost compare-and-swap: the race row appears for the
        // fallback, then another claimant advances it before the CAS update
        // runs. With a single attempt allowed, the reservation must throw
        // rather than hand out the stale number a second time.
        $stage = 0;
        DB::listen(function ($query) use (&$stage): void {
            $sql = strtolower($query->sql);

            if (! str_contains($sql, 'sequence_counters')
                || ! str_starts_with(ltrim($sql), 'select')
            ) {
                return;
            }

            if ($stage === 0 && ! str_contains($sql, 'next_value')) {
                $stage = 1;

                DB::table('sequence_counters')->insert([
                    'scope' => 'contended',
                    'year' => 2026,
                    'next_value' => 8,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } elseif ($stage === 1 && str_contains($sql, 'next_value')) {
                $stage = 2;

                // Another claimant got there first.
                DB::table('sequence_counters')
                    ->where('scope', 'contended')
                    ->where('year', 2026)
                    ->update(['next_value' => 42, 'updated_at' => now()]);
            }
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('contended');

        app(SequenceCounter::class)->reserve('contended', 2026, 0, 9999, 1);
    }
}

<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Thrown when a counter reservation loses a concurrent race and must be
 * retried. Never returned to callers as a guessed number.
 */
class SequenceContentionException extends RuntimeException
{
}

class SequenceCounter
{
    /**
     * Reserve the next number for a scope/year without relying on a racy
     * max()+1 query when the sequence is empty.
     *
     * Every return path hands out a unique number or throws: the
     * insert-or-claim fallback uses a compare-and-swap update (only the
     * writer that still sees the value it read advances the row), so two
     * losers of the same insert race can never read the same next_value
     * and both return it. A lost race, a vanished row, or a concurrent
     * write error retries with jitter up to $maxAttempts and then throws
     * instead of returning a guess. On SQLite `lockForUpdate()` compiles
     * to nothing, which is exactly why the CAS + retry exists.
     *
     * Uniqueness is also enforced by the UNIQUE(scope, year) index (see
     * 2026_09_26_000007_create_sequence_counters_table), so a duplicate
     * row can never be persisted even under contention.
     */
    public function reserve(string $scope, int $year, int $currentMaximum, int $limit = 9999, int $maxAttempts = 5): int
    {
        $attempts = 0;

        while (true) {
            try {
                return DB::transaction(function () use ($scope, $year, $currentMaximum, $limit): int {
                    $row = DB::table('sequence_counters')
                        ->where('scope', $scope)
                        ->where('year', $year)
                        ->lockForUpdate()
                        ->first();

                    $next = max((int) ($row->next_value ?? 0), $currentMaximum + 1, 1);
                    if ($next > $limit) {
                        throw new RuntimeException("The {$scope} sequence for {$year} has reached {$limit}.");
                    }

                    if ($row) {
                        DB::table('sequence_counters')
                            ->where('id', $row->id)
                            ->update(['next_value' => $next + 1, 'updated_at' => now()]);

                        return $next;
                    }

                    $inserted = DB::table('sequence_counters')->insertOrIgnore([
                        'scope' => $scope,
                        'year' => $year,
                        'next_value' => $next + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    if ($inserted !== 0) {
                        return $next;
                    }

                    // Another writer created the row first. Claim the next
                    // value with a compare-and-swap so concurrent losers
                    // cannot both read the same value and return duplicates.
                    $current = DB::table('sequence_counters')
                        ->where('scope', $scope)
                        ->where('year', $year)
                        ->value('next_value');

                    if ($current === null) {
                        // The row vanished between the insert race and the
                        // re-read; retry rather than guessing a number.
                        throw new SequenceContentionException(
                            "Concurrent sequence reservation for [{$scope}:{$year}] must be retried."
                        );
                    }

                    $next = max((int) $current, $currentMaximum + 1, 1);
                    if ($next > $limit) {
                        throw new RuntimeException("The {$scope} sequence for {$year} has reached {$limit}.");
                    }

                    $claimed = DB::table('sequence_counters')
                        ->where('scope', $scope)
                        ->where('year', $year)
                        ->where('next_value', $current)
                        ->update(['next_value' => $next + 1, 'updated_at' => now()]);

                    if ($claimed === 0) {
                        // Another claimant advanced the row first; retry
                        // rather than handing out the same number twice.
                        throw new SequenceContentionException(
                            "Concurrent sequence reservation for [{$scope}:{$year}] must be retried."
                        );
                    }

                    return $next;
                }, 3);
            } catch (SequenceContentionException $e) {
                $attempts++;

                if ($attempts >= $maxAttempts) {
                    throw new RuntimeException(
                        "The {$scope} sequence for {$year} is contended; please retry.",
                        0,
                        $e
                    );
                }

                // Small jittered back-off so contending clerks do not retry
                // in lockstep.
                usleep(random_int(1000, 5000) * $attempts);

                continue;
            } catch (QueryException $e) {
                // A concurrent write (e.g. SQLite "database is locked") is
                // also a lost race: retry with back-off instead of
                // surfacing as a 500 or returning a duplicate.
                $attempts++;

                if ($attempts >= $maxAttempts) {
                    throw $e;
                }

                usleep(random_int(1000, 5000) * $attempts);

                continue;
            }
        }
    }
}

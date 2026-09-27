<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class SequenceCounter
{
    /**
     * Reserve the next number for a scope/year without relying on a racy
     * max()+1 query when the sequence is empty.
     *
     * The insert is wrapped in `insertOrIgnore` because two clerks hitting a
     * brand new (scope, year) pair can both miss the row, and on SQLite the
     * `lockForUpdate()` above compiles to nothing. The loser of that race falls
     * back to an update of the row the winner inserted, and the transaction is
     * retried on deadlock/timeout rather than surfacing as a 500.
     */
    public function reserve(string $scope, int $year, int $currentMaximum, int $limit = 9999): int
    {
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

            if ($inserted === 0) {
                // Another writer created the row first; take the next value from
                // it rather than failing the whole request.
                $current = DB::table('sequence_counters')
                    ->where('scope', $scope)
                    ->where('year', $year)
                    ->value('next_value');

                $next = max((int) $current, 1);
                if ($next > $limit) {
                    throw new RuntimeException("The {$scope} sequence for {$year} has reached {$limit}.");
                }

                DB::table('sequence_counters')
                    ->where('scope', $scope)
                    ->where('year', $year)
                    ->update(['next_value' => $next + 1, 'updated_at' => now()]);
            }

            return $next;
        }, 3);
    }
}

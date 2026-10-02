<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Queue positions + honest ETAs for the resident portal's pending
 * certificate, blotter, and welfare requests.
 *
 * Position N of T for one pending row = the still-pending same-type rows
 * created before it (+1), over T = all still-pending same-type rows. Both
 * numbers come from a single COUNT aggregate per row, and decided-row
 * counts come from one GROUP BY aggregate per page (the same pattern the
 * reports and analytics pages use) — no request, blotter, or welfare row
 * is ever hydrated for the math.
 *
 * ETA days = ceil(position / dailyRate) where dailyRate =
 * decided same-type rows in the last WINDOW_DAYS / WINDOW_DAYS, with NO
 * floor under the pace: 5 completions in 30 days is 1 row per 6 days, so
 * position 5 is ~30 days out — flooring the rate at 1/day would print
 * ~5 days, a lie. Fewer than MIN_COMPLETIONS_FOR_ETA decided rows means
 * thin data, so the line shows the position only and no ETA. ETAs above
 * ETA_CAP_DAYS render as "more than 30 days".
 *
 * The counts span every resident's rows (that is what makes a queue a
 * queue) but only as numbers — no other resident's name, ID, or details
 * ever reach the view. Each line is one short muted string so a 375px
 * phone never overflows.
 */
final class QueueTracker
{
    public const WINDOW_DAYS = 30;

    public const MIN_COMPLETIONS_FOR_ETA = 5;

    public const ETA_CAP_DAYS = 30;

    /**
     * Normalize a free-text grouping key (complaint type, and anything
     * else grouped by typing rather than by taxonomy): trim + casefold so
     * " Noise complaint ", "noise complaint" and "NOISE COMPLAINT" form
     * one queue instead of three queues of one.
     *
     * REPORT: no complaint_type taxonomy exists — the column is free text
     * (maxlength 100, no lookup table, no enum), so the normalized text is
     * the grouping key. If a taxonomy is ever introduced, group by its id
     * instead and keep this only as a display fallback.
     */
    public static function normalizeKey(mixed $key): string
    {
        return mb_strtolower(trim((string) $key), 'UTF-8');
    }

    /**
     * One muted queue line per pending row on the current page, keyed by
     * the row's own key. Decided rows are skipped: they already show their
     * outcome in the card.
     *
     * @param  Collection<int, Model>  $items  current page rows (the resident's own)
     * @param  callable(Model): int|string  $typeOf  same-type grouping key
     * @param  callable(Model): bool  $isPending  still waiting on staff
     * @param  callable(int|string): \Illuminate\Database\Eloquent\Builder  $pendingQuery  still-pending rows of one type key
     * @param  callable(array<int, int|string>): Collection  $completions  decided-in-window counts per type key (one GROUP BY query)
     * @return array<int, string>
     */
    public static function lines(Collection $items, callable $typeOf, callable $isPending, callable $pendingQuery, callable $completions): array
    {
        $pending = $items->filter($isPending)->values();

        if ($pending->isEmpty()) {
            return [];
        }

        $keys = $pending->map($typeOf)->unique()->values()->all();

        // One GROUP BY aggregate for the whole page: type key → decided
        // rows in the window. Keys are normalized to strings because the
        // database returns grouping keys as strings.
        $doneByType = collect($completions($keys))
            ->mapWithKeys(fn ($count, $key) => [(string) $key => (int) $count]);

        $lines = [];

        foreach ($pending as $item) {
            $type = $typeOf($item);
            $keyName = $item->getKeyName();
            $stamped = $item->getAttribute('created_at');
            $timestamp = $stamped ? $stamped->toDateTimeString() : now()->toDateTimeString();

            // Ahead + total in one COUNT aggregate: rows of the same type
            // still waiting, and among them the ones created before this
            // one (id breaks created_at ties from same-second writes).
            $row = $pendingQuery($type)->selectRaw(
                'COUNT(*) as total,'
                ." SUM(CASE WHEN created_at < ? OR (created_at = ? AND {$keyName} < ?) THEN 1 ELSE 0 END) as ahead",
                [$timestamp, $timestamp, $item->getKey()],
            )->first();

            $total = max(1, (int) ($row->total ?? 0));
            $position = min($total, (int) ($row->ahead ?? 0) + 1);

            $done = $doneByType->get((string) $type, 0);
            $eta = null;

            if ($done >= self::MIN_COMPLETIONS_FOR_ETA) {
                // Honest pace, no floor: $done >= MIN_COMPLETIONS_FOR_ETA
                // is always > 0 here, so this cannot divide by zero. A
                // floor like max(1, …) would invent a 1/day pace the office
                // never demonstrated and print an ETA below the measured
                // one (e.g. ~5 days for work that really takes ~30).
                $dailyRate = $done / self::WINDOW_DAYS;
                $eta = (int) ceil($position / $dailyRate);
            }

            $lines[$item->getKey()] = self::line($position, $total, $eta);
        }

        return $lines;
    }

    /**
     * Short muted copy for one pending card. Thin data (no ETA) shows the
     * position only rather than guessing.
     */
    public static function line(int $position, int $total, ?int $etaDays): string
    {
        $line = "Position {$position} of {$total}";

        if ($etaDays === null) {
            return $line;
        }

        if ($etaDays > self::ETA_CAP_DAYS) {
            return $line.' · usually ready in more than 30 days';
        }

        return $line.' · usually ready in ~'.$etaDays.' day'.($etaDays === 1 ? '' : 's');
    }
}

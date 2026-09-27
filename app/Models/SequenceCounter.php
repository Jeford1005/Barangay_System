<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Yearly atomic sequences behind every control number.
 *
 *   certificate control : CLR-2026-0007  (scope "certificate:CLR")
 *   blotter case number : BLTR-2026-0001 (scope "certificate:BLTR")
 *
 * Both go through nextControlNumber(), which namespaces the scope by the
 * document prefix — legacy scope strings are kept so existing counters
 * continue instead of restarting.
 *
 * Numbers are reserved inside a transaction with a row lock, so two
 * concurrent issuances can never receive the same number, and a voided or
 * deleted record never releases its number back into circulation.
 */
class SequenceCounter extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['scope', 'year', 'last_value'];

    /** Reserve and return the next value for a scope in a given year. */
    public static function reserve(string $scope, ?int $year = null): int
    {
        $year ??= (int) now()->year;

        return DB::transaction(function () use ($scope, $year): int {
            /** @var static $counter */
            $counter = static::query()
                ->where('scope', $scope)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($counter === null) {
                $counter = static::create(['scope' => $scope, 'year' => $year, 'last_value' => 0]);
                $counter = static::query()
                    ->where('id', $counter->id)
                    ->lockForUpdate()
                    ->first();
            }

            $counter->last_value++;
            $counter->save();

            return $counter->last_value;
        });
    }

    /**
     * Next formatted control number, e.g. nextControlNumber('CLR')
     * → "CLR-2026-0007".
     */
    public static function nextControlNumber(string $prefix): string
    {
        $year = (int) now()->year;
        $value = static::reserve('certificate:'.$prefix, $year);

        return sprintf('%s-%d-%04d', $prefix, $year, $value);
    }
}

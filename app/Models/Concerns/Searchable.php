<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared LIKE-search helper for index, directory, archive and export queries.
 *
 * Centralizes three fixes that were previously copy-pasted (and drifting)
 * across controllers:
 *
 * - LIKE wildcards (`\`, `%`, `_`) in user input are escaped and every
 *   predicate carries an explicit `ESCAPE '\'` clause (portable across
 *   MySQL and SQLite), so input only ever matches literally.
 * - Curly/smart quotes and apostrophes are normalized to their ASCII
 *   equivalents, so `O'Brien` typed with either apostrophe style matches
 *   the stored spelling; each column is additionally matched in a
 *   quote-stripped form so `O'Brien` and `OBrien` find each other in
 *   either direction (input/stored with or without the apostrophe).
 * - Identifier-like columns (case/control/household codes) use a trailing-% 
 *   prefix match instead of a leading-% contains match, keeping the lookup
 *   sargable. Multi-word input is split into tokens matched against
 *   separate columns (AND across tokens, OR across columns) instead of a
 *   `CONCAT(...)` expression, which can never use an index.
 */
trait Searchable
{
    /**
     * Apply a free-text LIKE search across plain columns.
     *
     * @param  Builder  $query
     * @param  string|null  $term  Raw user input (capped/normalized here).
     * @param  list<string>  $columns  Plain column names on this model's table.
     * @param  list<string>  $prefixColumns  Subset of $columns matched by
     *                                       prefix (`term%`) instead of
     *                                       contains (`%term%`). Use for
     *                                       codes/numbers with a known head.
     */
    public function scopeSearch(Builder $query, mixed $term, array $columns, array $prefixColumns = []): Builder
    {
        $term = self::normalizeSearchTerm($term);

        if ($term === null || $term === '' || $columns === []) {
            return $query;
        }

        $tokens = self::searchTokens($term);

        if ($tokens === []) {
            return $query;
        }

        $prefixLookup = array_fill_keys($prefixColumns, true);

        return $query->where(function (Builder $query) use ($tokens, $columns, $prefixLookup): void {
            foreach ($tokens as $token) {
                $query->where(function (Builder $query) use ($token, $columns, $prefixLookup): void {
                    // Token with every quote/apostrophe style removed; paired
                    // with the dequoted column expression below.
                    $bare = str_replace(
                        ["'", '"', "\u{2018}", "\u{2019}", "\u{201A}", "\u{201B}", "\u{201C}", "\u{201D}"],
                        '',
                        $token
                    );

                    foreach (array_values($columns) as $index => $column) {
                        $prefix = isset($prefixLookup[$column]);
                        $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                        $query->{$method}("{$column} LIKE ? ESCAPE '\\'", [self::likePattern($token, $prefix)]);

                        // Quote-split variant: the column with quotes stripped
                        // versus the token with quotes stripped, so O'Brien
                        // matches OBrien whichever side holds the apostrophe.
                        if ($bare !== '') {
                            $query->orWhereRaw(
                                self::dequotedColumnSql($column)." LIKE ? ESCAPE '\\'",
                                [self::likePattern($bare, $prefix)]
                            );
                        }
                    }
                });
            }
        });
    }

    /**
     * Cap, tag-strip and quote-normalize raw search input.
     */
    public static function normalizeSearchTerm(mixed $value, int $max = 100): ?string
    {
        if (! is_scalar($value) && ! $value instanceof \Stringable) {
            return null;
        }

        $value = mb_substr((string) $value, 0, $max);
        $value = strip_tags($value);
        // Curly apostrophes/quotes (phones and word processors emit these)
        // become the ASCII forms stored in the database.
        $value = str_replace(
            ["\u{2018}", "\u{2019}", "\u{201A}", "\u{201B}", "\u{2032}", "\u{02BC}", "\u{FF07}"],
            "'",
            $value
        );
        $value = str_replace(
            ["\u{201C}", "\u{201D}", "\u{201E}", "\u{201F}", "\u{2033}", "\u{FF02}"],
            '"',
            $value
        );
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $value === '' ? null : $value;
    }

    /**
     * Escape LIKE wildcards so input only ever matches literally.
     * To be used with an explicit `ESCAPE '\'` clause.
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Build the LIKE pattern for one token.
     */
    public static function likePattern(string $token, bool $prefix = false): string
    {
        $escaped = self::escapeLike($token);

        return $prefix ? $escaped.'%' : '%'.$escaped.'%';
    }

    /**
     * SQL expression for a column with quote/apostrophe characters removed.
     *
     * Nested REPLACE() is supported by both MySQL and SQLite; the curly
     * literals are UTF-8 and both connections store text as UTF-8.
     */
    public static function dequotedColumnSql(string $column): string
    {
        $expression = $column;

        foreach (["'", '"', "\u{2018}", "\u{2019}", "\u{201A}", "\u{201B}", "\u{201C}", "\u{201D}"] as $quote) {
            $literal = str_replace("'", "''", $quote);
            $expression = "REPLACE({$expression}, '{$literal}', '')";
        }

        return $expression;
    }

    /**
     * Split a normalized term into match tokens (bounded for safety).
     *
     * @return list<string>
     */
    public static function searchTokens(string $term): array
    {
        $tokens = preg_split('/\s+/u', trim($term)) ?: [];

        $tokens = array_values(array_filter(
            array_map(fn (string $token): string => mb_substr(trim($token), 0, 50), $tokens),
            fn (string $token): bool => $token !== ''
        ));

        return array_slice($tokens, 0, 6);
    }
}

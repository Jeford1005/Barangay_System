<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use Searchable;

    /**
     * Placeholder stored wherever a sensitive value was redacted out of the
     * recorded properties. The DB audit row stays queryable (event, actor,
     * subject, filters) without keeping secrets.
     */
    public const REDACTED = '[REDACTED]';

    /**
     * Property keys that must never be persisted verbatim: credentials,
     * tokens and one-time codes. Matched case-insensitively against the
     * exact key name (so `household_code`, `case_number` and other business
     * codes keep working).
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'passwd',
        'pass',
        'token',
        'remember_token',
        'secret',
        'client_secret',
        'api_key',
        'apikey',
        'access_token',
        'refresh_token',
        'reset_token',
        'code',
        'reset_code',
        'verification_code',
        'otp',
        'pin',
        'authorization',
        'private_key',
    ];

    protected $fillable = [
        'occurred_at',
        'user_id',
        'user_email',
        'actor_type',
        'actor_id',
        'actor_email',
        'subject_type',
        'subject_id',
        'subject_label',
        'event',
        'ip_address',
        'user_agent',
        'properties',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'user_id' => 'integer',
        'actor_id' => 'integer',
        'subject_id' => 'integer',
        'properties' => 'array',
    ];

    /**
     * Record an accountability event. Never throws — auditing must not
     * break the request it is observing.
     */
    public static function record(
        string $event,
        ?int $userId,
        ?string $email,
        ?string $ip,
        ?string $userAgent,
        array $properties = [],
    ): ?self {
        $actorType = $userId ? 'user' : (str_starts_with($event, 'system.') ? 'system' : 'guest');
        $actorId = $userId;
        $actorEmail = $email;
        $subjectType = null;
        $subjectId = null;
        $subjectLabel = null;

        // Password-reset requests are made by an unauthenticated visitor;
        // the user in the legacy user_id/user_email fields is the subject.
        if (str_starts_with($event, 'password_reset.')) {
            $actorType = 'guest';
            $actorId = null;
            $actorEmail = null;
            $subjectType = 'user';
            $subjectId = $userId;
            $subjectLabel = $email;
        } elseif (array_key_exists('actor_id', $properties)) {
            // Account-management events historically stored the target in the
            // legacy fields. New rows preserve that compatibility while
            // recording the real administrator as the actor and the target as
            // the explicit subject.
            $actorId = $properties['actor_id'] !== null ? (int) $properties['actor_id'] : null;
            $actorEmail = $properties['actor_email'] ?? null;
            $actorType = $actorId ? 'user' : 'system';
            $subjectType = 'user';
            $subjectId = $userId;
            $subjectLabel = $email;
        } elseif (isset($properties['resident_id'])) {
            $subjectType = 'resident';
            $subjectId = (int) $properties['resident_id'];
            $subjectLabel = $properties['resident_name'] ?? null;
        } elseif (isset($properties['case_number'])) {
            $subjectType = 'blotter';
            $subjectLabel = (string) $properties['case_number'];
        } elseif (isset($properties['control_number'])) {
            $subjectType = 'certificate';
            $subjectLabel = (string) $properties['control_number'];
        }

        try {
            return static::create([
                'occurred_at' => now(),
                'user_id' => $userId,
                'user_email' => $email,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'actor_email' => $actorEmail,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'subject_label' => $subjectLabel,
                'event' => $event,
                'ip_address' => $ip,
                'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 500) : null,
                'properties' => self::redactProperties($properties),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Recursively replace sensitive values (passwords, tokens, secrets,
     * one-time codes) with self::REDACTED. Non-sensitive keys — including
     * rejection/suspension `reason` text, export `filters`, names and case
     * numbers — pass through untouched so accountability is preserved.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function redactProperties(array $properties): array
    {
        foreach ($properties as $key => $value) {
            if (is_array($value)) {
                $properties[$key] = self::redactProperties($value);

                continue;
            }

            if (! is_string($key)) {
                continue;
            }

            $normalized = strtolower((string) $key);

            if (
                in_array($normalized, self::SENSITIVE_KEYS, true)
                || str_contains($normalized, 'password')
                || str_contains($normalized, 'secret')
                || str_contains($normalized, '_token')
                || $normalized === 'token'
            ) {
                $properties[$key] = self::REDACTED;
            }
        }

        return $properties;
    }

    /**
     * Strip PII out of already-persisted properties for the prune-time
     * anonymize pass (see PruneAuditLogsCommand). Event names, actor ids,
     * timestamps and every non-PII value — names, case/control numbers,
     * reasons, filters, counts — pass through untouched so the row stays a
     * queryable accountability record; only emails, IPs, phone-like strings
     * and token-shaped values become self::REDACTED.
     *
     * Key-directed first (an `email`/`ip_address`/`phone`/`token`-ish key
     * redacts its whole scalar value), then pattern-directed over every
     * remaining string, so a stray address inside free text (e.g. a reason)
     * is redacted but the surrounding text survives. Structure — keys,
     * nesting, scalars, nulls — is never altered.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function anonymizeProperties(array $properties, bool $inSensitiveSubtree = false): array
    {
        foreach ($properties as $key => $value) {
            if (is_array($value)) {
                $properties[$key] = self::anonymizeProperties(
                    $value,
                    $inSensitiveSubtree || self::isAnonymizeSensitiveKey($key)
                );

                continue;
            }

            if (! is_string($value) || $value === '' || $value === self::REDACTED) {
                continue;
            }

            $normalizedKey = is_string($key)
                ? strtolower((string) preg_replace('/[^a-z0-9]/i', '', $key))
                : '';

            if ($inSensitiveSubtree || self::isAnonymizeSensitiveKey($key)) {
                // A phone-ish key may hold a labelled value ("tel: 0917…",
                // "Juan — 09171234567"), so redact just the number part and
                // only blank the whole value when that leaves nothing
                // meaningful behind. Every other sensitive key (email, IP,
                // token, secret) owns its value outright.
                if (self::isPhoneKey($normalizedKey)) {
                    $scrubbed = self::scrubPiiPatterns($value);

                    if (trim($scrubbed) === '' || self::isBareNumber($scrubbed)) {
                        $properties[$key] = self::REDACTED;
                    } else {
                        $properties[$key] = $scrubbed;
                    }
                } else {
                    $properties[$key] = self::REDACTED;
                }

                continue;
            }

            $scrubbed = self::scrubPiiPatterns($value);

            if ($scrubbed !== $value) {
                $properties[$key] = $scrubbed;

                continue;
            }

            $trimmed = trim($value);

            if (
                filter_var($trimmed, FILTER_VALIDATE_IP) !== false
                || self::looksLikeToken($trimmed)
            ) {
                $properties[$key] = self::REDACTED;
            }
        }

        return $properties;
    }

    /**
     * Keys whose whole scalar value is PII by definition: anything
     * email-, IP- or token/secret-addressed. Phone-ish keys are matched
     * separately (see isPhoneKey) because their values may carry labels.
     */
    private static function isAnonymizeSensitiveKey(mixed $key): bool
    {
        if (! is_string($key)) {
            return false;
        }

        $normalized = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $key));

        if ($normalized === '' || $normalized === self::REDACTED) {
            return false;
        }

        if (str_contains($normalized, 'email')) {
            return true;
        }

        if (in_array($normalized, ['ip', 'ipaddress', 'remoteaddr', 'clientip', 'forwardedfor'], true)) {
            return true;
        }

        foreach (['token', 'secret', 'apikey', 'authkey', 'privatekey', 'password', 'passwd', 'otp', 'passcode'] as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return self::isPhoneKey($normalized);
    }

    /**
     * Phone-ish keys (phone, mobile, contact, tel, fax…), matched on word
     * fragments that do not collide with ordinary words. Note `contact`
     * also matches contact_email-style keys — those are claimed by the
     * email rule first, which is checked before this one.
     */
    private static function isPhoneKey(string $normalizedKey): bool
    {
        foreach (['phone', 'mobile', 'cellphone', 'telephone', 'contact', 'tel', 'fax'] as $fragment) {
            if ($fragment === 'tel') {
                if (in_array($normalizedKey, ['tel', 'telno', 'telnum', 'telepono'], true)) {
                    return true;
                }

                continue;
            }

            if (str_contains($normalizedKey, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Substring-level PII scrub for free text: email addresses, IPv4/IPv6
     * literals and Philippine mobile formats become self::REDACTED while
     * the surrounding text survives.
     */
    private static function scrubPiiPatterns(string $value): string
    {
        $scrubbed = preg_replace(
            '/[A-Za-z0-9._%+\\-]+@[A-Za-z0-9.\\-]+\\.[A-Za-z]{2,}/',
            self::REDACTED,
            $value
        );

        $scrubbed = preg_replace(
            '/\\b(?:(?:25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)\\.){3}(?:25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)\\b/',
            self::REDACTED,
            (string) $scrubbed
        );

        // Compressed IPv6 always contains "::", which dotted quads, times
        // and version strings never do — safe to match loosely.
        $scrubbed = preg_replace(
            '/[0-9A-Fa-f:]*::[0-9A-Fa-f:]*/',
            self::REDACTED,
            (string) $scrubbed
        );

        // Philippine mobiles: +63… and 09… with optional spacing/hyphens.
        $scrubbed = preg_replace(
            '/\\+63[\\s\\-]?\\d{3}[\\s\\-]?\\d{3}[\\s\\-]?\\d{4}/',
            self::REDACTED,
            (string) $scrubbed
        );

        $scrubbed = preg_replace(
            '/\\b09\\d{2}[\\s\\-]?\\d{3}[\\s\\-]?\\d{4}\\b/',
            self::REDACTED,
            (string) $scrubbed
        );

        return (string) $scrubbed;
    }

    /**
     * A value that is nothing but a dialable number (7+ digits, optional
     * leading + and separators) — years, counts and short codes never reach
     * the digit threshold, so they are never mistaken for phone numbers.
     */
    private static function isBareNumber(string $value): bool
    {
        if (preg_match('/^\\+?[\\d\\s\\-().]{7,25}$/', trim($value)) !== 1) {
            return false;
        }

        return strlen((string) preg_replace('/\\D/', '', $value)) >= 7;
    }

    /**
     * Long opaque strings (API keys, session tokens, base64 blobs): 32+
     * chars from a token alphabet with at least one letter, so pure numeric
     * references and short business codes are never mistaken for secrets.
     */
    private static function looksLikeToken(string $value): bool
    {
        if (strlen($value) < 32 || preg_match('/^[A-Za-z0-9\\-_+\\/=]+$/', $value) !== 1) {
            return false;
        }

        return preg_match('/[A-Za-z]/', $value) === 1;
    }

    public static function recordWithSubject(
        string $event,
        ?int $actorId,
        ?string $actorEmail,
        ?string $ip,
        ?string $userAgent,
        string $subjectType,
        ?int $subjectId,
        ?string $subjectLabel,
        array $properties = [],
    ): ?self {
        $properties = array_merge($properties, [
            'actor_id' => $actorId,
            'actor_email' => $actorEmail,
        ]);

        try {
            return static::create([
                'occurred_at' => now(),
                'user_id' => $subjectType === 'user' ? $subjectId : $actorId,
                'user_email' => $subjectType === 'user' ? $subjectLabel : $actorEmail,
                'actor_type' => $actorId ? 'user' : 'system',
                'actor_id' => $actorId,
                'actor_email' => $actorEmail,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'subject_label' => $subjectLabel,
                'event' => $event,
                'ip_address' => $ip,
                'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 500) : null,
                'properties' => self::redactProperties($properties),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The account that performed the event. There is deliberately no
     * foreign key on actor_id even though it references users when
     * actor_type is 'user': the actor is polymorphic (user/system/guest,
     * null for system and guest rows), the legacy user_id FK with
     * set-null already preserves the user link, and audit writes must
     * never fail — record()/recordWithSubject() swallow every exception
     * so auditing can never break the request it observes. A hard
     * constraint would risk failed inserts (and a failed migration) on
     * legacy rows whose actor has no matching user.
     */
    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Human-readable label for the admin log page.
     */
    public function getEventLabelAttribute(): string
    {
        return match ($this->event) {
            'password_reset.code_requested' => 'Reset code requested',
            'password_reset.completed' => 'Password reset completed',
            'password_reset.failed_code' => 'Failed reset attempt (invalid/expired code)',
            default => ucwords(str_replace(['.', '_'], ' ', $this->event)),
        };
    }
}

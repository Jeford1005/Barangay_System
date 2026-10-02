<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ResetPasswordCodeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class PasswordResetCodeService
{
    public const CODE_TTL_MINUTES = 15;

    /**
     * Alphabet without visually ambiguous characters (0/O, 1/I/L).
     */
    public const CODE_ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public const CODE_REGEX = '/^[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{6}$/i';

    /**
     * Generate a code, store only its hash, and email it to the user.
     *
     * The code is bcrypt-hashed (one-way, salted, slow): a read of the
     * password_reset_tokens table no longer yields anything an attacker can
     * brute-force offline at sha256 speed.
     */
    public function issue(User $user): string
    {
        if (! $user->isActive()) {
            throw new InvalidArgumentException('Password reset codes are only available to active accounts.');
        }

        $code = $this->generateCode();

        // A new code always replaces the previous one, so old codes die instantly.
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make(strtoupper($code)),
                'created_at' => now(),
            ],
        );

        try {
            $user->notify(new ResetPasswordCodeNotification($code, self::CODE_TTL_MINUTES));
        } catch (\Throwable $exception) {
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            throw $exception;
        }

        return $code;
    }

    private function generateCode(): string
    {
        $length = strlen(self::CODE_ALPHABET);
        $code = '';

        for ($i = 0; $i < 6; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, $length - 1)];
        }

        // Guarantee at least two digits so copied email text is unambiguous.
        $positions = (array) array_rand(range(0, 5), 2);

        foreach ($positions as $position) {
            $code[$position] = random_int(2, 9);
        }

        return $code;
    }
}

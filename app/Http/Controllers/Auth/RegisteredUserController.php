<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Resident self-registration.
 *
 * Residents create their own accounts here; staff and administrator accounts
 * are created by an administrator from the Accounts screen. Every account
 * created here lands as role=resident, status=pending and must be approved
 * by an administrator before it can be used.
 */
class RegisteredUserController extends Controller
{
    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->string('reg_name')->trim()->toString(),
            'email' => strtolower($request->string('reg_email')->trim()->toString()),
            'password' => $request->string('reg_password')->toString(),
        ]);

        // Belt and braces: the fillable list cannot carry privileges, but state
        // it explicitly so the contract is visible here too.
        $user->forceFill([
            'role' => User::ROLE_RESIDENT,
            'status' => User::STATUS_PENDING,
        ])->save();

        return redirect()
            ->route('login')
            ->with([
                'status' => 'Your account has been created. Please wait for the barangay administrator to approve it before signing in.',
                'register_modal' => true,
            ]);
    }
}

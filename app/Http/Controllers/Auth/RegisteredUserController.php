<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ResidentApplication;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Resident self-registration.
 *
 * The form collects the resident's full record (name parts, birth date, sex,
 * civil status, phone, address, purok, household) and stores it as a pending
 * ResidentApplication. The administrator reviews it on the Accounts screen,
 * approves the account, then converts the application into a resident profile.
 * Staff and official accounts are still created by an administrator only.
 */
class RegisteredUserController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        // A named error bag keeps registration errors out of the sign-in
        // form's own email/password fields when this posts from the dialog.
        $validated = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'suffix' => ['nullable', 'string', 'max:10'],
            'birth_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:1900-01-01'],
            'sex' => ['required', 'in:Male,Female,Other'],
            'civil_status' => ['required', 'in:Single,Married,Divorced,Widowed,Separated'],
            'phone_number' => ['nullable', 'string', 'max:30', 'regex:/^(?=.*\d)\+?[0-9()\-\s]+$/'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:users,email'],
            'address' => ['required', 'string', 'max:255'],
            'purok_id' => ['nullable', 'integer', 'exists:puroks,id'],
            'household_id' => ['nullable', 'integer', 'exists:households,id'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ], [
            'first_name.required' => 'Please enter your first name.',
            'last_name.required' => 'Please enter your last name.',
            'birth_date.required' => 'Please enter your birth date.',
            'birth_date.date_format' => 'Please enter your birth date as YYYY-MM-DD.',
            'sex.required' => 'Please select your sex.',
            'civil_status.required' => 'Please select your civil status.',
            'phone_number.regex' => 'The phone number must contain at least one digit. Spaces, +, -, and parentheses are allowed.',
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'An account with this email already exists. Try signing in instead.',
            'address.required' => 'Please enter your home address.',
            'purok_id.integer' => 'Please select a purok from the list, or leave it blank.',
            'purok_id.exists' => 'The selected purok is no longer available. Please choose another.',
            'household_id.integer' => 'Please select a household from the list, or leave it blank.',
            'household_id.exists' => 'The selected household is no longer available. Please choose another.',
            'password.required' => 'Please choose a password.',
            'password.min' => 'The password must be at least 8 characters.',
            'password.confirmed' => 'The password confirmation does not match.',
        ])->validateWithBag('register');

        $user = DB::transaction(function () use ($validated) {
            $fullName = trim(implode(' ', array_filter([
                $validated['first_name'],
                $validated['middle_name'] ?? null,
                $validated['last_name'],
                $validated['suffix'] ?? null,
            ])));

            $user = User::create([
                'name' => $fullName,
                'email' => strtolower(trim($validated['email'])),
                'password' => $validated['password'], // hashed cast
            ]);

            // Belt and braces: privileges are never accepted from the form.
            $user->forceFill([
                'role' => User::ROLE_RESIDENT,
                'status' => User::STATUS_PENDING,
            ])->save();

            ResidentApplication::create([
                'user_id' => $user->id,
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'suffix' => $validated['suffix'] ?? null,
                'birth_date' => $validated['birth_date'],
                'sex' => $validated['sex'],
                'civil_status' => $validated['civil_status'],
                'phone_number' => $validated['phone_number'] ?? null,
                'email' => $user->email,
                'address' => $validated['address'],
                'purok_id' => $validated['purok_id'] ?? null,
                'household_id' => $validated['household_id'] ?? null,
                'status' => ResidentApplication::STATUS_PENDING,
            ]);

            return $user;
        });

        AuditLog::record('registered', 'user', $user->id, null, [
            'email' => $user->email,
            'source' => 'self-registration',
        ]);

        // JSON clients (fetch-based dialogs) confirm in place instead.
        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()
            ->route('login')
            ->with([
                'status' => 'Your account has been created. Please wait for the barangay administrator to approve it before signing in.',
                'register_modal' => true,
            ]);
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Purok;
use App\Models\ResidentApplication;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration form.
     *
     * The household dropdown is guest-visible, so it is capped and carries
     * no street detail: the field is optional (staff link the household at
     * approval), and an uncapped code+street list would let anyone harvest
     * the household registry one page load at a time.
     */
    public function create(Request $request): View
    {
        return view('auth.register', [
            'puroks' => Cache::remember('auth.purok-options', now()->addMinutes(5), fn () => Purok::orderBy('name')->get(['id', 'name'])),
            'households' => Cache::remember('auth.household-options-capped', now()->addMinutes(5), fn () => Household::orderBy('household_code')->limit(100)->get(['id', 'household_code'])),
            'old' => $request->old(),
        ]);
    }

    /**
     * Handle a resident self-registration.
     *
     * Registration creates a pending account and an application record. It
     * never claims or mutates an existing resident row based on identity
     * matching; staff must explicitly link or create the resident profile.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        // A named error bag keeps registration errors out of the login form's
        // fields when the dialog on the login page posts here.
        $validated = $request->validateWithBag('register', [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:10'],
            'birth_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:1900-01-01'],
            'sex' => ['required', 'in:Male,Female,Other'],
            'civil_status' => ['required', 'in:Single,Married,Divorced,Widowed,Separated'],
            'phone_number' => ['nullable', 'string', 'max:15', 'regex:/^(?=(?:.*\d){7,})\+?[0-9()\-\s]+$/'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'address' => ['required', 'string', 'max:255'],
            'purok_id' => ['nullable', 'integer', 'min:1', 'max:4294967295', 'exists:puroks,id'],
            'household_id' => ['nullable', 'integer', 'min:1', 'max:4294967295', 'exists:households,id'],
            'password' => ['required', 'string', 'max:72', 'confirmed', Password::min(12)->letters()->numbers()],
        ], [
            'email.unique' => 'An account with this email already exists.',
            'password.confirmed' => 'The password confirmation does not match.',
            'phone_number.regex' => 'The phone number must contain at least 7 digits. Spaces, +, -, and parentheses are allowed.',
            'purok_id.integer' => 'Please select a purok from the list, or leave it blank.',
            'purok_id.exists' => 'The selected purok is no longer available. Please choose another.',
            'household_id.integer' => 'Please select a household from the list, or leave it blank.',
            'household_id.exists' => 'The selected household is no longer available. Please choose another.',
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = new User([
                'name' => trim($validated['first_name'].' '.$validated['last_name']),
                'email' => $validated['email'],
                'password' => $validated['password'], // hashed cast
                'status' => 'pending',
            ]);
            // Role is forced server-side — never trust the form.
            $user->user_type = 'resident';
            $user->save();

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
                'status' => 'Pending',
            ]);

            return $user;
        });

        AuditLog::recordWithSubject(
            'account.registration_submitted',
            null,
            null,
            $request->ip(),
            $request->userAgent(),
            'user',
            $user->id,
            $user->email,
        );

        // The create-account dialog on the login page posts with
        // Accept: application/json — confirm with JSON instead of redirecting.
        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()
            ->route('login')
            ->with('status', 'Registration received. The barangay office will review your application — we will email you once your account is approved.');
    }
}

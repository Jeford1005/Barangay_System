    {{-- ============================================================ --}}
    {{-- Create-account dialog: resident self-registration, same     --}}
    {{-- dialog pattern as the forgot-password flow above. Posts to  --}}
    {{-- the same route as the standalone page with Accept: JSON.    --}}
    {{-- ============================================================ --}}
    <div id="register-modal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="register-title">
        <div id="register-backdrop" class="modal-backdrop absolute inset-0 bg-slate-950/60"></div>

        <div class="absolute inset-0 overflow-hidden">
            <div class="flex min-h-full items-center justify-center p-2 sm:p-4">
                <div id="register-panel" class="modal-panel relative flex max-h-[calc(100dvh-1rem)] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl sm:max-h-[calc(100dvh-2rem)]">

                {{-- Modal header --}}
                <div class="flex shrink-0 items-start justify-between border-b border-slate-200 px-6 py-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-white p-0.5 shadow-sm">
                            <img src="{{ asset('images/bidduang-seal-circle.png') }}" alt="Barangay Bidduang official seal" class="h-full w-full object-contain">
                        </div>
                        <div>
                            <h2 id="register-title" class="text-base font-semibold tracking-tight">Create your resident account</h2>
                            <p class="text-xs text-slate-500">Reviewed by the barangay office</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        id="register-close"
                        aria-label="Close dialog"
                        class="flex h-11 w-11 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Step 1: application form --}}
                <div id="register-step-form" class="min-h-0 flex-1 overflow-y-auto px-6 py-6">
                    <p class="mb-5 text-sm text-slate-500">
                        You'll get an email once the barangay office approves your account.
                    </p>

                    <form id="register-form" method="POST" action="{{ route('register') }}" novalidate>
                        @csrf

                        {{-- Name row --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <label for="register-first_name" class="mb-1.5 block text-sm font-medium text-slate-700">First name *</label>
                                <input id="register-first_name" type="text" name="first_name" value="{{ old('first_name') }}" required maxlength="100" autocomplete="given-name"
                                    class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('first_name', 'register') border-red-500! @enderror">
                                @error('first_name', 'register') <p class="register-error mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="register-middle_name" class="mb-1.5 block text-sm font-medium text-slate-700">Middle name</label>
                                <input id="register-middle_name" type="text" name="middle_name" value="{{ old('middle_name') }}" maxlength="100"
                                    class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                            </div>
                            <div>
                                <label for="register-last_name" class="mb-1.5 block text-sm font-medium text-slate-700">Last name *</label>
                                <input id="register-last_name" type="text" name="last_name" value="{{ old('last_name') }}" required maxlength="100" autocomplete="family-name"
                                    class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('last_name', 'register') border-red-500! @enderror">
                                @error('last_name', 'register') <p class="register-error mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="register-suffix" class="mb-1.5 block text-sm font-medium text-slate-700">Suffix</label>
                                <input id="register-suffix" type="text" name="suffix" value="{{ old('suffix') }}" maxlength="10" placeholder="Jr., III"
                                    class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm placeholder:text-slate-500 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                            </div>
                        </div>

                        {{-- Personal row --}}
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <label for="register-birth_date" class="mb-1.5 block text-sm font-medium text-slate-700">Birth date *</label>
                                <input id="register-birth_date" type="date" name="birth_date" value="{{ old('birth_date') }}" required min="1900-01-01" max="{{ now()->toDateString() }}"
                                    class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('birth_date', 'register') border-red-500! @enderror">
                                @error('birth_date', 'register') <p class="register-error mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="register-sex" class="mb-1.5 block text-sm font-medium text-slate-700">Sex *</label>
                                <select id="register-sex" name="sex" required
                                    class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('sex', 'register') border-red-500! @enderror">
                                    <option value="">Select…</option>
                                    @foreach (['Male', 'Female', 'Other'] as $option)
                                        <option value="{{ $option }}" @selected(old('sex') === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                                @error('sex', 'register') <p class="register-error mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="register-civil_status" class="mb-1.5 block text-sm font-medium text-slate-700">Civil status *</label>
                                <select id="register-civil_status" name="civil_status" required
                                    class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('civil_status', 'register') border-red-500! @enderror">
                                    <option value="">Select…</option>
                                    @foreach (['Single', 'Married', 'Divorced', 'Widowed', 'Separated'] as $option)
                                        <option value="{{ $option }}" @selected(old('civil_status') === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                                @error('civil_status', 'register') <p class="register-error mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        {{-- Contact row --}}
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="register-phone_number" class="mb-1.5 block text-sm font-medium text-slate-700">Phone number</label>
                                <input id="register-phone_number" type="tel" name="phone_number" value="{{ old('phone_number') }}" placeholder="09XX XXX XXXX" maxlength="15" inputmode="numeric" data-phone="true" pattern="[0-9+()\- ]*"
                                    class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm placeholder:text-slate-500 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('phone_number', 'register') border-red-500! @enderror">
                                @error('phone_number', 'register') <p class="register-error mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="register-email" class="mb-1.5 block text-sm font-medium text-slate-700">Email address *</label>
                                <input id="register-email" type="email" name="email" value="{{ old('email') }}" required maxlength="150" autocomplete="username"
                                    placeholder="you@example.com"
                                    class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm placeholder:text-slate-500 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('email', 'register') border-red-500! @enderror">
                                @error('email', 'register') <p class="register-error mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        {{-- Location row --}}
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="register-purok_id" class="mb-1.5 block text-sm font-medium text-slate-700">Purok</label>
                                <select id="register-purok_id" name="purok_id"
                                    class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('purok_id', 'register') border-red-500! @enderror">
                                    <option value="">Select purok…</option>
                                    @foreach ($puroks as $purok)
                                        <option value="{{ $purok->id }}" @selected(old('purok_id') == $purok->id)>{{ $purok->name }}</option>
                                    @endforeach
                                </select>
                                @error('purok_id', 'register') <p class="register-error mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="register-household_id" class="mb-1.5 block text-sm font-medium text-slate-700">Household</label>
                                <select id="register-household_id" name="household_id"
                                    class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('household_id', 'register') border-red-500! @enderror">
                                    <option value="">Select household…</option>
                                    @foreach ($households as $household)
                                        <option value="{{ $household->id }}" @selected(old('household_id') == $household->id)>
                                            {{ $household->household_code }}@if($household->street) — {{ $household->street }}@endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('household_id', 'register') <p class="register-error mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                <p class="mt-1 text-xs text-slate-500">Leave blank if your household isn't listed yet.</p>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label for="register-address" class="mb-1.5 block text-sm font-medium text-slate-700">Home address *</label>
                            <input id="register-address" type="text" name="address" value="{{ old('address') }}" required maxlength="255"
                                placeholder="House no., street, sitio"
                                class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm placeholder:text-slate-500 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('address', 'register') border-red-500! @enderror">
                            @error('address', 'register') <p class="register-error mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Password row --}}
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="register-password" class="mb-1.5 block text-sm font-medium text-slate-700">Password *</label>
                                <div class="relative">
                                    <input id="register-password" type="password" name="password" required minlength="8" autocomplete="new-password"
                                        class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm placeholder:text-slate-500 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('password', 'register') border-red-500! @enderror">
                                </div>
                                @error('password', 'register') <p class="register-error mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="register-password_confirmation" class="mb-1.5 block text-sm font-medium text-slate-700">Confirm password *</label>
                                <div class="relative">
                                    <input id="register-password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                                        class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 pr-10 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                                    <button type="button" data-password-toggle="register-password_confirmation" aria-label="Show password" aria-pressed="false"
                                        class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded text-slate-500 transition-colors hover:text-slate-700 focus:outline-none">
                                        <svg class="icon-eye h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                                        <svg class="icon-eye-off hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                {{-- Sticky footer: the submit lives outside the scroll area so
                     short windows never cut it in half. form= keeps it posting
                     #register-form. Hidden with the form on the done step. --}}
                <div id="register-step-footer" class="shrink-0 border-t border-slate-200 px-6 py-4">
                    <button
                        type="submit"
                        form="register-form"
                        id="register-submit-btn"
                        class="btn btn-primary w-full disabled:bg-slate-300 disabled:text-slate-500"
                    >
                        Submit Application
                    </button>
                </div>

                {{-- Step 2: success --}}
                <div id="register-step-done" class="hidden px-6 py-10 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100">
                        <svg class="h-7 w-7 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4 4L19 7"/></svg>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold tracking-tight">Application received</h3>
                    <p class="mt-1.5 text-sm text-slate-500">
                        The barangay office will review your application — we'll email you once your account is approved.
                    </p>
                    <button
                        type="button"
                        id="register-done-btn"
                        class="mt-6 btn btn-primary w-full"
                    >
                        Back to sign in
                    </button>
                </div>
            </div>
        </div>
    </div>
    </div>
